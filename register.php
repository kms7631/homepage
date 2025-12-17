<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
  redirect('/index.php');
}

$email = '';
$name = '';
$supplierId = 0;
$newSupplierName = '';

try {
  $db = db();
  $suppliers = Supplier::listAll($db);
} catch (Throwable $e) {
  $suppliers = [];
}

if (is_post()) {
  $email = trim((string)($_POST['email'] ?? ''));
  $name = trim((string)($_POST['name'] ?? ''));
  $supplierId = (int)($_POST['supplier_id'] ?? 0);
  $newSupplierName = trim((string)($_POST['new_supplier_name'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $password2 = (string)($_POST['password2'] ?? '');

  if ($email === '' || $name === '' || $password === '') {
    flash_set('error', '필수 항목을 입력하세요.');
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', '이메일 형식이 올바르지 않습니다.');
  } elseif ($password !== $password2) {
    flash_set('error', '비밀번호 확인이 일치하지 않습니다.');
  } else {
    try {
      $db = db();
      if (User::findByEmail($db, $email)) {
        flash_set('error', '이미 등록된 이메일입니다.');
      } else {
        // 거래처 소속 지정: 기존 선택 또는 신규 생성
        $createdNewSupplier = false;
        $db->beginTransaction();
        try {
          $finalSupplierId = $supplierId;
          if ($finalSupplierId <= 0) {
            if ($newSupplierName === '') {
              throw new RuntimeException('거래처를 선택하거나, 신규 거래처명을 입력하세요.');
            }
            $finalSupplierId = Supplier::create($db, ['name' => $newSupplierName]);
            $createdNewSupplier = true;
          }

          $userId = User::createWithSupplier($db, $email, $name, $password, $finalSupplierId);
          $db->commit();
        } catch (Throwable $e) {
          $db->rollBack();
          throw $e;
        }

        $u = User::findById($db, $userId);
        if ($u) {
          auth_login($u);
        }
        flash_set('success', '회원가입이 완료되었습니다.');
        if ($createdNewSupplier) {
          $_SESSION['onboarding_supplier_id'] = (int)($u['supplier_id'] ?? 0);
          redirect('/supplier_items_setup.php');
        }
        redirect('/index.php');
      }
    } catch (PDOException $e) {
      $msg = $e->getMessage();
      error_log('[register] PDOException: ' . $msg);

      // 자주 나오는 케이스를 사용자 친화적으로 분기
      if (stripos($msg, 'Access denied') !== false) {
        flash_set('error', 'DB 인증 실패입니다. includes/config.php의 DB_USER/DB_PASS가 실제 MySQL 계정과 일치하는지 확인하세요.');
      } elseif (stripos($msg, 'Unknown column') !== false || stripos($msg, 'supplier_id') !== false) {
        flash_set('error', 'DB 스키마가 최신이 아닙니다. migrate_add_supplier_id.sql(또는 schema.sql 재적용)을 먼저 실행하세요.');
      } elseif (stripos($msg, 'Unknown database') !== false) {
        flash_set('error', 'DB_NAME이 존재하지 않습니다. MySQL에서 DB 생성 후 includes/config.php의 DB_NAME을 확인하세요.');
      } else {
        flash_set('error', 'DB 연결/쿼리 오류입니다. includes/config.php의 DB_NAME/DB_USER/DB_PASS를 확인하세요.');
      }
    } catch (Throwable $e) {
      error_log('[register] Exception: ' . $e->getMessage());
      flash_set('error', $e->getMessage() ?: '회원가입 처리 중 오류가 발생했습니다.');
    }
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <h1 class="h1">회원가입</h1>
  <form method="post" action="<?= e(url('/register.php')) ?>">
    <div class="form-row">
      <div class="field">
        <div class="label">이메일</div>
        <input class="input" type="email" name="email" value="<?= e($email) ?>" required />
      </div>
      <div class="field">
        <div class="label">이름</div>
        <input class="input" type="text" name="name" value="<?= e($name) ?>" required />
      </div>
      <div class="field">
        <div class="label">비밀번호</div>
        <input class="input" type="password" name="password" required />
      </div>
      <div class="field">
        <div class="label">비밀번호 확인</div>
        <input class="input" type="password" name="password2" required />
      </div>

      <div class="field" style="min-width:240px">
        <div class="label">소속 거래처</div>
        <select class="input" name="supplier_id">
          <option value="0">(신규 거래처 생성)</option>
          <?php foreach ($suppliers as $sp): ?>
            <option value="<?= (int)$sp['id'] ?>" <?= ((int)$sp['id'] === (int)$supplierId) ? 'selected' : '' ?>>
              <?= e($sp['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="small muted" style="margin-top:6px">기존 거래처를 선택하거나, 아래에 신규 거래처명을 입력하세요.</div>
      </div>

      <div class="field" style="min-width:240px">
        <div class="label">신규 거래처명(선택)</div>
        <input class="input" type="text" name="new_supplier_name" value="<?= e($newSupplierName) ?>" placeholder="예: (주)샘플상사" />
        <div class="small muted" style="margin-top:6px">신규 거래처 생성 후, 부족 품목을 직접 등록합니다.</div>
      </div>

      <div class="field">
        <button class="btn" type="submit">가입</button>
      </div>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
