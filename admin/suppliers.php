<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$db = db();

$editId = (int)($_GET['edit_id'] ?? 0);
$editSupplier = null;
if ($editId > 0) {
  $editSupplier = Supplier::find($db, $editId);
  if (!$editSupplier) {
    flash_set('error', '수정할 거래처를 찾을 수 없습니다.');
    redirect('/admin/suppliers.php');
  }
}

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? 'create'));

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        throw new RuntimeException('삭제할 거래처가 올바르지 않습니다.');
      }
      Supplier::delete($db, $id);
      flash_set('success', '거래처가 삭제되었습니다.');
      redirect('/admin/suppliers.php');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
      throw new RuntimeException('거래처명을 입력하세요.');
    }

    if ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        throw new RuntimeException('수정할 거래처가 올바르지 않습니다.');
      }
      Supplier::update($db, $id, $_POST);
      flash_set('success', '거래처가 수정되었습니다.');
      redirect('/admin/suppliers.php');
    }

    // create
    // 과거 파라미터(create_demo_items)와 신규 파라미터(create_initial_items) 모두 허용(호환)
    $createInitial = (string)($_POST['create_initial_items'] ?? '') === '1'
      || (string)($_POST['create_demo_items'] ?? '') === '1';
    $db->beginTransaction();
    try {
      $newId = Supplier::create($db, $_POST);
      if ($createInitial) {
        Supplier::createInitialItems($db, $newId, 6);
      }
      $db->commit();
    } catch (Throwable $e) {
      $db->rollBack();
      throw $e;
    }
    flash_set('success', '거래처가 추가되었습니다.');
    redirect('/admin/suppliers.php');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

$suppliers = Supplier::listAll($db);

