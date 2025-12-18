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
$phone = (string)($me['phone'] ?? '');
$phone1 = '';
$phone2 = '';
$phone3 = '';
$supplierId = (int)($me['supplier_id'] ?? 0);
$suppliers = Supplier::listAll($db);

if ($phone !== '') {
  $digits = preg_replace('/\D+/', '', $phone);
  $phone1 = substr($digits, 0, 3) ?: '';
  $phone2 = substr($digits, 3, 4) ?: '';
  $phone3 = substr($digits, 7, 4) ?: '';
}

if (is_post()) {
  $name = trim((string)($_POST['name'] ?? ''));
  $phone1 = trim((string)($_POST['phone1'] ?? ''));
  $phone2 = trim((string)($_POST['phone2'] ?? ''));
  $phone3 = trim((string)($_POST['phone3'] ?? ''));
  $digits1 = preg_replace('/\D+/', '', $phone1);
  $digits2 = preg_replace('/\D+/', '', $phone2);
  $digits3 = preg_replace('/\D+/', '', $phone3);
  if ($digits1 !== '' || $digits2 !== '' || $digits3 !== '') {
    $phone = $digits1 . '-' . $digits2 . '-' . $digits3;
  } else {
    $phone = '';
  }
  $supplierId = (int)($_POST['supplier_id'] ?? 0);
  $pw1 = (string)($_POST['password'] ?? '');
  $pw2 = (string)($_POST['password2'] ?? '');

  // 재렌더 시 값 유지
  $phone1 = $digits1;
  $phone2 = $digits2;
  $phone3 = $digits3;

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

    User::updateProfile($db, (int)$me['id'], $name, $sid, $newPassword, $phone);

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
      <div class="field" style="min-width:220px">
        <div class="label">연락처</div>
        <div style="display:flex; align-items:center; gap:8px">
          <input class="input" type="text" name="phone1" value="<?= e($phone1) ?>" inputmode="numeric" autocomplete="tel-area-code" maxlength="3" style="width:72px" placeholder="010" />
          <span class="muted">-</span>
          <input class="input" type="text" name="phone2" value="<?= e($phone2) ?>" inputmode="numeric" autocomplete="tel-local-prefix" maxlength="4" style="width:88px" placeholder="1234" />
          <span class="muted">-</span>
          <input class="input" type="text" name="phone3" value="<?= e($phone3) ?>" inputmode="numeric" autocomplete="tel-local-suffix" maxlength="4" style="width:88px" placeholder="5678" />
        </div>
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

<script>
  (function () {
    function onlyDigits(s) {
      return (s || '').replace(/\D+/g, '');
    }

    function setupPhoneSplit() {
      var a = document.querySelector('input[name="phone1"]');
      var b = document.querySelector('input[name="phone2"]');
      var c = document.querySelector('input[name="phone3"]');
      if (!a || !b || !c) return;

      var maxA = 3, maxB = 4, maxC = 4;
      var inputs = [a, b, c];
      var maxes = [maxA, maxB, maxC];

      function normalize(el) {
        var v = onlyDigits(el.value);
        if (el.name === 'phone1') v = v.slice(0, maxA);
        if (el.name === 'phone2') v = v.slice(0, maxB);
        if (el.name === 'phone3') v = v.slice(0, maxC);
        if (el.value !== v) el.value = v;
      }

      function maybeAdvance(idx) {
        if (idx < 0 || idx >= inputs.length) return;
        var el = inputs[idx];
        var max = maxes[idx];
        if (onlyDigits(el.value).length >= max && idx < inputs.length - 1) {
          inputs[idx + 1].focus();
          inputs[idx + 1].select();
        }
      }

      function maybeBack(idx, e) {
        if (e.key !== 'Backspace') return;
        var el = inputs[idx];
        if (el.value !== '') return;
        if (idx > 0) {
          inputs[idx - 1].focus();
        }
      }

      inputs.forEach(function (el, idx) {
        el.addEventListener('input', function () {
          normalize(el);
          maybeAdvance(idx);
        });
        el.addEventListener('keydown', function (e) {
          maybeBack(idx, e);
        });
        el.addEventListener('paste', function () {
          setTimeout(function () {
            normalize(el);
            maybeAdvance(idx);
          }, 0);
        });
      });
    }

    setupPhoneSplit();
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
