<?php
declare(strict_types=1);

final class Inquiry {
  private const REOPEN_DAYS = 7;
  private static ?bool $supportsStatus = null;
  private static ?bool $supportsLastMessageAt = null;
  private static ?bool $supportsAdminRead = null;

  private static function hasInquiryColumn(PDO $db, string $column): bool {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
      return false;
    }
    try {
      // 권한이 제한된 계정에서도 동작하도록 SHOW COLUMNS 대신 SELECT로 확인
      $db->query('SELECT `' . $column . '` FROM inquiries LIMIT 0');
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }

  public static function supportsStatus(PDO $db): bool {
    if (self::$supportsStatus !== null) {
      return self::$supportsStatus;
    }
    self::$supportsStatus = self::hasInquiryColumn($db, 'status');
    return self::$supportsStatus;
  }

  private static function supportsLastMessageAt(PDO $db): bool {
    if (self::$supportsLastMessageAt !== null) {
      return self::$supportsLastMessageAt;
    }
    self::$supportsLastMessageAt = self::hasInquiryColumn($db, 'last_message_at');
    return self::$supportsLastMessageAt;
  }

  private static function supportsAdminRead(PDO $db): bool {
    if (self::$supportsAdminRead !== null) {
      return self::$supportsAdminRead;
    }
    self::$supportsAdminRead = self::hasInquiryColumn($db, 'admin_last_read_at');
    return self::$supportsAdminRead;
  }
  private static function now(): string {
    return date('Y-m-d H:i:s');
  }

  private static function userRole(PDO $db, int $userId): string {
    $st = $db->prepare('SELECT role FROM users WHERE id = ?');
    $st->execute([$userId]);
    $role = (string)($st->fetchColumn() ?: '');
    return $role !== '' ? $role : 'user';
  }

  private static function normalizeStatus(?string $status): string {
    $s = strtoupper(trim((string)$status));
    if (!in_array($s, ['NEW', 'IN_PROGRESS', 'RESOLVED'], true)) {
      return 'IN_PROGRESS';
    }
    return $s;
  }

  public static function create(PDO $db, int $senderId, int $receiverId, string $title, string $body): int {
    if ($senderId <= 0) {
      throw new RuntimeException('보내는 사용자가 올바르지 않습니다.');
    }
    if ($receiverId <= 0) {
      throw new RuntimeException('받는 사용자를 선택하세요.');
    }
    if ($senderId === $receiverId) {
      throw new RuntimeException('본인에게는 문의를 보낼 수 없습니다.');
    }

    $title = trim($title);
    $body = trim($body);
    if ($title === '') {
      throw new RuntimeException('제목을 입력하세요.');
    }
    if ($body === '') {
      throw new RuntimeException('내용을 입력하세요.');
    }

    $now = self::now();

    $db->beginTransaction();
    try {
      if (!self::supportsStatus($db)) {
        // DB 마이그레이션 전 호환
        $st = $db->prepare('INSERT INTO inquiries (sender_id, receiver_id, title, body, active) VALUES (?, ?, ?, ?, 1)');
        $st->execute([$senderId, $receiverId, $title, $body]);
      } else {
        $senderRole = self::userRole($db, $senderId);
        // 정책: 사용자가 문의를 생성하면 NEW, 관리자가 생성하면 이미 진행중으로 간주
        $status = ($senderRole === 'admin') ? 'IN_PROGRESS' : 'NEW';

        $st = $db->prepare(
          'INSERT INTO inquiries (sender_id, receiver_id, title, body, active, status, last_message_at) VALUES (?, ?, ?, ?, 1, ?, ?)'
        );
        $st->execute([$senderId, $receiverId, $title, $body, $status, $now]);
      }

      $inquiryId = (int)$db->lastInsertId();

      // 우측 채팅 패널에 바로 노출되도록, 문의 본문을 첫 메시지로도 저장
      try {
        $st2 = $db->prepare('INSERT INTO inquiry_messages (inquiry_id, sender_id, body, active) VALUES (?, ?, ?, 1)');
        $st2->execute([$inquiryId, $senderId, $body]);

        // last_message_at 지원 시 생성 시각으로 정렬 고정
        if (self::supportsLastMessageAt($db)) {
          $st3 = $db->prepare('UPDATE inquiries SET last_message_at = ? WHERE id = ?');
          $st3->execute([$now, $inquiryId]);
        }
      } catch (Throwable $e) {
        // 마이그레이션 전/환경 차이로 메시지 테이블이 없을 수 있어 폴백
      }

      $db->commit();
      return $inquiryId;
    } catch (Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      throw $e;
    }
  }

