<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();

$u = current_user();
$supplierId = (int)($u['supplier_id'] ?? 0);
if ($supplierId <= 0) {
  flash_set('error', '거래처 소속이 필요합니다.');
  redirect('/profile.php');
}

// 안전재고는 신규 등록 시 고정(관리자가 /admin/items.php 에서 변경 가능)
$fixedMinStock = (int)DEFAULT_MIN_STOCK;

if (!isset($_SESSION['setup_cart'])) {
  $_SESSION['setup_cart'] = [];
}

$action = trim((string)($_POST['action'] ?? ''));
$q = trim((string)($_GET['q'] ?? ($_POST['q'] ?? '')));

if (is_post()) {
  try {
    if ($action === 'add') {
      $templateId = (int)($_POST['template_item_id'] ?? 0);
      if ($templateId <= 0) {
        throw new RuntimeException('추가할 품목이 올바르지 않습니다.');
      }
      $tpl = Item::find($db, $templateId);
      if (!$tpl) {
        throw new RuntimeException('품목을 찾을 수 없습니다.');
      }
      $_SESSION['setup_cart'][(string)$templateId] = (int)($_SESSION['setup_cart'][(string)$templateId] ?? 0);
      flash_set('success', '부족 품목 리스트에 추가되었습니다.');
      redirect('/supplier_items_setup.php' . ($q !== '' ? ('?q=' . urlencode($q)) : ''));
    }

    if ($action === 'remove') {
      $templateId = (int)($_POST['template_item_id'] ?? 0);
      unset($_SESSION['setup_cart'][(string)$templateId]);
      flash_set('success', '삭제되었습니다.');
      redirect('/supplier_items_setup.php' . ($q !== '' ? ('?q=' . urlencode($q)) : ''));
    }

    if ($action === 'update') {
      $templateIds = $_POST['template_item_id'] ?? [];
      $onHands = $_POST['on_hand'] ?? [];
      for ($i = 0; $i < count($templateIds); $i++) {
        $tid = (int)$templateIds[$i];
        if ($tid <= 0) continue;
        $val = (int)($onHands[$i] ?? 0);
        $_SESSION['setup_cart'][(string)$tid] = $val;
      }
      flash_set('success', '저장되었습니다.');
      redirect('/supplier_items_setup.php' . ($q !== '' ? ('?q=' . urlencode($q)) : ''));
    }

    if ($action === 'submit') {
      $cart = $_SESSION['setup_cart'] ?? [];
      if (!$cart) {
        throw new RuntimeException('등록할 부족 품목이 없습니다. 먼저 아래 검색에서 품목을 추가하세요.');
      }

      $db->beginTransaction();
      try {
        foreach ($cart as $templateIdStr => $onHand) {
          $templateId = (int)$templateIdStr;
          $tpl = Item::find($db, $templateId);
          if (!$tpl) {
            continue;
          }

          $name = (string)$tpl['name'];
          $unit = trim((string)($tpl['unit'] ?? 'EA')) ?: 'EA';

          // SKU 유니크 제약 회피용 생성
          $sku = null;
          for ($try = 0; $try < 6; $try++) {
            $candidate = sprintf('SUP%d-%s', $supplierId, strtoupper(bin2hex(random_bytes(3))));
            try {
              $newId = Item::create($db, [
                'sku' => $candidate,
                'name' => $name,
                'supplier_id' => $supplierId,
                'unit' => $unit,
                'min_stock' => $fixedMinStock,
              ]);
              Inventory::setOnHand($db, $newId, (int)$onHand);
              $sku = $candidate;
              break;
            } catch (PDOException $e) {
              // 충돌 시 재시도
              continue;
            }
          }
          if ($sku === null) {
            throw new RuntimeException('SKU 생성에 실패했습니다. 잠시 후 다시 시도하세요.');
          }
        }

        $db->commit();
      } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
      }

      unset($_SESSION['setup_cart']);
      unset($_SESSION['onboarding_supplier_id']);

      flash_set('success', '부족 품목이 등록되었습니다. 이제 발주/입고를 진행할 수 있습니다.');
      redirect('/items.php');
    }

    if ($action === 'skip') {
      unset($_SESSION['onboarding_supplier_id']);
      flash_set('success', '나중에 등록할 수 있습니다.');
      redirect('/items.php');
    }
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

$cartIds = array_map('intval', array_keys((array)($_SESSION['setup_cart'] ?? [])));
$cartItems = $cartIds ? Item::listByIds($db, $cartIds) : [];

// 전체 품목(기존에 있던 품목) 목록을 기본으로 보여주고, 검색어가 있으면 필터링
$searchResults = Item::list($db, ['q' => $q, 'supplier_id' => 0]);

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">신규 거래처 · 부족 품목 등록</h1>
      <div class="muted">신규 거래처는 부족 품목을 직접 등록합니다. 안전재고는 기본 <?= e((string)$fixedMinStock) ?>로 고정되며, 관리자가 품목관리에서 변경할 수 있습니다.</div>
    </div>
    <div>
      <form method="post" action="<?= e(url('/supplier_items_setup.php')) ?>" style="display:inline">
        <input type="hidden" name="action" value="skip" />
        <button class="btn secondary" type="submit">나중에</button>
      </form>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <h2 class="h1">선택된 부족 품목</h2>

  <?php if (!$cartItems): ?>
    <div class="muted">아직 선택된 품목이 없습니다. 아래 검색에서 품목을 추가하세요.</div>
  <?php else: ?>
    <form method="post" action="<?= e(url('/supplier_items_setup.php' . ($q !== '' ? ('?q=' . urlencode($q)) : ''))) ?>">
      <input type="hidden" name="action" value="update" />
      <table class="table" style="margin-top:10px">
        <thead>
          <tr>
            <th>SKU(기존)</th>
            <th>품목</th>
            <th>단위</th>
            <th>현재재고</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cartItems as $it): ?>
            <?php $tid = (int)$it['id']; ?>
            <tr>
              <td><?= e($it['sku']) ?><input type="hidden" name="template_item_id[]" value="<?= e((string)$tid) ?>" /></td>
              <td><?= e($it['name']) ?></td>
              <td><?= e($it['unit']) ?></td>
              <td style="max-width:180px">
                <input class="input" type="number" min="0" name="on_hand[]" value="<?= e((string)(int)($_SESSION['setup_cart'][(string)$tid] ?? 0)) ?>" />
              </td>
              <td style="white-space:nowrap">
                <form method="post" action="<?= e(url('/supplier_items_setup.php' . ($q !== '' ? ('?q=' . urlencode($q)) : ''))) ?>" style="display:inline">
                  <input type="hidden" name="action" value="remove" />
                  <input type="hidden" name="template_item_id" value="<?= e((string)$tid) ?>" />
                  <button class="btn secondary" type="submit">삭제</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top:10px">
        <button class="btn secondary" type="submit">재고값 저장</button>
      </div>
    </form>

    <form method="post" action="<?= e(url('/supplier_items_setup.php')) ?>" style="margin-top:10px">
      <input type="hidden" name="action" value="submit" />
      <button class="btn" type="submit">부족 품목 등록 완료</button>
    </form>
  <?php endif; ?>

  <div style="margin-top:18px"></div>

  <h2 class="h1">품목 검색(기존 품목에서 선택)</h2>
  <form method="get" action="<?= e(url('/supplier_items_setup.php')) ?>" style="margin-top:10px">
    <div class="form-row">
      <div class="field" style="min-width:280px;flex:1">
        <div class="label">검색</div>
        <input class="input" type="text" name="q" value="<?= e($q) ?>" placeholder="품목명 또는 SKU" />
      </div>
      <div class="field">
        <button class="btn" type="submit">검색</button>
      </div>
    </div>
  </form>

  <div class="small muted" style="margin-top:8px">
    아래 목록은 전체 품목이며, 검색어를 입력하면 필터링됩니다.
  </div>

  <table class="table" style="margin-top:10px">
    <thead>
      <tr>
        <th>SKU</th>
        <th>품목</th>
        <th>단위</th>
        <th>안전재고(참고)</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$searchResults): ?>
      <tr><td colspan="5" class="muted">표시할 품목이 없습니다.</td></tr>
    <?php else: ?>
      <?php foreach ($searchResults as $r): ?>
        <tr>
          <td><?= e($r['sku']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['unit']) ?></td>
          <td><?= e((string)$r['min_stock']) ?></td>
          <td style="white-space:nowrap">
            <form method="post" action="<?= e(url('/supplier_items_setup.php' . ($q !== '' ? ('?q=' . urlencode($q)) : ''))) ?>" style="display:inline">
              <input type="hidden" name="action" value="add" />
              <input type="hidden" name="template_item_id" value="<?= e((string)$r['id']) ?>" />
              <button class="btn" type="submit">추가</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
