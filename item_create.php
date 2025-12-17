<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();

$suppliers = is_admin() ? Supplier::listAll($db) : [];

$supplierId = (int)($_GET['supplier_id'] ?? ($_POST['supplier_id'] ?? 0));
if (!is_admin()) {
  $supplierId = (int)(current_supplier_id() ?? 0);
}

$sku = trim((string)($_POST['sku'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$unit = trim((string)($_POST['unit'] ?? 'EA'));
$minStock = (int)($_POST['min_stock'] ?? 0);

if (is_post()) {
  try {
    if ($supplierId <= 0) {
      throw new RuntimeException('거래처를 선택하세요.');
    }
    if ($sku === '' || $name === '') {
      throw new RuntimeException('SKU와 품목명을 입력하세요.');
    }

    $id = Item::create($db, [
      'sku' => $sku,
      'name' => $name,
      'supplier_id' => $supplierId,
      'unit' => $unit ?: 'EA',
      'min_stock' => $minStock,
    ]);

    flash_set('success', '품목이 추가되었습니다.');
    redirect('/item_view.php?id=' . $id);
  } catch (PDOException $e) {
    // SKU unique 또는 FK 등
    error_log('[item_create] PDOException: ' . $e->getMessage());
    flash_set('error', '저장할 수 없습니다. SKU가 중복되었거나 입력값이 올바르지 않을 수 있습니다.');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">품목 추가</h1>
      <div class="muted">새 품목을 등록한 뒤 발주/입고에 사용할 수 있습니다.</div>
    </div>
    <div>
      <a class="btn secondary" href="<?= e(url('/items.php')) ?>">목록</a>
    </div>
  </div>

  <form method="post" action="<?= e(url('/item_create.php')) ?>" style="margin-top:12px">
    <div class="form-row">
      <?php if (is_admin()): ?>
        <div class="field">
          <div class="label">거래처</div>
          <select name="supplier_id" required>
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

      <div class="field" style="min-width:220px">
        <div class="label">SKU</div>
        <input class="input" type="text" name="sku" value="<?= e($sku) ?>" placeholder="예: RM-AL" required />
      </div>

      <div class="field" style="min-width:260px;flex:1">
        <div class="label">품목명</div>
        <input class="input" type="text" name="name" value="<?= e($name) ?>" placeholder="예: 알루미늄" required />
      </div>

      <div class="field" style="min-width:140px">
        <div class="label">단위</div>
        <input class="input" type="text" name="unit" value="<?= e($unit ?: 'EA') ?>" placeholder="EA" />
      </div>

      <div class="field" style="min-width:140px">
        <div class="label">안전재고</div>
        <input class="input" type="number" min="0" name="min_stock" value="<?= e((string)$minStock) ?>" />
      </div>

      <div class="field">
        <button class="btn" type="submit">추가</button>
      </div>
    </div>

    <div class="small" style="margin-top:10px">SKU는 시스템 전체에서 유일해야 합니다. (중복이면 저장이 실패합니다)</div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