  public static function listForUser(PDO $db, int $userId, string $box = 'all', int $limit = 100): array {
    $limit = max(1, min(300, $limit));
    $box = strtoupper(trim($box));
    if (!in_array($box, ['ALL', 'SENT', 'RECEIVED'], true)) {
      $box = 'ALL';
    }

    $where = 'i.active = 1 AND (i.sender_id = ? OR i.receiver_id = ?)';
    if ($box === 'SENT') {
      $where = 'i.active = 1 AND i.sender_id = ?';
    } elseif ($box === 'RECEIVED') {
      $where = 'i.active = 1 AND i.receiver_id = ?';
    }

    $st = $db->prepare(
      'SELECT i.id, i.sender_id, i.receiver_id, i.title, i.created_at,
              su.name AS sender_name, su.email AS sender_email,
              ru.name AS receiver_name, ru.email AS receiver_email
       FROM inquiries i
       JOIN users su ON su.id = i.sender_id
       JOIN users ru ON ru.id = i.receiver_id
       WHERE ' . $where . '
       ORDER BY i.created_at DESC, i.id DESC
       LIMIT ?'
    );

    if ($box === 'ALL') {
      $st->bindValue(1, $userId, PDO::PARAM_INT);
      $st->bindValue(2, $userId, PDO::PARAM_INT);
      $st->bindValue(3, $limit, PDO::PARAM_INT);
    } else {
      $st->bindValue(1, $userId, PDO::PARAM_INT);
      $st->bindValue(2, $limit, PDO::PARAM_INT);
    }
    $st->execute();
    return $st->fetchAll();
  }

  public static function countThreadsByStatusForUser(PDO $db, int $userId): array {
    if (!self::supportsStatus($db)) {
      // 마이그레이션 전: 전부 진행중으로 간주
      $all = self::listThreadsForUserLegacy($db, $userId, 300);
      return ['NEW' => 0, 'IN_PROGRESS' => count($all), 'RESOLVED' => 0];
    }
    $st = $db->prepare(
      "SELECT i.status, COUNT(*) AS cnt
       FROM inquiries i
       WHERE i.active = 1 AND (i.sender_id = ? OR i.receiver_id = ?)
       GROUP BY i.status"
    );
    $st->execute([$userId, $userId]);
    $rows = $st->fetchAll();
    $out = ['NEW' => 0, 'IN_PROGRESS' => 0, 'RESOLVED' => 0];
    foreach ($rows as $r) {
      $k = self::normalizeStatus((string)($r['status'] ?? ''));
      $out[$k] = (int)($r['cnt'] ?? 0);
    }
    return $out;
  }

  public static function listThreadsForUser(PDO $db, int $userId, int $limit = 200): array {
    if (!self::supportsStatus($db)) {
      return self::listThreadsForUserLegacy($db, $userId, $limit);
    }
    return self::listThreadsForUserByStatus($db, $userId, 'ALL', $limit);
  }

