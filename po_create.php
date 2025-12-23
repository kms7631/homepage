<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();
require_once __DIR__ . '/includes/flatpickr_datepicker.php';

$db = db();

$suppliers = Supplier::listAll($db);

// Session cart
if (!isset($_SESSION['po_cart']) || !is_array($_SESSION['po_cart'])) {
  $_SESSION['po_cart'] = [];
}
if (!isset($_SESSION['po_cart_supplier_id'])) {
  $_SESSION['po_cart_supplier_id'] = 0;
}

$supplierId = (int)($_GET['supplier_id'] ?? ($_POST['supplier_id'] ?? $_SESSION['po_cart_supplier_id'] ?? 0));

if (!is_admin()) {
  $supplierId = (int)(current_supplier_id() ?? 0);
}

// Supplier change: reset cart to keep consistency
if ($supplierId > 0 && (int)$_SESSION['po_cart_supplier_id'] !== $supplierId) {
  $_SESSION['po_cart'] = [];
  $_SESSION['po_cart_supplier_id'] = $supplierId;
  flash_set('success', '거래처가 변경되어 카트가 초기화되었습니다.');
  redirect('/po_create.php?supplier_id=' . $supplierId);
}

$action = trim((string)($_POST['action'] ?? ''));

if (is_post() && $action !== '') {
  try {
    if ($action !== 'select_supplier' && $supplierId <= 0) {
      throw new RuntimeException('거래처를 먼저 선택하세요.');
    }

    if ($action === 'select_supplier') {
      if ($supplierId <= 0) {
        throw new RuntimeException('거래처를 선택하세요.');
      }
      $_SESSION['po_cart'] = [];
      $_SESSION['po_cart_supplier_id'] = $supplierId;
      flash_set('success', '거래처가 선택되었습니다. 이제 품목을 추가하세요.');
      redirect('/po_create.php?supplier_id=' . $supplierId);
    }

    if ($action === 'add') {
      $itemId = (int)($_POST['item_id'] ?? 0);
      if ($itemId <= 0) {
        throw new RuntimeException('추가할 품목이 올바르지 않습니다.');
      }
      $it = Item::find($db, $itemId);
      if (!$it) {
        throw new RuntimeException('품목을 찾을 수 없습니다.');
      }
      if ((int)($it['supplier_id'] ?? 0) !== $supplierId) {
        throw new RuntimeException('선택한 거래처의 품목만 추가할 수 있습니다.');
      }

      $cart = $_SESSION['po_cart'];
      $cart[$itemId] = (int)($cart[$itemId] ?? 0) + 1; // 동일 품목 재추가 시 합산
      $_SESSION['po_cart'] = $cart;

      flash_set('success', '카트에 추가되었습니다.');
      redirect('/po_create.php?supplier_id=' . $supplierId . '&q=' . urlencode((string)($_GET['q'] ?? '')));
    }

    if ($action === 'remove') {
      $itemId = (int)($_POST['item_id'] ?? ($_POST['remove_item_id'] ?? 0));
      $cart = $_SESSION['po_cart'];
      unset($cart[$itemId]);
      $_SESSION['po_cart'] = $cart;
      flash_set('success', '카트에서 제거되었습니다.');
      redirect('/po_create.php?supplier_id=' . $supplierId);
    }

    if ($action === 'update') {
      // 중첩 form을 없애면서 제거 버튼은 remove_item_id로 처리
      $removeItemId = (int)($_POST['remove_item_id'] ?? 0);
      if ($removeItemId > 0) {
        $cart = $_SESSION['po_cart'];
        unset($cart[$removeItemId]);
        $_SESSION['po_cart'] = $cart;
        flash_set('success', '카트에서 제거되었습니다.');
        redirect('/po_create.php?supplier_id=' . $supplierId);
      }

      $qtys = $_POST['qty'] ?? [];
      if (!is_array($qtys)) {
        throw new RuntimeException('수량 정보가 올바르지 않습니다.');
      }
      $cart = $_SESSION['po_cart'];
      foreach ($qtys as $itemIdStr => $qtyVal) {
        $itemId = (int)$itemIdStr;
        if (!isset($cart[$itemId])) {
          continue;
        }
        $qty = (int)$qtyVal;
        if ($qty < 1) {
          throw new RuntimeException('수량은 1 이상이어야 합니다.');
        }
        $cart[$itemId] = $qty;
      }
      $_SESSION['po_cart'] = $cart;
      flash_set('success', '수량이 반영되었습니다.');
      redirect('/po_create.php?supplier_id=' . $supplierId);
    }

    if ($action === 'submit') {
      $orderDate = trim((string)($_POST['order_date'] ?? ''));
      $notes = (string)($_POST['notes'] ?? '');

      // 제출 시에도 qty[]를 받아 세션 카트에 즉시 반영 (수량 반영 버튼을 누르지 않아도 입력값이 저장되도록)
      $qtys = $_POST['qty'] ?? [];
      if (is_array($qtys)) {
        $cart = $_SESSION['po_cart'];
        foreach ($qtys as $itemIdStr => $qtyVal) {
          $itemId = (int)$itemIdStr;
          if (!isset($cart[$itemId])) {
            continue;
          }
          $qty = (int)$qtyVal;
          if ($qty < 1) {
            throw new RuntimeException('수량은 1 이상이어야 합니다.');
          }
          $cart[$itemId] = $qty;
        }
        $_SESSION['po_cart'] = $cart;
      }

      if ($supplierId <= 0) {
        throw new RuntimeException('거래처를 선택하세요.');
      }
      $cart = $_SESSION['po_cart'];
      if (!$cart) {
        throw new RuntimeException('품목을 1개 이상 담아주세요.');
      }

      $rows = [];
      foreach ($cart as $itemId => $qty) {
        $itemId = (int)$itemId;
        $qty = (int)$qty;
        if ($itemId <= 0 || $qty < 1) {
          throw new RuntimeException('카트 품목/수량이 올바르지 않습니다.');
        }
        $rows[] = ['item_id' => $itemId, 'qty' => $qty, 'unit_cost' => 0];
      }

      if ($orderDate === '') {
        $orderDate = date('Y-m-d');
      }

      $poId = PurchaseOrder::create($db, $supplierId, (int)current_user()['id'], $orderDate, trim($notes) ?: null, $rows);

      $_SESSION['po_cart'] = [];
      $_SESSION['po_cart_supplier_id'] = 0;

      flash_set('success', '발주가 제출되었습니다.');
      redirect('/po_view.php?id=' . $poId);
    }

    throw new RuntimeException('지원하지 않는 action 입니다.');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    redirect('/po_create.php' . ($supplierId > 0 ? ('?supplier_id=' . $supplierId) : ''));
  }
}

