<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('error', '잘못된 접근입니다.');
  redirect('/po_list.php');
}

$db = db();
$po = PurchaseOrder::find($db, $id);
if (!$po) {
  flash_set('error', '발주를 찾을 수 없습니다.');
  redirect('/po_list.php');
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
  if ($dbStatus === 'RECEIVED') {
    return 'ok';
  }
  if ($dbStatus === 'CANCELLED') {
    return 'danger';
  }
  return '';
}

if (!is_admin()) {
  $sid = (int)(current_supplier_id() ?? 0);
  if ($sid > 0 && (int)($po['supplier_id'] ?? 0) !== $sid) {
    flash_set('error', '접근 권한이 없습니다.');
    redirect('/po_list.php');
  }
}

$items = $po['items'] ?? [];
$totalLines = is_array($items) ? count($items) : 0;
$totalQty = 0;
if (is_array($items)) {
  foreach ($items as $row) {
    $totalQty += (int)($row['qty'] ?? 0);
  }
}

$canEdit = false;
if ((string)($po['status'] ?? '') === 'OPEN') {
  if (is_admin()) {
    $canEdit = true;
  } else {
    $canEdit = (int)($po['ordered_by'] ?? 0) === (int)current_user()['id'];
  }
}

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action !== 'cancel') {
      throw new RuntimeException('잘못된 요청입니다.');
    }

    if ((string)($po['status'] ?? '') !== 'OPEN') {
      throw new RuntimeException('OPEN 상태의 발주만 취소할 수 있습니다.');
    }
    if (!$canEdit) {
      throw new RuntimeException('접근 권한이 없습니다.');
    }

    // 안전장치: 연결된 입고가 있으면 취소 불가
    $st = $db->prepare('SELECT COUNT(*) FROM receipts WHERE purchase_order_id = ?');
    $st->execute([$id]);
    $rcCnt = (int)$st->fetchColumn();
    if ($rcCnt > 0) {
      throw new RuntimeException('이미 입고 처리된 발주는 취소할 수 없습니다.');
    }

    $stUp = $db->prepare("UPDATE purchase_orders SET status='CANCELLED' WHERE id = ?");
    $stUp->execute([$id]);

    flash_set('success', '발주가 취소되었습니다.');
    redirect('/po_view.php?id=' . $id);
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    redirect('/po_view.php?id=' . $id);
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card">
      <div class="kpi">
        <div>
          <h1 class="h1" style="margin-bottom:4px">발주 상세</h1>
          <div class="muted"><?= e($po['po_no']) ?> · <?= e($po['supplier_name']) ?></div>
        </div>
        <div>
          <a class="btn secondary" href="<?= e(url('/po_list.php')) ?>">목록</a>
          <?php if ($canEdit): ?>
            <a class="btn secondary" href="<?= e(url('/po_edit.php?id=' . (int)$po['id'])) ?>">수정/삭제</a>
          <?php endif; ?>
          <?php if ((string)($po['status'] ?? '') === 'OPEN'): ?>
            <a class="btn" href="<?= e(url('/receipt_create.php?purchase_order_id=' . (int)$po['id'])) ?>">이 발주 입고 처리</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($canEdit && (string)($po['status'] ?? '') === 'OPEN'): ?>
        <div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end">
          <form method="post" action="<?= e(url('/po_view.php?id=' . (int)$po['id'])) ?>" onsubmit="return confirm('이 발주를 취소(CANCEL) 처리할까요?')">
            <input type="hidden" name="action" value="cancel" />
            <button class="btn danger" type="submit">취소(CANCEL)</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h2 class="h1">헤더</h2>
      <table class="table">
        <tbody>
          <tr><th>발주번호</th><td><?= e($po['po_no']) ?></td></tr>
          <tr><th>거래처</th><td><?= e($po['supplier_name']) ?></td></tr>
          <tr><th>발주일</th><td><?= e($po['order_date']) ?></td></tr>
          <tr><th>상태</th><td><span class="badge <?= e(po_status_badge_class((string)($po['status'] ?? ''))) ?>"><?= e(po_status_label((string)($po['status'] ?? ''))) ?></span></td></tr>
          <tr><th>등록자</th><td><?= e($po['ordered_by_name']) ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-6">
    <div class="card">
      <h2 class="h1">메모</h2>
      <div class="muted"><?= e($po['notes'] ?: '-') ?></div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <h2 class="h1">발주 품목</h2>
      <div class="small">총 품목 수: <?= e((string)$totalLines) ?> · 총 수량: <?= e((string)$totalQty) ?></div>
      <table class="table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>품목</th>
            <th>수량</th>
            <th>단가</th>
            <th>단위</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($po['items'] ?? []) as $row): ?>
            <tr>
              <td><?= e($row['sku']) ?></td>
              <td><?= e($row['name']) ?></td>
              <td><?= e((string)$row['qty']) ?></td>
              <td><?= e(number_format((float)$row['unit_cost'], 2)) ?></td>
              <td><?= e($row['unit']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
