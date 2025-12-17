<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('error', '잘못된 접근입니다.');
  redirect('/items.php');
}

$db = db();
$item = Item::find($db, $id);
if (!$item) {
  flash_set('error', '품목을 찾을 수 없습니다.');
  redirect('/items.php');
}

if (!is_admin()) {
  $sid = (int)(current_supplier_id() ?? 0);
  if ($sid > 0 && (int)($item['supplier_id'] ?? 0) !== $sid) {
    flash_set('error', '접근 권한이 없습니다.');
    redirect('/items.php');
  }
}

$isLow = ((int)$item['on_hand'] <= (int)$item['min_stock']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card">
      <div class="kpi">
        <div>
          <h1 class="h1" style="margin-bottom:4px">품목 상세</h1>
          <div class="muted">재고/안전재고 기준으로 부족 여부를 표시합니다.</div>
        </div>
        <div>
          <a class="btn secondary" href="<?= e(url('/items.php')) ?>">목록</a>
          <a class="btn" href="<?= e(url('/po_create.php?supplier_id=' . (int)($item['supplier_id'] ?? 0))) ?>">해당 거래처 발주</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h2 class="h1">기본 정보</h2>
      <table class="table">
        <tbody>
          <tr><th>SKU</th><td><?= e($item['sku']) ?></td></tr>
          <tr><th>품목명</th><td><?= e($item['name']) ?></td></tr>
          <tr><th>거래처</th><td><?= e($item['supplier_name'] ?? '-') ?></td></tr>
          <tr><th>단위</th><td><?= e($item['unit']) ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h2 class="h1">재고 상태</h2>
      <table class="table">
        <tbody>
          <tr><th>현재 재고(on_hand)</th><td><?= e((string)$item['on_hand']) ?></td></tr>
          <tr><th>안전재고(min_stock)</th><td><?= e((string)$item['min_stock']) ?></td></tr>
          <tr>
            <th>부족 여부</th>
            <td>
              <?php if ($isLow): ?>
                <span class="badge danger">부족</span>
              <?php else: ?>
                <span class="badge ok">정상</span>
              <?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>
      <?php if ($isLow): ?>
        <div class="flash error" style="margin-top:10px">
          안전재고 이하입니다. 발주 등록 후 입고 처리로 재고 반영을 확인하세요.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
