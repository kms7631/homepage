<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
  redirect('/index.php');
}

$email = '';

if (is_post()) {
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  try {
    $user = User::verifyLogin(db(), $email, $password);
    if (!$user) {
      flash_set('error', '이메일 또는 비밀번호가 올바르지 않습니다.');
    } else {
      auth_login($user);
      flash_set('success', '로그인 성공');
      redirect('/index.php');
    }
  } catch (PDOException $e) {
    error_log('[login] PDOException: ' . $e->getMessage());
    flash_set('error', 'DB 연결/쿼리 오류입니다. includes/config.php의 DB_NAME/DB_USER/DB_PASS를 확인하세요.');
  } catch (Throwable $e) {
    error_log('[login] Exception: ' . $e->getMessage());
    flash_set('error', '로그인 처리 중 오류가 발생했습니다.');
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-brand-outside"><?= e(APP_NAME) ?></div>
  <div class="card auth-card">
    <form method="post" action="<?= e(url('/login.php')) ?>" class="auth-form">
      <div class="field">
        <div class="label">이메일</div>
        <input class="input" type="email" name="email" value="<?= e($email) ?>" placeholder="Email" required />
      </div>

      <div class="field">
        <div class="label">비밀번호</div>
        <input class="input" type="password" name="password" required />
        <div class="auth-right-link">
          <a class="small" href="<?= e(url('/password_forgot.php')) ?>">비밀번호 찾기</a>
          <span class="muted" style="margin:0 6px">|</span>
          <a class="small" href="<?= e(url('/register.php')) ?>">회원가입</a>
        </div>
      </div>

      <button class="btn" type="submit" style="width:100%">로그인</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
