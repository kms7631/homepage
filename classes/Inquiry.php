<?php
declare(strict_types=1);

final class Inquiry {
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

    $st = $db->prepare('INSERT INTO inquiries (sender_id, receiver_id, title, body, active) VALUES (?, ?, ?, ?, 1)');
    $st->execute([$senderId, $receiverId, $title, $body]);
    return (int)$db->lastInsertId();
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

  public static function findForUser(PDO $db, int $id, int $userId): ?array {
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
    return $row ?: null;
  }

  public static function addMessage(PDO $db, int $inquiryId, int $senderId, int $viewerId, string $body): int {
    $inquiry = self::findForUser($db, $inquiryId, $viewerId);
    if (!$inquiry) {
      throw new RuntimeException('문의 내역을 찾을 수 없습니다.');
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

    $st = $db->prepare('INSERT INTO inquiry_messages (inquiry_id, sender_id, body, active) VALUES (?, ?, ?, 1)');
    $st->execute([$inquiryId, $senderId, $body]);
    return (int)$db->lastInsertId();
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
