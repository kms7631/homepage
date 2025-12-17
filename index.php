<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();

$scopeSupplierId = 0;
if (!is_admin()) {
  $scopeSupplierId = (int)(current_supplier_id() ?? 0);
}

$low = Inventory::lowStockTop($db, 5, $scopeSupplierId);
$recentPo = PurchaseOrder::latest($db, 5, $scopeSupplierId);
$recentRc = Receipt::latest($db, 5, $scopeSupplierId);

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card">
      <div class="kpi">
        <div>
          <h1 class="h1" style="margin-bottom:4px">작업 순서</h1>
          <div class="muted">부족 품목 → 발주 → 입고 → 재고 반영 흐름을 확인하세요.</div>
        </div>
        <div>
          <a class="btn" href="<?= e(url('/po_create.php')) ?>">발주 등록</a>
          <a class="btn secondary" href="<?= e(url('/receipt_create.php')) ?>">입고 처리</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h2 class="h1">부족 품목 TOP 5</h2>
      <div class="small">기준: 재고(on_hand) ≤ 안전재고(min_stock)</div>
      <div style="margin-top:10px"></div>
      <table class="table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>품목</th>
            <th>재고</th>
            <th>안전재고</th>
            <th>부족</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$low): ?>
          <tr><td colspan="5" class="muted">부족 품목이 없습니다.</td></tr>
        <?php else: ?>
          <?php foreach ($low as $r): ?>
            <tr>
              <td><?= e($r['sku']) ?></td>
              <td><a href="<?= e(url('/item_view.php?id=' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a></td>
              <td><?= e((string)$r['on_hand']) ?></td>
              <td><?= e((string)$r['min_stock']) ?></td>
              <td><span class="badge danger"><?= e((string)$r['shortage']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h2 class="h1">최근 발주 5건</h2>
      <table class="table">
        <thead>
          <tr>
            <th>발주번호</th>
            <th>거래처</th>
            <th>일자</th>
            <th>상태</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$recentPo): ?>
          <tr><td colspan="4" class="muted">발주가 없습니다.</td></tr>
        <?php else: ?>
          <?php foreach ($recentPo as $po): ?>
            <tr>
              <td><a href="<?= e(url('/po_view.php?id=' . (int)$po['id'])) ?>"><?= e($po['po_no']) ?></a></td>
              <td><?= e($po['supplier_name']) ?></td>
              <td><?= e($po['order_date']) ?></td>
              <td><span class="badge"><?= e($po['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div style="height:14px"></div>

    <div class="card">
      <h2 class="h1">최근 입고 5건</h2>
      <table class="table">
        <thead>
          <tr>
            <th>입고번호</th>
            <th>거래처</th>
            <th>일자</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$recentRc): ?>
          <tr><td colspan="3" class="muted">입고가 없습니다.</td></tr>
        <?php else: ?>
          <?php foreach ($recentRc as $rc): ?>
            <tr>
              <td><a href="<?= e(url('/receipt_view.php?id=' . (int)$rc['id'])) ?>"><?= e($rc['receipt_no']) ?></a></td>
              <td><?= e($rc['supplier_name']) ?></td>
              <td><?= e($rc['receipt_date']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
