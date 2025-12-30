<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$nameExact = trim((string)($_GET['name_exact'] ?? ''));

// 템플릿(전체 물품)에서 선택하여 내 거래처 품목으로 추가하는 카트
if (!isset($_SESSION['item_add_cart']) || !is_array($_SESSION['item_add_cart'])) {
  $_SESSION['item_add_cart'] = [];
}

$supplierId = (int)($_GET['supplier_id'] ?? 0);
if (!is_admin()) {
  $supplierId = (int)(current_supplier_id() ?? 0);
}

// 하단 전체 물품 검색어
$templateQ = trim((string)($_GET['template_q'] ?? ($_POST['template_q'] ?? '')));

// 안전재고 기본값(추가 후 관리자가 변경 가능)
$fixedMinStock = (int)DEFAULT_MIN_STOCK;

// 선택/삭제/등록 처리
if (is_post()) {
  $action = trim((string)($_POST['action'] ?? ''));
  try {
    if ($supplierId <= 0) {
      throw new RuntimeException('거래처가 선택되지 않았습니다.');
    }

    if ($action === 'add_template') {
      $templateId = (int)($_POST['template_item_id'] ?? 0);
      if ($templateId <= 0) {
        throw new RuntimeException('추가할 품목이 올바르지 않습니다.');
      }
      $_SESSION['item_add_cart'][(string)$templateId] = 1;
      flash_set('success', '선택된 품목에 추가되었습니다.');
      redirect('/items.php?supplier_id=' . $supplierId . '&template_q=' . urlencode($templateQ) . '#catalog-add');
    }

    if ($action === 'remove_template') {
      $templateId = (int)($_POST['template_item_id'] ?? 0);
      unset($_SESSION['item_add_cart'][(string)$templateId]);
      flash_set('success', '삭제되었습니다.');
      redirect('/items.php?supplier_id=' . $supplierId . '&template_q=' . urlencode($templateQ) . '#catalog-add');
    }

    if ($action === 'submit_templates') {
      $cart = (array)($_SESSION['item_add_cart'] ?? []);
      if (!$cart) {
        throw new RuntimeException('추가할 품목이 없습니다. 아래 전체 물품에서 선택하세요.');
      }

      $db->beginTransaction();
      try {
        $created = 0;
        foreach (array_keys($cart) as $templateIdStr) {
          $templateId = (int)$templateIdStr;
          if ($templateId <= 0) continue;

          $tpl = Item::find($db, $templateId);
          if (!$tpl) {
            continue;
          }

          $name = (string)($tpl['name'] ?? '');
          $sku = trim((string)($tpl['sku'] ?? ''));
          $unit = trim((string)($tpl['unit'] ?? 'EA')) ?: 'EA';
          if ($name === '') {
            continue;
          }
          if ($sku === '') {
            continue;
          }

          // 이미 내 거래처에 같은 SKU가 있으면 중복 생성하지 않음
          $exists = Item::findBySkuForSupplier($db, $sku, $supplierId);
          if ($exists) {
            continue;
          }

          try {
            Item::create($db, [
              'sku' => $sku,
              'name' => $name,
              'supplier_id' => $supplierId,
              'unit' => $unit,
              'min_stock' => $fixedMinStock,
            ]);
            $created++;
          } catch (PDOException $e) {
            // 기존 DB에서 sku 유니크 제약(uq_items_sku)이 남아있으면 다른 거래처에 같은 SKU를 추가할 수 없음
            throw new RuntimeException('동일 SKU를 거래처별로 사용하려면 DB 마이그레이션이 필요합니다. migrate_adjust_items_sku_unique.sql 을 실행하세요.');
          }
        }

        $db->commit();
        $_SESSION['item_add_cart'] = [];
        flash_set('success', '품목이 추가되었습니다. (총 ' . $created . '건)');
        redirect('/items.php?supplier_id=' . $supplierId);
      } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
      }
    }
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    redirect('/items.php' . ($supplierId > 0 ? ('?supplier_id=' . $supplierId) : ''));
  }
}

