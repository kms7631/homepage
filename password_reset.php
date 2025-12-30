<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
  redirect('/index.php');
}

$token = trim((string)($_GET['token'] ?? ($_POST['token'] ?? '')));

if ($token === '') {
  flash_set('error', '재설정 토큰이 올바르지 않습니다.');
  redirect('/login.php');
}

if (is_post()) {
  $newPassword = (string)($_POST['password'] ?? '');
  $newPassword2 = (string)($_POST['password2'] ?? '');

  if ($newPassword === '' || $newPassword2 === '') {
    flash_set('error', '비밀번호를 입력하세요.');
  } elseif ($newPassword !== $newPassword2) {
    flash_set('error', '비밀번호 확인이 일치하지 않습니다.');
  } else {
    try {
      $db = db();
      $db->beginTransaction();
      try {
        $tokenHash = hash('sha256', $token);

        $st = $db->prepare(
          'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at
           FROM password_resets pr
           WHERE pr.token_hash = ?
           LIMIT 1'
        );
        $st->execute([$tokenHash]);
        $pr = $st->fetch();

        if (!$pr) {
          throw new RuntimeException('재설정 토큰이 유효하지 않습니다.');
        }
        if (!empty($pr['used_at'])) {
          throw new RuntimeException('이미 사용된 재설정 링크입니다.');
        }

        // 관리자 승인 필요
        if (empty($pr['approved_at'])) {
          throw new RuntimeException('관리자 승인 전에는 재설정할 수 없습니다.');
        }
        $expiresAt = (string)($pr['expires_at'] ?? '');
        if ($expiresAt === '' || strtotime($expiresAt) < time()) {
          throw new RuntimeException('재설정 링크가 만료되었습니다. 다시 요청하세요.');
        }

        $userId = (int)($pr['user_id'] ?? 0);
        if ($userId <= 0) {
          throw new RuntimeException('재설정 대상 사용자가 올바르지 않습니다.');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $up = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $up->execute([$hash, $userId]);

        $mark = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $mark->execute([(int)$pr['id']]);

        $db->commit();
      } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
      }

      flash_set('success', '비밀번호가 재설정되었습니다. 새 비밀번호로 로그인하세요.');
      redirect('/login.php');
    } catch (PDOException $e) {
      error_log('[password_reset] PDOException: ' . $e->getMessage());
      flash_set('error', 'DB 오류로 재설정할 수 없습니다.');
    } catch (Throwable $e) {
      flash_set('error', $e->getMessage());
    }
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-brand-outside"><?= e(APP_NAME) ?></div>
  <div class="card auth-card">
    <form method="post" action="<?= e(url('/password_reset.php')) ?>" class="auth-form">
      <input type="hidden" name="token" value="<?= e($token) ?>" />

      <div class="field">
        <div class="label">비밀번호 재설정</div>
        <div class="muted" style="margin-top:6px">새 비밀번호를 설정하세요.</div>
      </div>

      <div class="field">
        <div class="label">새 비밀번호</div>
        <input class="input" type="password" name="password" required />
      </div>

      <div class="field">
        <div class="label">새 비밀번호 확인</div>
        <input class="input" type="password" name="password2" required />
      </div>

      <button class="btn" type="submit" style="width:100%">비밀번호 변경</button>

      <div class="auth-right-link" style="margin-top:10px">
        <a class="small" href="<?= e(url('/login.php')) ?>">로그인으로 돌아가기</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
