<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$supplierId = 0;
if (!is_admin()) {
  $supplierId = (int)(current_supplier_id() ?? 0);
}

$rows = Receipt::list($db, ['from' => $from, 'to' => $to, 'supplier_id' => $supplierId]);

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">입고 조회</h1>
      <div class="muted">기간 필터로 입고 내역을 확인합니다.</div>
    </div>
    <div>
      <a class="btn" href="<?= e(url('/receipt_create.php')) ?>">입고 처리</a>
    </div>
  </div>

  <form method="get" action="<?= e(url('/receipt_list.php')) ?>" style="margin-top:12px">
    <div class="form-row">
      <div class="field">
        <div class="label">기간(From)</div>
        <input class="input" type="date" name="from" value="<?= e($from) ?>" />
      </div>
      <div class="field">
        <div class="label">기간(To)</div>
        <input class="input" type="date" name="to" value="<?= e($to) ?>" />
      </div>
      <div class="field">
        <button class="btn" type="submit">검색</button>
      </div>
    </div>
  </form>

  <div style="margin-top:12px"></div>

  <table class="table">
    <thead>
      <tr>
        <th>입고번호</th>
        <th>거래처</th>
        <th>일자</th>
        <th>발주 연결</th>
        <th>처리자</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="5" class="muted">검색 결과가 없습니다.</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= e(url('/receipt_view.php?id=' . (int)$r['id'])) ?>"><?= e($r['receipt_no']) ?></a></td>
          <td><?= e($r['supplier_name']) ?></td>
          <td><?= e($r['receipt_date']) ?></td>
          <td><?= $r['purchase_order_id'] ? e('PO#' . (int)$r['purchase_order_id']) : '-' ?></td>
          <td><?= e($r['received_by_name']) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
