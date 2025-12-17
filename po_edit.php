<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
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

if (!is_admin()) {
  $sid = (int)(current_supplier_id() ?? 0);
  if ($sid > 0 && (int)($po['supplier_id'] ?? 0) !== $sid) {
    flash_set('error', '접근 권한이 없습니다.');
    redirect('/po_list.php');
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

if (!$canEdit) {
  flash_set('error', '이 발주는 수정할 수 없습니다. (OPEN 상태 + 작성자만 가능)');
  redirect('/po_view.php?id=' . $id);
}

if (is_post()) {
  $action = trim((string)($_POST['action'] ?? ''));
  try {
    if ($action === 'delete') {
      PurchaseOrder::delete($db, $id);
      flash_set('success', '발주가 삭제되었습니다.');
      redirect('/po_list.php');
    }

    if ($action === 'update') {
      $orderDate = trim((string)($_POST['order_date'] ?? ''));
      if ($orderDate === '') {
        $orderDate = (string)($po['order_date'] ?? date('Y-m-d'));
      }
      $notes = trim((string)($_POST['notes'] ?? ''));
      $notes = $notes === '' ? null : $notes;

      $qtys = $_POST['qty'] ?? [];
      if (!is_array($qtys)) {
        throw new RuntimeException('수량 정보가 올바르지 않습니다.');
      }

      PurchaseOrder::update($db, $id, $orderDate, $notes, $qtys);
      flash_set('success', '발주가 수정되었습니다.');
      redirect('/po_view.php?id=' . $id);
    }

    throw new RuntimeException('지원하지 않는 action 입니다.');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    redirect('/po_edit.php?id=' . $id);
  }
}

$orderDate = (string)($po['order_date'] ?? date('Y-m-d'));
$notes = (string)($po['notes'] ?? '');

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">발주 수정</h1>
      <div class="muted"><?= e($po['po_no']) ?> · <?= e($po['supplier_name']) ?></div>
    </div>
    <div style="display:flex;gap:8px">
      <a class="btn secondary" href="<?= e(url('/po_view.php?id=' . (int)$po['id'])) ?>">취소</a>
      <form method="post" action="<?= e(url('/po_edit.php')) ?>" onsubmit="return confirm('정말 이 발주를 삭제할까요? (되돌릴 수 없습니다)')">
        <input type="hidden" name="id" value="<?= e((string)$po['id']) ?>" />
        <input type="hidden" name="action" value="delete" />
        <button class="btn danger" type="submit">삭제</button>
      </form>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <form method="post" action="<?= e(url('/po_edit.php')) ?>">
    <input type="hidden" name="id" value="<?= e((string)$po['id']) ?>" />
    <input type="hidden" name="action" value="update" />

    <div class="form-row">
      <div class="field">
        <div class="label">발주일</div>
        <input class="input" type="date" name="order_date" value="<?= e($orderDate) ?>" required />
      </div>
      <div class="field" style="min-width:320px;flex:1">
        <div class="label">메모</div>
        <input class="input" type="text" name="notes" value="<?= e($notes) ?>" placeholder="예: 긴급" />
      </div>
      <div class="field">
        <button class="btn" type="submit">저장</button>
      </div>
    </div>

    <div style="margin-top:12px"></div>

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
        <?php foreach (($po['items'] ?? []) as $row): ?>
          <?php $itemId = (int)$row['item_id']; ?>
          <tr>
            <td><?= e($row['sku']) ?></td>
            <td><?= e($row['name']) ?></td>
            <td>
              <input class="input" style="max-width:160px" type="number" min="1" name="qty[<?= e((string)$itemId) ?>]" value="<?= e((string)$row['qty']) ?>" required />
            </td>
            <td><?= e($row['unit']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <div class="small" style="margin-top:10px">수정/삭제는 OPEN 상태에서만 가능하며, 일반 사용자는 본인이 등록한 발주만 수정할 수 있습니다.</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