$q = trim((string)($_GET['q'] ?? ''));
$searchResults = [];
if ($supplierId > 0 && $q !== '') {
  $searchResults = Item::list($db, ['q' => $q, 'supplier_id' => $supplierId]);
}

$lowStockItems = [];
if ($supplierId > 0) {
  $allForSupplier = Item::listBySupplier($db, $supplierId);
  foreach ($allForSupplier as $it) {
    if ((int)$it['on_hand'] <= (int)$it['min_stock']) {
      $lowStockItems[] = $it;
    }
  }
  usort($lowStockItems, function (array $a, array $b): int {
    $aShort = (int)$a['min_stock'] - (int)$a['on_hand'];
    $bShort = (int)$b['min_stock'] - (int)$b['on_hand'];
    return $bShort <=> $aShort;
  });
}

$cart = $_SESSION['po_cart'];
$cartItems = [];
if ($supplierId > 0 && $cart) {
  $cartItems = Item::listByIds($db, array_keys($cart));
}

$supplierName = '';
if ($supplierId > 0) {
  foreach ($suppliers as $sp) {
    if ((int)$sp['id'] === $supplierId) {
      $supplierName = (string)$sp['name'];
      break;
    }
  }
}

$orderDate = (string)($_POST['order_date'] ?? date('Y-m-d'));
$notes = (string)($_POST['notes'] ?? '');

