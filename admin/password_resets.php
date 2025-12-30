<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$db = db();

function make_reset_token(): string {
  return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function make_temp_password(): string {
  $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
  $lower = 'abcdefghijkmnopqrstuvwxyz';
  $digits = '23456789';
  $all = $upper . $lower . $digits;

  $chars = [];
  $chars[] = $upper[random_int(0, strlen($upper) - 1)];
  $chars[] = $lower[random_int(0, strlen($lower) - 1)];
  $chars[] = $digits[random_int(0, strlen($digits) - 1)];

  $len = 12;
  while (count($chars) < $len) {
    $chars[] = $all[random_int(0, strlen($all) - 1)];
  }

  // 안전한 셔플
  for ($i = count($chars) - 1; $i > 0; $i--) {
    $j = random_int(0, $i);
    $tmp = $chars[$i];
    $chars[$i] = $chars[$j];
    $chars[$j] = $tmp;
  }

  return implode('', $chars);
}

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? ''));
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      throw new RuntimeException('요청이 올바르지 않습니다.');
    }

    if ($action === 'approve') {
      $me = current_user();
      $approvedBy = $me ? (int)$me['id'] : null;

      $st = $db->prepare('SELECT pr.id, pr.user_id, pr.used_at, u.email
                          FROM password_resets pr
                          JOIN users u ON u.id = pr.user_id
                          WHERE pr.id = ?
                          LIMIT 1');
      $st->execute([$id]);
      $row = $st->fetch();
      if (!$row) {
        throw new RuntimeException('요청을 찾을 수 없습니다.');
      }
      if (!empty($row['used_at'])) {
        throw new RuntimeException('이미 처리된 요청입니다.');
      }

      $token = make_reset_token();
      $tokenHash = hash('sha256', $token);
      $expiresAt = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+30 minutes')->format('Y-m-d H:i:s');

      $db->beginTransaction();
      try {
        // 해당 사용자 기존 미사용 요청은 모두 무효화
        $inv = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL AND id <> ?');
        $inv->execute([(int)$row['user_id'], $id]);

        $up = $db->prepare('UPDATE password_resets
                            SET token_hash = ?, expires_at = ?, approved_at = NOW(), approved_by = ?
                            WHERE id = ?');
        $up->execute([$tokenHash, $expiresAt, $approvedBy, $id]);

        $db->commit();
      } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
      }

      $resetUrl = url('/password_reset.php?token=' . urlencode($token));
      flash_set('success', '승인 완료. 사용자에게 아래 링크를 전달하세요: ' . $resetUrl);
      redirect('/admin/password_resets.php');
    }

    if ($action === 'issue_temp') {
      $me = current_user();
      $approvedBy = $me ? (int)$me['id'] : null;

      $st = $db->prepare('SELECT pr.id, pr.user_id, pr.used_at, u.email
                          FROM password_resets pr
                          JOIN users u ON u.id = pr.user_id
                          WHERE pr.id = ?
                          LIMIT 1');
      $st->execute([$id]);
      $row = $st->fetch();
      if (!$row) {
        throw new RuntimeException('요청을 찾을 수 없습니다.');
      }
      if (!empty($row['used_at'])) {
        throw new RuntimeException('이미 처리된 요청입니다.');
      }

      $tempPassword = make_temp_password();
      $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

      $db->beginTransaction();
      try {
        // 해당 사용자 기존 미사용 요청은 모두 무효화
        $inv = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL AND id <> ?');
        $inv->execute([(int)$row['user_id'], $id]);

        // 비밀번호 즉시 변경
        $upUser = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upUser->execute([$hash, (int)$row['user_id']]);

        // 현재 요청은 처리 완료로 마킹
        $upReq = $db->prepare('UPDATE password_resets SET approved_at = NOW(), approved_by = ?, used_at = NOW() WHERE id = ?');
        $upReq->execute([$approvedBy, $id]);

        $db->commit();
      } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
      }

      flash_set('success', '임시 비밀번호가 발급되었습니다. 사용자에게 전달하세요: ' . (string)$row['email'] . ' / 임시 비밀번호: ' . $tempPassword);
      redirect('/admin/password_resets.php');
    }

    if ($action === 'reject') {
      $st = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
      $st->execute([$id]);
      flash_set('success', '요청이 거절(종료)되었습니다.');
      redirect('/admin/password_resets.php');
    }

    throw new RuntimeException('지원하지 않는 action 입니다.');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

// 최근 요청(최신순)
$st = $db->query('SELECT pr.id, pr.created_at, pr.expires_at, pr.approved_at, pr.used_at,
                         u.email, u.name,
                         au.email AS approved_by_email
                  FROM password_resets pr
                  JOIN users u ON u.id = pr.user_id
                  LEFT JOIN users au ON au.id = pr.approved_by
                  ORDER BY pr.id DESC
                  LIMIT 100');
$rows = $st->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h1 class="h1">비밀번호 재설정 요청</h1>
  <div class="small muted">사용자가 비밀번호 찾기를 요청하면 여기에 표시됩니다. 승인 시 30분 유효 링크가 발급됩니다.</div>

  <div style="margin-top:12px"></div>

  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>이메일</th>
        <th>이름</th>
        <th>요청일</th>
        <th>승인</th>
        <th>만료</th>
        <th>처리</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted">요청이 없습니다.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $isUsed = !empty($r['used_at']);
            $isApproved = !empty($r['approved_at']);
            $isExpired = !empty($r['expires_at']) && strtotime((string)$r['expires_at']) < time();
          ?>
          <tr>
            <td><?= e((string)$r['id']) ?></td>
            <td><?= e((string)$r['email']) ?></td>
            <td><?= e((string)$r['name']) ?></td>
            <td><?= e((string)$r['created_at']) ?></td>
            <td>
              <?php if ($isApproved): ?>
                <span class="badge ok">승인</span>
                <div class="small muted"><?= e((string)($r['approved_by_email'] ?? '')) ?></div>
              <?php else: ?>
                <span class="badge">대기</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($isExpired): ?>
                <span class="badge danger">만료</span>
              <?php else: ?>
                <span class="badge">유효</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <?php if ($isUsed): ?>
                <span class="muted">-</span>
              <?php else: ?>
                <form method="post" action="<?= e(url('/admin/password_resets.php')) ?>" style="display:inline" onsubmit="return confirm('승인하면 새 링크가 발급됩니다. 진행할까요?');">
                  <input type="hidden" name="action" value="approve" />
                  <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>" />
                  <button class="btn" type="submit">승인</button>
                </form>
                <form method="post" action="<?= e(url('/admin/password_resets.php')) ?>" style="display:inline" onsubmit="return confirm('임시 비밀번호로 즉시 초기화합니다. 진행할까요?');">
                  <input type="hidden" name="action" value="issue_temp" />
                  <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>" />
                  <button class="btn danger" type="submit">임시비번</button>
                </form>
                <form method="post" action="<?= e(url('/admin/password_resets.php')) ?>" style="display:inline" onsubmit="return confirm('요청을 거절(종료)할까요?');">
                  <input type="hidden" name="action" value="reject" />
                  <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>" />
                  <button class="btn secondary" type="submit">거절</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div style="margin-top:10px">
    <a class="btn secondary" href="<?= e(url('/admin/users.php')) ?>">사용자</a>
    <a class="btn secondary" href="<?= e(url('/admin/items.php')) ?>">품목</a>
    <a class="btn secondary" href="<?= e(url('/admin/inventory.php')) ?>">재고</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
