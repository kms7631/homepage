<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$db = db();

if (is_post()) {
  try {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $onHand = (int)($_POST['on_hand'] ?? 0);
    if ($itemId <= 0) {
      throw new RuntimeException('품목을 선택하세요.');
    }
    Inventory::setOnHand($db, $itemId, $onHand);
    flash_set('success', '재고가 수정되었습니다.');
    redirect('/admin/inventory.php');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

$items = Item::list($db, ['q' => '', 'supplier_id' => 0]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h1 class="h1">재고관리</h1>
  <div class="muted">운영 편의를 위해 재고를 직접 수정할 수 있습니다.</div>

  <div style="margin-top:12px"></div>

  <form method="post" action="<?= e(url('/admin/inventory.php')) ?>">
    <div class="form-row">
      <div class="field" style="min-width:340px">
        <div class="label">품목</div>
        <select name="item_id" required>
          <option value="">선택...</option>
          <?php foreach ($items as $it): ?>
            <option value="<?= e((string)$it['id']) ?>"><?= e($it['sku'] . ' · ' . $it['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <div class="label">재고(on_hand)</div>
        <input class="input" type="number" name="on_hand" min="0" value="0" required />
      </div>
      <div class="field">
        <button class="btn" type="submit">수정</button>
      </div>
      <div class="field">
        <a class="btn secondary" href="<?= e(url('/admin/suppliers.php')) ?>">거래처</a>
        <a class="btn secondary" href="<?= e(url('/admin/items.php')) ?>">품목</a>
        <a class="btn secondary" href="<?= e(url('/admin/users.php')) ?>">사용자</a>
      </div>
    </div>
  </form>

  <div style="margin-top:12px"></div>

  <table class="table">
    <thead>
      <tr>
        <th>SKU</th>
        <th>품목</th>
        <th>거래처</th>
        <th>재고</th>
        <th>안전재고</th>
        <th>부족</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <?php $isLow = ((int)$it['on_hand'] <= (int)$it['min_stock']); ?>
        <tr>
          <td><?= e($it['sku']) ?></td>
          <td><?= e($it['name']) ?></td>
          <td><?= e($it['supplier_name'] ?? '-') ?></td>
          <td><?= e((string)$it['on_hand']) ?></td>
          <td><?= e((string)$it['min_stock']) ?></td>
          <td><?= $isLow ? '<span class="badge danger">부족</span>' : '<span class="badge ok">정상</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
