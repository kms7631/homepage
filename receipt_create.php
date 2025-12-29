<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();
require_once __DIR__ . '/includes/flatpickr_datepicker.php';

$db = db();

$suppliers = Supplier::listAll($db);

$purchaseOrderId = (int)($_GET['purchase_order_id'] ?? ($_POST['purchase_order_id'] ?? 0));
$supplierId = (int)($_GET['supplier_id'] ?? ($_POST['supplier_id'] ?? 0));
$receiptDate = (string)($_POST['receipt_date'] ?? date('Y-m-d'));
$notes = (string)($_POST['notes'] ?? '');

$po = null;
$items = [];

if ($purchaseOrderId > 0) {
  $po = PurchaseOrder::find($db, $purchaseOrderId);
  if ($po) {
    $supplierId = (int)$po['supplier_id'];
    if ((string)($po['status'] ?? '') !== 'OPEN') {
      flash_set('error', 'OPEN 상태의 발주만 입고 처리할 수 있습니다.');
      redirect('/receipt_create.php' . ($supplierId > 0 ? ('?supplier_id=' . $supplierId) : ''));
    }
    $items = $po['items'] ?? [];
  }
}

if (!is_admin()) {
  $supplierId = (int)(current_supplier_id() ?? 0);
  // 일반 사용자는 임의의 purchase_order_id를 넘겨도 소속 거래처가 아니면 차단
  if ($po && (int)($po['supplier_id'] ?? 0) !== $supplierId) {
    flash_set('error', '접근 권한이 없습니다.');
    redirect('/receipt_list.php');
  }
  $purchaseOrderId = $po ? (int)$purchaseOrderId : 0;
}

// 선택용 PO 리스트(간단히 최근 50건)
$poOptions = [];
if ($supplierId > 0) {
  $st = $db->prepare("SELECT id, po_no, status, order_date FROM purchase_orders WHERE supplier_id = ? AND status='OPEN' ORDER BY id DESC LIMIT 50");
  $st->execute([$supplierId]);
  $poOptions = $st->fetchAll();
}

$extraHeadHtml = flatpickr_datepicker_head_html();
$extraBodyEndHtml = flatpickr_datepicker_body_html('input.js-date');

if (is_post()) {
  try {
    if ($supplierId <= 0) {
      throw new RuntimeException('거래처를 선택하세요.');
    }

    $itemIds = $_POST['item_id'] ?? [];
    $qtys = $_POST['qty_received'] ?? [];

    $rows = [];
    for ($i = 0; $i < count($itemIds); $i++) {
      $rows[] = [
        'item_id' => (int)$itemIds[$i],
        'qty_received' => (int)($qtys[$i] ?? 0),
      ];
    }

    $rcId = Receipt::create(
      $db,
      $supplierId,
      (int)current_user()['id'],
      $receiptDate,
      trim($notes) ?: null,
      $purchaseOrderId > 0 ? $purchaseOrderId : null,
      $rows
    );

    flash_set('success', '입고가 처리되었습니다.');
    redirect('/receipt_view.php?id=' . $rcId);
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">입고 처리</h1>
      <div class="muted">입고 처리 시 receipts/receipt_items + inventory 증가를 트랜잭션으로 처리합니다.</div>
    </div>
    <div>
      <a class="btn secondary" href="<?= e(url('/receipt_list.php')) ?>">입고 조회</a>
    </div>
  </div>

  <form method="get" action="<?= e(url('/receipt_create.php')) ?>" style="margin-top:12px">
    <div class="form-row">
      <?php if (is_admin()): ?>
        <div class="field">
          <div class="label">거래처</div>
          <select name="supplier_id">
            <option value="0">선택...</option>
            <?php foreach ($suppliers as $sp): ?>
              <option value="<?= e((string)$sp['id']) ?>" <?= $supplierId === (int)$sp['id'] ? 'selected' : '' ?>>
                <?= e($sp['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
        <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
      <?php endif; ?>
      <div class="field" style="min-width:260px">
        <div class="label">발주 연결(선택)</div>
        <select name="purchase_order_id">
          <option value="0">미연결</option>
          <?php foreach ($poOptions as $opt): ?>
            <option value="<?= e((string)$opt['id']) ?>" <?= $purchaseOrderId === (int)$opt['id'] ? 'selected' : '' ?>>
              <?= e($opt['po_no']) ?> (<?= e($opt['status']) ?>, <?= e($opt['order_date']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <button class="btn" type="submit">불러오기</button>
      </div>
    </div>
  </form>

  <div style="margin-top:12px"></div>

  <?php if ($supplierId > 0 && $po && $purchaseOrderId > 0): ?>
    <form method="post" action="<?= e(url('/receipt_create.php')) ?>">
      <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
      <input type="hidden" name="purchase_order_id" value="<?= e((string)$purchaseOrderId) ?>" />

      <div class="form-row">
        <div class="field">
          <div class="label">입고일</div>
          <input class="input js-date" type="text" name="receipt_date" value="<?= e($receiptDate) ?>" placeholder="YYYY-MM-DD" autocomplete="off" required />
        </div>
        <div class="field" style="min-width:320px;flex:1">
          <div class="label">메모</div>
          <input class="input" type="text" name="notes" value="<?= e($notes) ?>" placeholder="예: 납품서 123" />
        </div>
        <div class="field">
          <button class="btn" type="submit">입고 처리</button>
        </div>
      </div>

      <div style="margin-top:12px"></div>

      <table class="table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>품목</th>
            <th>입고 수량</th>
            <th>참고</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="4" class="muted">표시할 품목이 없습니다.</td></tr>
        <?php else: ?>
          <?php foreach ($items as $row): ?>
            <?php
              // PO 연결 시: purchase_order_items.item_id
              // 미연결(거래처 전체 품목) 시: items.id
              $itemId = (int)($row['item_id'] ?? ($row['id'] ?? 0));
              $sku = (string)($row['sku'] ?? '');
              $name = (string)($row['name'] ?? '');
              $ref = '';
              if (isset($row['qty'])) {
                $ref = '발주수량: ' . (int)$row['qty'];
              }
            ?>
            <tr>
              <td><?= e($sku) ?><input type="hidden" name="item_id[]" value="<?= e((string)$itemId) ?>" /></td>
              <td><?= e($name) ?></td>
              <td><input class="input" style="max-width:160px" type="number" min="0" name="qty_received[]" value="<?= e((string)(isset($row['qty']) ? (int)$row['qty'] : 0)) ?>" /></td>
              <td class="muted"><?= e($ref ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>

      <div class="small" style="margin-top:10px">수량이 0인 품목은 입고에 포함되지 않습니다.</div>
    </form>
  <?php elseif ($supplierId > 0): ?>
    <div class="muted">발주 연결에서 발주를 선택하고 “불러오기”를 누르면 입고 품목 입력 폼이 표시됩니다.</div>
  <?php else: ?>
    <div class="muted">거래처를 선택하면 발주 목록을 불러올 수 있습니다.</div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