  private static function listThreadsForUserLegacy(PDO $db, int $userId, int $limit = 200): array {
    $limit = max(1, min(300, $limit));
    $st = $db->prepare(
      "SELECT i.id, i.sender_id, i.receiver_id, i.title, i.created_at,
              GREATEST(i.updated_at, COALESCE(lm.last_message_at, i.updated_at)) AS last_activity_at,
              su.name AS sender_name, su.email AS sender_email,
              ru.name AS receiver_name, ru.email AS receiver_email
       FROM inquiries i
       JOIN users su ON su.id = i.sender_id
       JOIN users ru ON ru.id = i.receiver_id
       LEFT JOIN (
         SELECT inquiry_id, MAX(created_at) AS last_message_at
         FROM inquiry_messages
         WHERE active = 1
         GROUP BY inquiry_id
       ) lm ON lm.inquiry_id = i.id
       WHERE i.active = 1 AND (i.sender_id = ? OR i.receiver_id = ?)
       ORDER BY last_activity_at DESC, i.id DESC
       LIMIT ?"
    );
    $st->bindValue(1, $userId, PDO::PARAM_INT);
    $st->bindValue(2, $userId, PDO::PARAM_INT);
    $st->bindValue(3, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  public static function listThreadsForUserByStatus(PDO $db, int $userId, string $status = 'ALL', int $limit = 200): array {
    if (!self::supportsStatus($db)) {
      return self::listThreadsForUserLegacy($db, $userId, $limit);
    }
    $limit = max(1, min(300, $limit));

    $status = strtoupper(trim($status));
    $filterStatus = null;
    if (in_array($status, ['NEW', 'IN_PROGRESS', 'RESOLVED'], true)) {
      $filterStatus = $status;
    }

    $meRole = self::userRole($db, $userId);
    $isAdmin = ($meRole === 'admin');
    $canAdminRead = $isAdmin && self::supportsAdminRead($db);

    $st = $db->prepare(
      "SELECT i.id, i.sender_id, i.receiver_id, i.title, i.created_at,
              i.status, i.last_message_at,
              COALESCE(i.last_message_at, i.updated_at, i.created_at) AS last_activity_at,
              su.name AS sender_name, su.email AS sender_email,
              ru.name AS receiver_name, ru.email AS receiver_email,
              " . ($canAdminRead
                ? "(
                    SELECT COUNT(*)
                    FROM inquiry_messages m
                    JOIN users mu ON mu.id = m.sender_id
                    WHERE m.active = 1
                      AND m.inquiry_id = i.id
                      AND mu.role = 'user'
                      AND m.created_at > COALESCE(i.admin_last_read_at, '1970-01-01 00:00:00')
                  ) AS unread_for_admin"
                : "0 AS unread_for_admin") . "
       FROM inquiries i
       JOIN users su ON su.id = i.sender_id
       JOIN users ru ON ru.id = i.receiver_id
       WHERE i.active = 1
         AND (i.sender_id = ? OR i.receiver_id = ?)
         " . ($filterStatus ? "AND i.status = " . $db->quote($filterStatus) : "") . "
       ORDER BY last_activity_at DESC, i.id DESC
       LIMIT ?"
    );
    $st->bindValue(1, $userId, PDO::PARAM_INT);
    $st->bindValue(2, $userId, PDO::PARAM_INT);
    $st->bindValue(3, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  public static function findForUser(PDO $db, int $id, int $userId): ?array {
    if (!self::supportsStatus($db)) {
      $st = $db->prepare(
        'SELECT i.id, i.sender_id, i.receiver_id, i.title, i.body, i.created_at, i.updated_at,
                su.name AS sender_name, su.email AS sender_email,
                ru.name AS receiver_name, ru.email AS receiver_email
         FROM inquiries i
         JOIN users su ON su.id = i.sender_id
         JOIN users ru ON ru.id = i.receiver_id
         WHERE i.id = ? AND i.active = 1 AND (i.sender_id = ? OR i.receiver_id = ?)'
      );
      $st->execute([$id, $userId, $userId]);
      $row = $st->fetch();
      if (!$row) {
        return null;
      }
      // 마이그레이션 전에도 화면이 깨지지 않도록 기본값 제공
      $row['status'] = 'IN_PROGRESS';
      $row['resolved_at'] = null;
      $row['resolved_by_role'] = null;
      $row['resolved_by_id'] = null;
      $row['resolved_reason'] = null;
      $row['resolved_note'] = null;
      $row['last_message_at'] = null;
      $row['admin_last_read_at'] = null;
      return $row;
    }

    $st = $db->prepare(
      'SELECT i.id, i.sender_id, i.receiver_id, i.title, i.body, i.created_at, i.updated_at,
              i.status, i.resolved_at, i.resolved_by_role, i.resolved_by_id, i.resolved_reason, i.resolved_note,
              i.last_message_at, i.admin_last_read_at,
              su.name AS sender_name, su.email AS sender_email,
              ru.name AS receiver_name, ru.email AS receiver_email
       FROM inquiries i
       JOIN users su ON su.id = i.sender_id
       JOIN users ru ON ru.id = i.receiver_id
       WHERE i.id = ? AND i.active = 1 AND (i.sender_id = ? OR i.receiver_id = ?)'
    );
    $st->execute([$id, $userId, $userId]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function addMessage(PDO $db, int $inquiryId, int $senderId, int $viewerId, string $body): int {
    $inquiry = self::findForUser($db, $inquiryId, $viewerId);
    if (!$inquiry) {
      throw new RuntimeException('문의 내역을 찾을 수 없습니다.');
    }

    $supportsStatus = self::supportsStatus($db);
    $status = $supportsStatus ? self::normalizeStatus((string)($inquiry['status'] ?? 'IN_PROGRESS')) : 'IN_PROGRESS';
    if ($supportsStatus && $status === 'RESOLVED') {
      throw new RuntimeException('해결된 문의입니다. “다시 열기” 후 메시지를 보낼 수 있습니다.');
    }

    if ($senderId <= 0) {
      throw new RuntimeException('사용자 정보가 올바르지 않습니다.');
    }
    // senderId는 반드시 참여자여야 함
    $sid = (int)($inquiry['sender_id'] ?? 0);
    $rid = (int)($inquiry['receiver_id'] ?? 0);
    if ($senderId !== $sid && $senderId !== $rid) {
      throw new RuntimeException('접근 권한이 없습니다.');
    }

    $body = trim($body);
    if ($body === '') {
      throw new RuntimeException('답변 내용을 입력하세요.');
    }

    $now = self::now();
    $senderRole = self::userRole($db, $senderId);

    $db->beginTransaction();
    try {
      $st = $db->prepare('INSERT INTO inquiry_messages (inquiry_id, sender_id, body, active) VALUES (?, ?, ?, 1)');
      $st->execute([$inquiryId, $senderId, $body]);
      $messageId = (int)$db->lastInsertId();

      if (self::supportsLastMessageAt($db)) {
        $st2 = $db->prepare('UPDATE inquiries SET last_message_at = ? WHERE id = ?');
        $st2->execute([$now, $inquiryId]);
      }

      if ($supportsStatus) {
        // 상태 전환: NEW 상태에서 관리자의 첫 응답 -> IN_PROGRESS
        if ($status === 'NEW' && $senderRole === 'admin') {
          $st3 = $db->prepare("UPDATE inquiries SET status = 'IN_PROGRESS' WHERE id = ? AND status = 'NEW'");
          $st3->execute([$inquiryId]);
        }
      }

      $db->commit();
      return $messageId;
    } catch (Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      throw $e;
    }
  }

  public static function markRead(PDO $db, int $inquiryId, int $viewerId): void {
    if (!self::supportsAdminRead($db)) {
      return;
    }
    $inquiry = self::findForUser($db, $inquiryId, $viewerId);
    if (!$inquiry) {
      return;
    }
    $role = self::userRole($db, $viewerId);
    if ($role !== 'admin') {
      return;
    }
    $now = self::now();
    $st = $db->prepare('UPDATE inquiries SET admin_last_read_at = ? WHERE id = ?');
    $st->execute([$now, $inquiryId]);
  }

  public static function resolve(PDO $db, int $inquiryId, int $actorId, string $actorRole, ?string $reason, ?string $note): void {
    if (!self::supportsStatus($db)) {
      throw new RuntimeException('DB 마이그레이션이 필요합니다. (문의 상태 컬럼이 없습니다)');
    }
    $inquiry = self::findForUser($db, $inquiryId, $actorId);
    if (!$inquiry) {
      throw new RuntimeException('문의 내역을 찾을 수 없습니다.');
    }

    $status = self::normalizeStatus((string)($inquiry['status'] ?? 'IN_PROGRESS'));
    if ($status === 'RESOLVED') {
      return;
    }

    $actorRole = strtoupper(trim($actorRole));
    if (!in_array($actorRole, ['USER', 'ADMIN'], true)) {
      throw new RuntimeException('잘못된 요청입니다.');
    }

    $reason = $reason !== null ? trim($reason) : null;
    $note = $note !== null ? trim($note) : null;

    if ($actorRole === 'ADMIN') {
      if ($reason === null || $reason === '') {
        throw new RuntimeException('해결 사유를 선택하세요.');
      }
      $allowed = ['해결 완료', '사용자 응답 없음', '중복 문의', '안내 완료'];
      if (!in_array($reason, $allowed, true)) {
        throw new RuntimeException('해결 사유가 올바르지 않습니다.');
      }
    } else {
      // 사용자 해결 처리: 사유/메모 저장하지 않음(요구사항 최소)
      $reason = null;
      $note = null;
    }

    $now = self::now();
    $db->beginTransaction();
    try {
      $st = $db->prepare(
        "UPDATE inquiries
         SET status = 'RESOLVED',
             resolved_at = ?,
             resolved_by_role = ?,
             resolved_by_id = ?,
             resolved_reason = ?,
             resolved_note = ?,
             last_message_at = COALESCE(last_message_at, ?)
         WHERE id = ? AND status IN ('NEW','IN_PROGRESS')"
      );
      $st->execute([$now, $actorRole, $actorId, $reason, $note, $now, $inquiryId]);

      if ($actorRole === 'ADMIN') {
        $msg = '관리자에 의해 문의가 해결 처리되었습니다. (사유: ' . $reason . ')';
        $st2 = $db->prepare('INSERT INTO inquiry_messages (inquiry_id, sender_id, body, active) VALUES (?, ?, ?, 1)');
        $st2->execute([$inquiryId, $actorId, $msg]);
        $st3 = $db->prepare('UPDATE inquiries SET last_message_at = ? WHERE id = ?');
        $st3->execute([$now, $inquiryId]);
      }

      $db->commit();
    } catch (Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      throw $e;
    }
  }

  public static function reopen(PDO $db, int $inquiryId, int $actorId): void {
    if (!self::supportsStatus($db)) {
      throw new RuntimeException('DB 마이그레이션이 필요합니다. (문의 상태 컬럼이 없습니다)');
    }
    $inquiry = self::findForUser($db, $inquiryId, $actorId);
    if (!$inquiry) {
      throw new RuntimeException('문의 내역을 찾을 수 없습니다.');
    }
    $status = self::normalizeStatus((string)($inquiry['status'] ?? 'IN_PROGRESS'));
    if ($status !== 'RESOLVED') {
      return;
    }

    $resolvedAt = (string)($inquiry['resolved_at'] ?? '');
    if ($resolvedAt !== '') {
      $ts = strtotime($resolvedAt);
      if ($ts !== false) {
        $limit = time() - (self::REOPEN_DAYS * 86400);
        if ($ts < $limit) {
          throw new RuntimeException('해결된 지 ' . self::REOPEN_DAYS . '일이 지난 문의는 다시 열 수 없습니다.');
        }
      }
    }

    $st = $db->prepare("UPDATE inquiries SET status = 'IN_PROGRESS' WHERE id = ? AND status = 'RESOLVED'");
    $st->execute([$inquiryId]);
  }

  public static function listMessages(PDO $db, int $inquiryId, int $viewerId, int $limit = 200): array {
    $limit = max(1, min(500, $limit));
    $inquiry = self::findForUser($db, $inquiryId, $viewerId);
    if (!$inquiry) {
      return [];
    }

    $st = $db->prepare(
      'SELECT m.id, m.inquiry_id, m.sender_id, m.body, m.created_at,
              u.name AS sender_name, u.email AS sender_email
       FROM inquiry_messages m
       JOIN users u ON u.id = m.sender_id
       JOIN inquiries i ON i.id = m.inquiry_id
       WHERE m.active = 1 AND i.active = 1 AND m.inquiry_id = ?
       ORDER BY m.created_at ASC, m.id ASC
       LIMIT ?'
    );
    $st->bindValue(1, $inquiryId, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  public static function findMessageForUser(PDO $db, int $messageId, int $viewerId): ?array {
    $st = $db->prepare(
      'SELECT m.id, m.inquiry_id, m.sender_id, m.body, m.created_at,
              u.name AS sender_name, u.email AS sender_email
       FROM inquiry_messages m
       JOIN inquiries i ON i.id = m.inquiry_id
       JOIN users u ON u.id = m.sender_id
       WHERE m.id = ? AND m.active = 1 AND i.active = 1 AND (i.sender_id = ? OR i.receiver_id = ?)'
    );
    $st->execute([$messageId, $viewerId, $viewerId]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function update(PDO $db, int $id, int $userId, string $title, string $body): void {
    $row = self::findForUser($db, $id, $userId);
    if (!$row) {
      throw new RuntimeException('문의 내역을 찾을 수 없습니다.');
    }

    $title = trim($title);
    $body = trim($body);
    if ($title === '') {
      throw new RuntimeException('제목을 입력하세요.');
    }
    if ($body === '') {
      throw new RuntimeException('내용을 입력하세요.');
    }

    $st = $db->prepare('UPDATE inquiries SET title = ?, body = ? WHERE id = ?');
    $st->execute([$title, $body, $id]);
  }

  public static function delete(PDO $db, int $id, int $userId): void {
    $row = self::findForUser($db, $id, $userId);
    if (!$row) {
      throw new RuntimeException('삭제할 문의 내역을 찾을 수 없습니다.');
    }

    // soft delete: 어느 한쪽이 삭제하면 양쪽에서 숨김
    $st = $db->prepare('UPDATE inquiries SET active = 0 WHERE id = ?');
    $st->execute([$id]);

    // 연결된 답변도 숨김 처리(정리 목적; 조회는 inquiry.active로도 차단됨)
    try {
      $st2 = $db->prepare('UPDATE inquiry_messages SET active = 0 WHERE inquiry_id = ?');
      $st2->execute([$id]);
    } catch (Throwable $e) {
      // ignore
    }
  }
}
