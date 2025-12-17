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

<div class="grid">
  <div class="col-6">
    <div class="card">
      <h1 class="h1">로그인</h1>
      <form method="post" action="<?= e(url('/login.php')) ?>">
        <div class="form-row">
          <div class="field">
            <div class="label">이메일</div>
            <input class="input" type="email" name="email" value="<?= e($email) ?>" required />
          </div>
          <div class="field">
            <div class="label">비밀번호</div>
            <input class="input" type="password" name="password" required />
          </div>
          <div class="field">
            <button class="btn" type="submit">로그인</button>
          </div>
        </div>
        <div class="small" style="margin-top:10px">
          기본 관리자 계정: admin@example.com / password
        </div>
      </form>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h1 class="h1">회원가입</h1>
      <div style="margin-top:10px">
        <a class="btn secondary" href="<?= e(url('/register.php')) ?>">회원가입으로 이동</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