$extraHeadHtml = flatpickr_datepicker_head_html();
$extraBodyEndHtml = flatpickr_datepicker_body_html('input.js-date');

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">발주 등록</h1>
      <div class="muted">카트에 여러 품목을 담아 한 번에 발주를 제출합니다.</div>
    </div>
    <div>
      <a class="btn secondary" href="<?= e(url('/po_list.php')) ?>">발주 조회</a>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <!-- 1) 거래처 표시(최상단) -->
  <div class="form-row" style="align-items:center">
    <div class="field" style="flex:1">
      <div style="display:flex;align-items:center;gap:10px">
        <div class="label" style="margin:0">거래처:</div>
        <span class="badge"><?= e($supplierId > 0 ? ($supplierName ?: ('#' . $supplierId)) : (is_admin() ? '미선택' : ($supplierName ?: '소속 거래처로 고정'))) ?></span>
      </div>
    </div>
  </div>

  <!-- 2) Supplier 선택(관리자만) -->
  <?php if (is_admin()): ?>
    <form method="post" action="<?= e(url('/po_create.php')) ?>" style="margin-top:10px">
      <input type="hidden" name="action" value="select_supplier" />
      <div class="form-row">
        <div class="field" style="min-width:280px">
          <div class="label">거래처 변경</div>
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
          <button class="btn" type="submit">확정</button>
        </div>
      </div>
      <div class="small" style="margin-top:10px">거래처를 바꾸면 카트는 초기화됩니다.</div>
    </form>
  <?php endif; ?>

  <?php if ($supplierId > 0): ?>
    <div style="margin-top:14px"></div>

    <!-- 3) 부족 품목(안전재고 기준) -->
    <div class="card" style="padding:12px">
      <div class="kpi">
        <div>
          <h2 class="h1" style="margin-bottom:4px">부족한 품목들</h2>
          <div class="muted">현재 재고가 안전재고 이하인 품목입니다. 바로 “추가”로 카트에 담을 수 있어요.</div>
        </div>
        <div>
          <span class="badge">총 <?= e((string)count($lowStockItems)) ?>건</span>
        </div>
      </div>

      <?php if (!$lowStockItems): ?>
        <div class="muted" style="margin-top:10px">부족한 품목이 없습니다.</div>
      <?php else: ?>
        <div style="margin-top:10px"></div>
        <table class="table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>품목</th>
              <th>재고</th>
              <th>안전재고</th>
              <th>부족수량</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lowStockItems as $it): ?>
              <?php
                $short = max(0, (int)$it['min_stock'] - (int)$it['on_hand']);
              ?>
              <tr>
                <td><?= e($it['sku']) ?></td>
                <td>
                  <?= e($it['name']) ?>
                  <span class="badge danger">부족</span>
                </td>
                <td><?= e((string)$it['on_hand']) ?></td>
                <td><?= e((string)$it['min_stock']) ?></td>
                <td><?= e((string)$short) ?></td>
                <td>
                  <form method="post" action="<?= e(url('/po_create.php?supplier_id=' . $supplierId . '&q=' . urlencode($q))) ?>">
                    <input type="hidden" name="action" value="add" />
                    <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
                    <input type="hidden" name="item_id" value="<?= e((string)$it['id']) ?>" />
                    <button class="btn" type="submit">추가</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div style="margin-top:14px"></div>

    <!-- 4) 품목 검색 + 추가 -->
    <div class="card" style="padding:12px">
      <div class="kpi">
        <div>
          <h2 class="h1" style="margin-bottom:4px">품목 검색</h2>
          <div class="muted">품목명/sku로 검색 후 “추가”로 카트에 담습니다.</div>
        </div>
      </div>

      <form method="get" action="<?= e(url('/po_create.php')) ?>" style="margin-top:10px">
        <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
        <div class="form-row">
          <div class="field" style="min-width:280px">
            <div class="label">검색</div>
            <input class="input" type="text" name="q" value="<?= e($q) ?>" placeholder="예: 박스 / RM-AL" />
          </div>
          <div class="field">
            <button class="btn" type="submit">검색</button>
          </div>
        </div>
      </form>

      <?php if ($q !== ''): ?>
        <div style="margin-top:10px"></div>
        <table class="table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>품목</th>
              <th>재고</th>
              <th>안전재고</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$searchResults): ?>
              <tr><td colspan="5" class="muted">검색 결과가 없습니다.</td></tr>
            <?php else: ?>
              <?php foreach ($searchResults as $it): ?>
                <?php $isLow = ((int)$it['on_hand'] <= (int)$it['min_stock']); ?>
                <tr>
                  <td><?= e($it['sku']) ?></td>
                  <td>
                    <?= e($it['name']) ?>
                    <?php if ($isLow): ?> <span class="badge danger">부족</span><?php endif; ?>
                  </td>
                  <td><?= e((string)$it['on_hand']) ?></td>
                  <td><?= e((string)$it['min_stock']) ?></td>
                  <td>
                    <form method="post" action="<?= e(url('/po_create.php?supplier_id=' . $supplierId . '&q=' . urlencode($q))) ?>">
                      <input type="hidden" name="action" value="add" />
                      <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
                      <input type="hidden" name="item_id" value="<?= e((string)$it['id']) ?>" />
                      <button class="btn" type="submit">추가</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div style="margin-top:14px"></div>

    <!-- 5) 카트(발주 리스트) -->
    <div class="card" style="padding:12px">
      <div class="kpi">
        <div>
          <h2 class="h1" style="margin-bottom:4px">발주 카트</h2>
          <div class="muted">수량 변경/제거 후 “발주 제출”로 한 번에 저장합니다.</div>
        </div>
        <div>
          <span class="badge">총 품목 수: <?= e((string)count($cart)) ?></span>
        </div>
      </div>

      <?php if (!$cartItems): ?>
        <div class="muted" style="margin-top:10px">카트가 비어있습니다. 품목을 검색해서 추가하세요.</div>
      <?php else: ?>
        <form method="post" action="<?= e(url('/po_create.php')) ?>" style="margin-top:10px">
          <input type="hidden" name="action" value="submit" />
          <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />

          <table class="table">
            <thead>
              <tr>
                <th>SKU</th>
                <th>품목</th>
                <th>수량(qty)</th>
                <th>단위</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cartItems as $it): ?>
                <?php
                  $itemId = (int)$it['id'];
                  $qty = (int)($cart[$itemId] ?? 0);
                ?>
                <tr>
                  <td><?= e($it['sku']) ?></td>
                  <td><?= e($it['name']) ?></td>
                  <td>
                    <input class="input" style="max-width:160px" type="number" min="1" name="qty[<?= e((string)$itemId) ?>]" value="<?= e((string)$qty) ?>" />
                  </td>
                  <td><?= e($it['unit']) ?></td>
                  <td>
                    <button class="btn danger" type="submit" name="remove_item_id" value="<?= e((string)$itemId) ?>" onclick="return confirm('이 품목을 카트에서 제거할까요?')">제거</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div style="margin-top:14px"></div>

          <div class="form-row">
            <div class="field">
              <div class="label">발주일</div>
              <input class="input js-date" type="text" name="order_date" value="<?= e($orderDate) ?>" placeholder="YYYY-MM-DD" autocomplete="off" required />
            </div>
            <div class="field" style="min-width:320px;flex:1">
              <div class="label">메모</div>
              <input class="input" type="text" name="notes" value="<?= e($notes) ?>" placeholder="예: 긴급" />
            </div>
            <div class="field" style="display:flex;gap:8px;align-items:flex-end">
              <button class="btn secondary" type="submit" name="action" value="update">수량 반영</button>
              <button class="btn" type="submit" name="action" value="submit">발주 제출</button>
            </div>
          </div>
          <div class="small" style="margin-top:10px">수량을 수정한 뒤 바로 “발주 제출”을 눌러도 입력한 수량이 반영됩니다.</div>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
