<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('error', '잘못된 접근입니다.');
  redirect('/receipt_list.php');
}

$db = db();
$rc = Receipt::find($db, $id);
if (!$rc) {
  flash_set('error', '입고 내역을 찾을 수 없습니다.');
  redirect('/receipt_list.php');
}

if (!is_admin()) {
  $sid = (int)(current_supplier_id() ?? 0);
  if ($sid > 0 && (int)($rc['supplier_id'] ?? 0) !== $sid) {
    flash_set('error', '접근 권한이 없습니다.');
    redirect('/receipt_list.php');
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card">
      <div class="kpi">
        <div>
          <h1 class="h1" style="margin-bottom:4px">입고 상세</h1>
          <div class="muted"><?= e($rc['receipt_no']) ?> · <?= e($rc['supplier_name']) ?></div>
        </div>
        <div>
          <a class="btn secondary" href="<?= e(url('/receipt_list.php')) ?>">목록</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h2 class="h1">헤더</h2>
      <table class="table">
        <tbody>
          <tr><th>입고번호</th><td><?= e($rc['receipt_no']) ?></td></tr>
          <tr><th>거래처</th><td><?= e($rc['supplier_name']) ?></td></tr>
          <tr><th>입고일</th><td><?= e($rc['receipt_date']) ?></td></tr>
          <tr><th>처리자</th><td><?= e($rc['received_by_name']) ?></td></tr>
          <tr><th>발주 연결</th><td><?= $rc['purchase_order_id'] ? e('PO#' . (int)$rc['purchase_order_id']) : '-' ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h2 class="h1">메모</h2>
      <div class="muted"><?= e($rc['notes'] ?: '-') ?></div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <h2 class="h1">입고 품목</h2>
      <table class="table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>품목</th>
            <th>수량</th>
            <th>단위</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($rc['items'] ?? []) as $row): ?>
            <tr>
              <td><?= e($row['sku']) ?></td>
              <td><?= e($row['name']) ?></td>
              <td><?= e((string)$row['qty_received']) ?></td>
              <td><?= e($row['unit']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="small" style="margin-top:10px">입고 처리 시 재고(inventory)가 트랜잭션으로 즉시 증가합니다.</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