$suppliers = is_admin() ? Supplier::listAll($db) : [];
$items = Item::list($db, ['q' => $q, 'name_exact' => $nameExact, 'supplier_id' => $supplierId]);

$cartIds = array_map('intval', array_keys((array)($_SESSION['item_add_cart'] ?? [])));
$cartItems = $cartIds ? Item::listByIds($db, $cartIds) : [];

// 하단 전체 물품(템플릿) 목록: 검색어가 있으면 필터
$tplWhere = ['it.active = 1'];
$tplParams = [];
// 과거 버전에서 생성된 SUP* 랜덤 SKU는 템플릿 목록에서 숨김
$tplWhere[] = "it.sku NOT LIKE 'SUP%'";
if ($templateQ !== '') {
  $tplWhere[] = '(it.name LIKE ? OR it.sku LIKE ?)';
  $tplParams[] = '%' . $templateQ . '%';
  $tplParams[] = '%' . $templateQ . '%';
}
$tplSql = 'SELECT MIN(it.id) AS id, MIN(it.sku) AS sku, it.name, MIN(it.unit) AS unit, MIN(it.min_stock) AS min_stock
           FROM items it
           WHERE ' . implode(' AND ', $tplWhere) .
           ' GROUP BY it.name
           ORDER BY it.name ASC
           LIMIT 300';