// 거래처별 사용자 목록(한 번에 조회해서 그룹핑)
$supplierUsers = [];
try {
  try {
    $stUsers = $db->query("SELECT id, email, name, phone, role, supplier_id FROM users WHERE supplier_id IS NOT NULL ORDER BY supplier_id ASC, id ASC");
    $rows = $stUsers->fetchAll();
  } catch (PDOException $e) {
    // 구버전 스키마(users.phone 없음) 호환
    $stUsers = $db->query("SELECT id, email, name, role, supplier_id FROM users WHERE supplier_id IS NOT NULL ORDER BY supplier_id ASC, id ASC");
    $rows = $stUsers->fetchAll();
    foreach ($rows as &$r) {
      $r['phone'] = null;
    }
    unset($r);
  }
  foreach ($rows as $u) {
    $sid = (int)($u['supplier_id'] ?? 0);
    if ($sid <= 0) continue;
    if (!isset($supplierUsers[$sid])) {
      $supplierUsers[$sid] = [];
    }
    $supplierUsers[$sid][] = $u;
  }
} catch (Throwable $e) {
  $supplierUsers = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card">
      <h1 class="h1"><?= $editSupplier ? '거래처 수정' : '거래처 추가' ?></h1>
      <form method="post" action="<?= e(url('/admin/suppliers.php')) ?>">
        <input type="hidden" name="action" value="<?= $editSupplier ? 'update' : 'create' ?>" />
        <?php if ($editSupplier): ?>
          <input type="hidden" name="id" value="<?= e((string)$editSupplier['id']) ?>" />
        <?php endif; ?>
        <div class="form-row">
          <div class="field" style="min-width:240px">
            <div class="label">거래처명</div>
            <input class="input" type="text" name="name" value="<?= e((string)($editSupplier['name'] ?? '')) ?>" required />
          </div>
          <div class="field">
            <div class="label">담당자</div>
            <input class="input" type="text" name="contact_name" value="<?= e((string)($editSupplier['contact_name'] ?? '')) ?>" />
          </div>
          <div class="field">
            <div class="label">연락처</div>
            <input class="input" type="text" name="phone" value="<?= e((string)($editSupplier['phone'] ?? '')) ?>" />
          </div>
          <div class="field">
            <div class="label">이메일</div>
            <input class="input" type="email" name="email" value="<?= e((string)($editSupplier['email'] ?? '')) ?>" />
          </div>
        </div>
        <div style="margin-top:10px">
          <?php if (!$editSupplier): ?>
            <label class="small" style="display:block;margin-bottom:8px">
              <input type="checkbox" name="create_initial_items" value="1" checked />
              기본 품목/재고 자동 생성(여유/부족 랜덤)
            </label>
          <?php endif; ?>
          <button class="btn" type="submit"><?= $editSupplier ? '저장' : '추가' ?></button>
          <?php if ($editSupplier): ?>
            <a class="btn secondary" href="<?= e(url('/admin/suppliers.php')) ?>">취소</a>
          <?php endif; ?>
          <a class="btn secondary" href="<?= e(url('/admin/items.php')) ?>">품목 관리</a>
          <a class="btn secondary" href="<?= e(url('/admin/inventory.php')) ?>">재고 관리</a>
          <a class="btn secondary" href="<?= e(url('/admin/users.php')) ?>">사용자</a>
        </div>
      </form>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <h1 class="h1">거래처 정보</h1>
      <div class="table-scroll">
      <table class="table nowrap">
        <thead>
          <tr>
            <th>거래처</th>
            <th>담당/사용자</th>
            <th>연락처</th>
            <th>이메일</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($suppliers as $sp): ?>
            <?php
              $spId = (int)$sp['id'];
              $users = $supplierUsers[$spId] ?? [];
              $groupId = 'supusers-' . $spId;
            ?>
            <tr>
              <td>
                <?php if ($users): ?>
                  <button
                    type="button"
                    class="table-toggle"
                    data-toggle-group="<?= e($groupId) ?>"
                    aria-expanded="false"
                  >
                    <span class="table-toggle-label"><?= e($sp['name']) ?></span>
                    <span class="badge" style="margin-left:8px">사용자 <?= e((string)count($users)) ?></span>
                    <span class="table-toggle-chevron" aria-hidden="true"></span>
                  </button>
                <?php else: ?>
                  <?= e($sp['name']) ?>
                <?php endif; ?>
              </td>
              <td><?= e($sp['contact_name'] ?: '-') ?></td>
              <td><?= e($sp['phone'] ?: '-') ?></td>
              <td><?= e($sp['email'] ?: '-') ?></td>
              <td style="white-space:nowrap">
                <a class="btn secondary" href="<?= e(url('/admin/suppliers.php?edit_id=' . (int)$sp['id'])) ?>">수정</a>
                <form method="post" action="<?= e(url('/admin/suppliers.php')) ?>" style="display:inline" onsubmit="return confirm('삭제하시겠습니까?')">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= e((string)$sp['id']) ?>" />
                  <button class="btn secondary" type="submit">삭제</button>
                </form>
              </td>
            </tr>

            <?php if ($users): ?>
              <?php foreach ($users as $u): ?>
                <tr class="child-row" data-group="<?= e($groupId) ?>" hidden>
                  <td class="child-first"></td>
                  <td>
                    <?= e($u['name']) ?>
                    <span class="badge" style="margin-left:6px"><?= e($u['role']) ?></span>
                  </td>
                  <td><?= e(((string)($u['phone'] ?? '')) ?: '-') ?></td>
                  <td><?= e($u['email']) ?></td>
                  <td></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    function setGroupVisible(groupId, visible) {
      var rows = document.querySelectorAll('tr.child-row[data-group="' + groupId + '"]');
      for (var i = 0; i < rows.length; i++) {
        if (visible) {
          rows[i].removeAttribute('hidden');
        } else {
          rows[i].setAttribute('hidden', 'hidden');
        }
      }
    }

    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-toggle-group]') : null;
      if (!btn) return;

      var groupId = btn.getAttribute('data-toggle-group');
      if (!groupId) return;

      var expanded = btn.getAttribute('aria-expanded') === 'true';
      var next = !expanded;
      btn.setAttribute('aria-expanded', next ? 'true' : 'false');
      setGroupVisible(groupId, next);
    });
  })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
