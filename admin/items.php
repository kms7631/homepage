<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$db = db();

$editId = (int)($_GET['edit_id'] ?? 0);
$editItem = null;
if ($editId > 0) {
  $editItem = Item::find($db, $editId);
  if (!$editItem) {
    flash_set('error', '수정할 품목을 찾을 수 없습니다.');
    redirect('/admin/items.php');
  }
}

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? 'create'));

    if ($action === 'delete_sample' || $action === 'delete_demo') {
      $db->beginTransaction();
      try {
        $deleted = Item::deleteSampleItems($db);
        $db->commit();
      } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
      }
      flash_set('success', '샘플 품목이 정리되었습니다. (삭제: ' . $deleted . '건)');
      redirect('/admin/items.php');
    }

    if ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        throw new RuntimeException('수정할 품목이 올바르지 않습니다.');
      }
      $name = trim((string)($_POST['name'] ?? ''));
      if ($name === '') {
        throw new RuntimeException('품목명을 입력하세요.');
      }
      Item::update($db, $id, $_POST);
      flash_set('success', '품목이 수정되었습니다.');
      redirect('/admin/items.php');
    }

    // create
    $sku = trim((string)($_POST['sku'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    if ($sku === '' || $name === '') {
      throw new RuntimeException('SKU/품목명을 입력하세요.');
    }
    $db->beginTransaction();
    try {
      Item::create($db, $_POST);
      $db->commit();
    } catch (Throwable $e) {
      $db->rollBack();
      throw $e;
    }
    flash_set('success', '품목이 추가되었습니다.');
    redirect('/admin/items.php');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

$suppliers = Supplier::listAll($db);
$items = Item::list($db, ['q' => '', 'supplier_id' => 0]);

// 동일 품목명이 여러 거래처에 존재할 수 있어, 화면에서는 품목명 기준으로 묶어서 표시합니다.
// (첫 줄은 그대로, 나머지는 화살표로 펼쳐보기)
$groupedItems = [];
foreach ($items as $it) {
  $key = trim((string)($it['name'] ?? ''));
  if ($key === '') {
    $key = (string)($it['sku'] ?? '');
  }
  if (!isset($groupedItems[$key])) {
    $groupedItems[$key] = [];
  }
  $groupedItems[$key][] = $it;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card">
      <h1 class="h1">관리자 · 품목</h1>
      <div class="small">품목 추가 시 inventory에 0으로 초기화됩니다.</div>
      <div style="margin-top:10px"></div>
      <table class="table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>품목</th>
            <th>거래처</th>
            <th>재고</th>
            <th>안전재고</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groupedItems as $nameKey => $group): ?>
            <?php
              $it = $group[0];
              $dupes = array_slice($group, 1);
              $groupId = substr(sha1('itemgrp:' . (string)$nameKey), 0, 12);
            ?>
            <tr>
              <td><?= e($it['sku']) ?></td>
              <td>
                <?php if ($dupes): ?>
                  <button
                    type="button"
                    class="table-toggle"
                    data-toggle-group="<?= e($groupId) ?>"
                    aria-expanded="false"
                  >
                    <span class="table-toggle-label"><?= e($it['name']) ?></span>
                    <span class="badge" style="margin-left:8px">추가 <?= e((string)count($dupes)) ?></span>
                    <span class="table-toggle-chevron" aria-hidden="true"></span>
                  </button>
                <?php else: ?>
                  <?= e($it['name']) ?>
                <?php endif; ?>
              </td>
              <td><?= e($it['supplier_name'] ?? '-') ?></td>
              <td><?= e((string)$it['on_hand']) ?></td>
              <td><?= e((string)$it['min_stock']) ?></td>
              <td style="white-space:nowrap">
                <a class="btn secondary" href="<?= e(url('/admin/items.php?edit_id=' . (int)$it['id'])) ?>">수정</a>
              </td>
            </tr>

            <?php if ($dupes): ?>
              <?php foreach ($dupes as $d): ?>
                <tr class="dup-row" data-group="<?= e($groupId) ?>" hidden>
                  <td class="dup-sku"><?= e($d['sku']) ?></td>
                  <td></td>
                  <td><?= e($d['supplier_name'] ?? '-') ?></td>
                  <td><?= e((string)$d['on_hand']) ?></td>
                  <td><?= e((string)$d['min_stock']) ?></td>
                  <td style="white-space:nowrap">
                    <a class="btn secondary" href="<?= e(url('/admin/items.php?edit_id=' . (int)$d['id'])) ?>">수정</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top:14px"></div>

      <form method="post" action="<?= e(url('/admin/items.php')) ?>" onsubmit="return confirm('샘플 품목(\'샘플 품목 \' 또는 과거 패턴으로 시작) 중 발주/입고에 연결되지 않은 항목을 정리합니다. 진행할까요?')">
        <input type="hidden" name="action" value="delete_sample" />
        <button class="btn secondary" type="submit">샘플 품목 정리</button>
        <span class="small muted" style="margin-left:8px">발주/입고에 연결된 품목은 삭제되지 않습니다.</span>
      </form>

      <h2 class="h1"><?= $editItem ? '품목 수정' : '품목 추가' ?></h2>
      <form method="post" action="<?= e(url('/admin/items.php')) ?>">
        <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create' ?>" />
        <?php if ($editItem): ?>
          <input type="hidden" name="id" value="<?= e((string)$editItem['id']) ?>" />
        <?php endif; ?>
        <div class="form-row">
          <div class="field">
            <div class="label">SKU</div>
            <input class="input" type="text" name="sku" value="<?= e((string)($editItem['sku'] ?? '')) ?>" <?= $editItem ? 'readonly' : 'required' ?> />
          </div>
          <div class="field" style="min-width:260px">
            <div class="label">품목명</div>
            <input class="input" type="text" name="name" value="<?= e((string)($editItem['name'] ?? '')) ?>" required />
          </div>
          <div class="field">
            <div class="label">거래처</div>
            <select name="supplier_id">
              <option value="0">미지정</option>
              <?php foreach ($suppliers as $sp): ?>
                <option value="<?= e((string)$sp['id']) ?>" <?= $editItem && (int)$editItem['supplier_id'] === (int)$sp['id'] ? 'selected' : '' ?>><?= e($sp['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <div class="label">단위</div>
            <input class="input" type="text" name="unit" value="<?= e((string)($editItem['unit'] ?? 'EA')) ?>" />
          </div>
          <div class="field">
            <div class="label">안전재고</div>
            <input class="input" type="number" min="0" name="min_stock" value="<?= e((string)(int)($editItem['min_stock'] ?? 0)) ?>" />
          </div>
          <div class="field">
            <button class="btn" type="submit"><?= $editItem ? '저장' : '추가' ?></button>
          </div>
        </div>
        <div style="margin-top:10px">
          <?php if ($editItem): ?>
            <a class="btn secondary" href="<?= e(url('/admin/items.php')) ?>">취소</a>
          <?php endif; ?>
          <a class="btn secondary" href="<?= e(url('/admin/suppliers.php')) ?>">거래처</a>
          <a class="btn secondary" href="<?= e(url('/admin/inventory.php')) ?>">재고</a>
          <a class="btn secondary" href="<?= e(url('/admin/users.php')) ?>">사용자</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
  (function () {
    function setGroupVisible(groupId, visible) {
      var rows = document.querySelectorAll('tr.dup-row[data-group="' + groupId + '"]');
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
