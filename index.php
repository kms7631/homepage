<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();

$scopeSupplierId = 0;
if (!is_admin()) {
  $scopeSupplierId = (int)(current_supplier_id() ?? 0);
}

function po_status_label(string $dbStatus): string {
  if ($dbStatus === 'RECEIVED') {
    return '입고';
  }
  if ($dbStatus === 'CANCELLED') {
    return '취소';
  }
  return '진행 중';
}

function po_status_badge_class(string $dbStatus): string {
  if ($dbStatus === 'OPEN') {
    return 'accent';
  }
  if ($dbStatus === 'RECEIVED') {
    return 'ok';
  }
  if ($dbStatus === 'CANCELLED') {
    return 'danger';
  }
  return '';
}

// KPI
$pendingPoCount = 0;
$todayReceiptCount = 0;
$todayReceiptQty = 0;
$lowStockCount = 0;
$today = date('Y-m-d');

// 1) 미처리 발주(OPEN)
if ($scopeSupplierId > 0) {
  $st = $db->prepare("SELECT COUNT(*) FROM purchase_orders po WHERE po.status='OPEN' AND po.supplier_id = ?");
  $st->execute([$scopeSupplierId]);
  $pendingPoCount = (int)$st->fetchColumn();
} else {
  $st = $db->query("SELECT COUNT(*) FROM purchase_orders po WHERE po.status='OPEN'");
  $pendingPoCount = (int)$st->fetchColumn();
}

// 2) 오늘 입고(건수 + 수량합)
if ($scopeSupplierId > 0) {
  $st = $db->prepare('SELECT COUNT(DISTINCT r.id) AS receipt_count, COALESCE(SUM(ri.qty_received),0) AS total_qty
                      FROM receipts r
                      LEFT JOIN receipt_items ri ON ri.receipt_id = r.id
                      WHERE r.receipt_date = ? AND r.supplier_id = ?');
  $st->execute([$today, $scopeSupplierId]);
} else {
  $st = $db->prepare('SELECT COUNT(DISTINCT r.id) AS receipt_count, COALESCE(SUM(ri.qty_received),0) AS total_qty
                      FROM receipts r
                      LEFT JOIN receipt_items ri ON ri.receipt_id = r.id
                      WHERE r.receipt_date = ?');
  $st->execute([$today]);
}
$row = $st->fetch() ?: [];
$todayReceiptCount = (int)($row['receipt_count'] ?? 0);
$todayReceiptQty = (int)($row['total_qty'] ?? 0);

// 3) 부족 품목 수(on_hand <= min_stock)
if ($scopeSupplierId > 0) {
  $st = $db->prepare('SELECT COUNT(*)
                      FROM items it
                      LEFT JOIN inventory inv ON inv.item_id = it.id
                      WHERE it.active = 1
                        AND COALESCE(inv.on_hand,0) <= it.min_stock
                        AND it.supplier_id = ?');
  $st->execute([$scopeSupplierId]);
  $lowStockCount = (int)$st->fetchColumn();
} else {
  $st = $db->query('SELECT COUNT(*)
                    FROM items it
                    LEFT JOIN inventory inv ON inv.item_id = it.id
                    WHERE it.active = 1
                      AND COALESCE(inv.on_hand,0) <= it.min_stock');
  $lowStockCount = (int)$st->fetchColumn();
}

$low = Inventory::lowStockTop($db, 5, $scopeSupplierId);
$recentPo = PurchaseOrder::latest($db, 5, $scopeSupplierId);
$recentRc = Receipt::latest($db, 5, $scopeSupplierId);

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid">
  <div class="col-4">
    <div class="kpi-card warn">
      <div class="kpi-title">미처리 발주</div>
      <div class="kpi-main"><?= e((string)$pendingPoCount) ?><span class="small" style="margin-left:6px;color:inherit">건</span></div>
    </div>
  </div>

  <div class="col-4">
    <div class="kpi-card ok">
      <div class="kpi-title">오늘 입고</div>
      <div class="kpi-main tight"><?= e((string)$todayReceiptCount) ?><span class="kpi-unit">건</span><span class="kpi-sep">/</span><?= e((string)$todayReceiptQty) ?><span class="kpi-unit">개</span></div>
    </div>
  </div>

  <div class="col-4">
    <div class="kpi-card danger">
      <div class="kpi-title">부족 품목</div>
      <div class="kpi-main"><?= e((string)$lowStockCount) ?><span class="small" style="margin-left:6px;color:inherit">개</span></div>
    </div>
  </div>

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
              <td>
                <?php $badgeClass = po_status_badge_class((string)($po['status'] ?? '')); ?>
                <span class="badge <?= e($badgeClass) ?>"><?= e(po_status_label((string)($po['status'] ?? ''))) ?></span>
              </td>
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
