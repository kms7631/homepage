<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
  redirect('/index.php');
}

$email = '';

if (is_post()) {
  $email = trim((string)($_POST['email'] ?? ''));

  // 사용자 존재 여부와 관계없이 동일한 메시지로 응답(계정 유무 노출 방지)
  try {
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $db = db();
      $u = User::findByEmail($db, $email);

      if ($u) {
        $userId = (int)($u['id'] ?? 0);
        if ($userId > 0) {
          // 요청 접수: 관리자 승인 후 새 토큰을 발급해 전달하는 흐름
          // (요청 단계에서 만든 토큰은 사용자에게 노출하지 않음)
          $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
          $tokenHash = hash('sha256', $token);
          $expiresAt = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+7 days')->format('Y-m-d H:i:s');

          // 기존 미사용 토큰은 무효화
          $st = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
          $st->execute([$userId]);

          $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
          $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
          $ua = mb_substr($ua, 0, 255, 'UTF-8');

          $ins = $db->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, request_ip, user_agent) VALUES (?, ?, ?, ?, ?)');
          $ins->execute([$userId, $tokenHash, $expiresAt, ($ip !== '' ? $ip : null), ($ua !== '' ? $ua : null)]);

          // 운영 환경: 링크는 관리자가 승인 시 새로 발급하여 전달
          error_log('[password_reset] reset request created for ' . $email);
        }
      }
    }

    flash_set('success', '요청이 접수되었습니다. 관리자가 승인하면 비밀번호를 재설정할 수 있습니다.');
    redirect('/login.php');
  } catch (PDOException $e) {
    error_log('[password_forgot] PDOException: ' . $e->getMessage());
    flash_set('error', 'DB 오류로 요청을 처리할 수 없습니다.');
  } catch (Throwable $e) {
    error_log('[password_forgot] Exception: ' . $e->getMessage());
    flash_set('error', '요청 처리 중 오류가 발생했습니다.');
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-brand-outside"><?= e(APP_NAME) ?></div>
  <div class="card auth-card">
    <form method="post" action="<?= e(url('/password_forgot.php')) ?>" class="auth-form">
      <div class="field">
        <div class="label">비밀번호 찾기</div>
        <div class="muted" style="margin-top:6px">이메일을 입력하면 관리자 승인 후 재설정을 진행합니다.</div>
      </div>

      <div class="field">
        <div class="label">이메일</div>
        <input class="input" type="email" name="email" value="<?= e($email) ?>" placeholder="Email" required />
      </div>

      <button class="btn" type="submit" style="width:100%">재설정 링크 요청</button>

      <div class="auth-right-link" style="margin-top:10px">
        <a class="small" href="<?= e(url('/login.php')) ?>">로그인으로 돌아가기</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