$tplSt = $db->prepare($tplSql);
$tplSt->execute($tplParams);
$templateItems = $tplSt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">품목</h1>
      <div class="muted">검색/필터로 부족 품목을 빠르게 찾으세요.</div>
    </div>
    <div>
      <?php if (is_admin()): ?>
        <a class="btn secondary" href="<?= e(url('/admin/items.php')) ?>">품목 관리</a>
      <?php else: ?>
        <a class="btn" href="<?= e(url('/inventory_manage.php')) ?>">품목 관리</a>
      <?php endif; ?>
      <a class="btn secondary" href="<?= e(url('/items.php' . ($supplierId > 0 ? ('?supplier_id=' . $supplierId) : ''))) ?>#catalog-add">품목 추가</a>
    </div>
  </div>

  <form method="get" action="<?= e(url('/items.php')) ?>" style="margin-top:12px">
    <div class="form-row">
      <div class="field" style="min-width:260px">
        <div class="label">검색(품목명/sku)</div>
        <input class="input" type="text" name="q" value="<?= e($q) ?>" placeholder="예: 알루미늄 / RM-AL" />
      </div>
      <?php if (is_admin()): ?>
        <div class="field">
          <div class="label">거래처</div>
          <select name="supplier_id">
            <option value="0">전체</option>
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
      <div class="field">
        <button class="btn" type="submit">조회</button>
      </div>
    </div>
  </form>

  <div style="margin-top:12px"></div>

  <table class="table">
    <thead>
      <tr>
        <th>SKU</th>
        <th>품목</th>
        <th>거래처</th>
        <th>재고</th>
        <th>안전재고</th>
        <th>상태</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="6" class="muted">검색 결과가 없습니다.</td></tr>
      <?php else: ?>
        <?php foreach ($items as $it): ?>
          <?php $isLow = ((int)$it['on_hand'] < (int)$it['min_stock']); ?>
          <tr>
            <td><?= e($it['sku']) ?></td>
            <td><a href="<?= e(url('/item_view.php?id=' . (int)$it['id'])) ?>"><?= e($it['name']) ?></a></td>
            <td><?= e($it['supplier_name'] ?? '-') ?></td>
            <td><?= e((string)$it['on_hand']) ?></td>
            <td><?= e((string)$it['min_stock']) ?></td>
            <td>
              <?php if ($isLow): ?>
                <span class="badge danger">부족</span>
              <?php else: ?>
                <span class="badge ok">정상</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div style="margin-top:18px"></div>

  <div id="catalog-add" class="card" style="padding:12px">
    <div class="kpi">
      <div>
        <h2 class="h1" style="margin-bottom:4px">품목 추가(전체 물품에서 선택)</h2>
        <div class="muted">아래 전체 물품에서 “추가”를 누르면 위 선택 목록에 담기고, “삭제”로 취소할 수 있습니다.</div>
      </div>
      <div>
        <span class="badge">선택됨: <?= e((string)count($cartItems)) ?>건</span>
      </div>
    </div>

    <?php if (is_admin() && $supplierId <= 0): ?>
      <div class="muted" style="margin-top:10px">관리자는 상단 필터에서 거래처를 먼저 선택한 뒤 품목을 추가하세요.</div>
    <?php endif; ?>

    <div style="margin-top:12px"></div>

    <h3 class="h1">선택된 품목</h3>
    <?php if (!$cartItems): ?>
      <div class="muted" style="margin-top:8px">선택된 품목이 없습니다. 아래 전체 물품에서 선택하세요.</div>
    <?php else: ?>
      <table class="table" style="margin-top:10px">
        <thead>
          <tr>
            <th>SKU(기존)</th>
            <th>품목</th>
            <th>단위</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cartItems as $it): ?>
            <?php $tid = (int)$it['id']; ?>
            <tr>
              <td><?= e($it['sku']) ?></td>
              <td><?= e($it['name']) ?></td>
              <td><?= e($it['unit']) ?></td>
              <td style="white-space:nowrap">
                <form method="post" action="<?= e(url('/items.php?supplier_id=' . $supplierId . '&template_q=' . urlencode($templateQ) . '#catalog-add')) ?>" style="display:inline">
                  <input type="hidden" name="action" value="remove_template" />
                  <input type="hidden" name="template_item_id" value="<?= e((string)$tid) ?>" />
                  <input type="hidden" name="template_q" value="<?= e($templateQ) ?>" />
                  <button class="btn secondary" type="submit">삭제</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <form method="post" action="<?= e(url('/items.php')) ?>" style="margin-top:10px" onsubmit="return confirm('선택된 품목을 내 품목으로 추가할까요?')">
        <input type="hidden" name="action" value="submit_templates" />
        <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
        <button class="btn" type="submit">선택한 품목 추가</button>
        <div class="small" style="margin-top:8px">추가된 품목의 안전재고는 기본 <?= e((string)$fixedMinStock) ?>로 생성됩니다.</div>
      </form>
    <?php endif; ?>

    <div style="margin-top:18px"></div>

    <h3 class="h1">전체 물품</h3>
    <form method="get" action="<?= e(url('/items.php')) ?>" style="margin-top:10px">
      <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
      <div class="form-row">
        <div class="field" style="min-width:280px;flex:1">
          <div class="label">검색</div>
          <input class="input" type="text" name="template_q" value="<?= e($templateQ) ?>" placeholder="품목명 또는 SKU" />
        </div>
        <div class="field">
          <button class="btn" type="submit">검색</button>
        </div>
      </div>
    </form>

    <div class="small muted" style="margin-top:8px">아래 목록은 전체 품목이며, 검색어를 입력하면 필터링됩니다.</div>

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
        <?php if (!$templateItems): ?>
          <tr><td colspan="5" class="muted">표시할 품목이 없습니다.</td></tr>
        <?php else: ?>
          <?php foreach ($templateItems as $r): ?>
            <tr>
              <td><?= e($r['sku']) ?></td>
              <td><?= e($r['name']) ?></td>
              <td><?= e($r['unit']) ?></td>
              <td><?= e((string)$r['min_stock']) ?></td>
              <td style="white-space:nowrap">
                <?php if ($supplierId <= 0): ?>
                  <span class="muted">거래처 필요</span>
                <?php else: ?>
                  <form method="post" action="<?= e(url('/items.php?supplier_id=' . $supplierId . '&template_q=' . urlencode($templateQ) . '#catalog-add')) ?>" style="display:inline">
                    <input type="hidden" name="action" value="add_template" />
                    <input type="hidden" name="template_item_id" value="<?= e((string)$r['id']) ?>" />
                    <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
                    <input type="hidden" name="template_q" value="<?= e($templateQ) ?>" />
                    <button class="btn" type="submit">추가</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
