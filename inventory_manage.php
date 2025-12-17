<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();

$supplierId = (int)($_GET['supplier_id'] ?? ($_POST['supplier_id'] ?? 0));
if (!is_admin()) {
  $supplierId = (int)(current_supplier_id() ?? 0);
}

$suppliers = is_admin() ? Supplier::listAll($db) : [];

if (is_post()) {
  try {
    if ($supplierId <= 0) {
      throw new RuntimeException('거래처를 선택하세요.');
    }

    // 허용된 품목(해당 거래처)만 가져와서, 그 안에서만 업데이트
    $allowed = Item::listBySupplier($db, $supplierId);
    $allowedIds = [];
    foreach ($allowed as $it) {
      $allowedIds[(int)$it['id']] = true;
    }

    $onHands = $_POST['on_hand'] ?? [];
    if (!is_array($onHands)) {
      throw new RuntimeException('재고 정보가 올바르지 않습니다.');
    }

    $db->beginTransaction();
    try {
      foreach ($onHands as $itemIdStr => $val) {
        $itemId = (int)$itemIdStr;
        if ($itemId <= 0 || !isset($allowedIds[$itemId])) {
          continue;
        }
        $onHand = (int)$val;
        if ($onHand < 0) {
          throw new RuntimeException('재고는 0 이상이어야 합니다.');
        }
        Inventory::setOnHand($db, $itemId, $onHand);
      }
      $db->commit();
    } catch (Throwable $e) {
      $db->rollBack();
      throw $e;
    }

    flash_set('success', '재고가 저장되었습니다.');
    redirect('/inventory_manage.php' . (is_admin() ? ('?supplier_id=' . $supplierId) : ''));
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

$items = [];
if ($supplierId > 0) {
  $items = Item::listBySupplier($db, $supplierId);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">재고 수정</h1>
      <div class="muted">
        <?= is_admin() ? '선택한 거래처 품목의 재고를 직접 수정할 수 있습니다.' : '내 거래처 품목의 재고를 직접 수정할 수 있습니다.' ?>
      </div>
    </div>
    <div>
      <a class="btn secondary" href="<?= e(url('/items.php')) ?>">품목으로</a>
    </div>
  </div>

  <?php if (is_admin()): ?>
    <form method="get" action="<?= e(url('/inventory_manage.php')) ?>" style="margin-top:12px">
      <div class="form-row">
        <div class="field" style="min-width:280px">
          <div class="label">거래처</div>
          <select name="supplier_id" required>
            <option value="0">선택...</option>
            <?php foreach ($suppliers as $sp): ?>
              <option value="<?= e((string)$sp['id']) ?>" <?= $supplierId === (int)$sp['id'] ? 'selected' : '' ?>>
                <?= e($sp['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <button class="btn" type="submit">불러오기</button>
        </div>
      </div>
    </form>
  <?php endif; ?>

  <div style="margin-top:12px"></div>

  <?php if ($supplierId <= 0): ?>
    <div class="muted">거래처를 선택하면 품목 재고 수정 화면이 표시됩니다.</div>
  <?php elseif (!$items): ?>
    <div class="muted">수정할 품목이 없습니다.</div>
  <?php else: ?>
    <form method="post" action="<?= e(url('/inventory_manage.php')) ?>">
      <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />

      <table class="table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>품목</th>
            <th>현재 재고</th>
            <th>안전재고</th>
            <th>단위</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <?php $id = (int)$it['id']; ?>
            <tr>
              <td><?= e($it['sku']) ?></td>
              <td><?= e($it['name']) ?></td>
              <td style="max-width:200px">
                <input class="input" type="number" min="0" name="on_hand[<?= e((string)$id) ?>]" value="<?= e((string)(int)$it['on_hand']) ?>" />
              </td>
              <td><?= e((string)(int)$it['min_stock']) ?></td>
              <td><?= e($it['unit']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top:10px">
        <button class="btn" type="submit">저장</button>
      </div>

      <div class="small" style="margin-top:10px">입고 처리는 별도 메뉴에서 진행하며, 여기서는 현재 재고값을 직접 수정합니다.</div>
    </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
