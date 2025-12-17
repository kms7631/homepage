<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$db = db();
$u = current_user();
$me = User::findById($db, (int)$u['id']);
if (!$me) {
  auth_logout();
  flash_set('error', '사용자 정보를 찾을 수 없습니다. 다시 로그인하세요.');
  redirect('/login.php');
}

$name = (string)($me['name'] ?? '');
$supplierId = (int)($me['supplier_id'] ?? 0);
$suppliers = Supplier::listAll($db);

if (is_post()) {
  $name = trim((string)($_POST['name'] ?? ''));
  $supplierId = (int)($_POST['supplier_id'] ?? 0);
  $pw1 = (string)($_POST['password'] ?? '');
  $pw2 = (string)($_POST['password2'] ?? '');

  try {
    if ($name === '') {
      throw new RuntimeException('이름을 입력하세요.');
    }

    $newPassword = null;
    if ($pw1 !== '' || $pw2 !== '') {
      if ($pw1 === '' || $pw2 === '') {
        throw new RuntimeException('비밀번호 변경 시 비밀번호/확인을 모두 입력하세요.');
      }
      if ($pw1 !== $pw2) {
        throw new RuntimeException('비밀번호 확인이 일치하지 않습니다.');
      }
      $newPassword = $pw1;
    }

    // 일반 사용자는 거래처 소속 필수
    $sid = $supplierId > 0 ? $supplierId : null;
    if (!is_admin() && $sid === null) {
      throw new RuntimeException('일반 사용자는 거래처 소속을 지정해야 합니다.');
    }

    User::updateProfile($db, (int)$me['id'], $name, $sid, $newPassword);

    $fresh = User::findById($db, (int)$me['id']);
    if ($fresh) {
      auth_login($fresh);
    }

    flash_set('success', '프로필이 저장되었습니다.');
    redirect('/profile.php');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage() ?: '프로필 저장 중 오류가 발생했습니다.');
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">프로필</h1>
      <div class="muted">이름/비밀번호/거래처 소속을 수정할 수 있습니다.</div>
    </div>
  </div>

  <form method="post" action="<?= e(url('/profile.php')) ?>" style="margin-top:12px">
    <div class="form-row">
      <div class="field" style="min-width:260px">
        <div class="label">이메일</div>
        <input class="input" type="email" value="<?= e((string)$me['email']) ?>" readonly />
      </div>
      <div class="field" style="min-width:220px">
        <div class="label">이름</div>
        <input class="input" type="text" name="name" value="<?= e($name) ?>" required />
      </div>
      <div class="field" style="min-width:260px">
        <div class="label">소속 거래처</div>
        <select class="input" name="supplier_id" <?= is_admin() ? '' : 'required' ?>>
          <?php if (is_admin()): ?>
            <option value="0">(미지정)</option>
          <?php else: ?>
            <option value="0">(선택)</option>
          <?php endif; ?>
          <?php foreach ($suppliers as $sp): ?>
            <option value="<?= (int)$sp['id'] ?>" <?= ((int)$sp['id'] === (int)$supplierId) ? 'selected' : '' ?>>
              <?= e($sp['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row" style="margin-top:10px">
      <div class="field" style="min-width:220px">
        <div class="label">새 비밀번호(선택)</div>
        <input class="input" type="password" name="password" />
      </div>
      <div class="field" style="min-width:220px">
        <div class="label">새 비밀번호 확인(선택)</div>
        <input class="input" type="password" name="password2" />
      </div>
      <div class="field">
        <button class="btn" type="submit">저장</button>
      </div>
    </div>
  </form>

  <?php if (!is_admin()): ?>
    <div class="small muted" style="margin-top:10px">
      거래처 소속이 없으면 발주/입고/품목 화면이 제한됩니다.
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
