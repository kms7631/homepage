<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();
require_once __DIR__ . '/includes/flatpickr_datepicker.php';

$db = db();

// 목록에서 바로 삭제(OPEN 상태 + 권한)
if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action !== 'delete') {
      throw new RuntimeException('잘못된 요청입니다.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      throw new RuntimeException('삭제할 발주가 올바르지 않습니다.');
    }

    $po = PurchaseOrder::find($db, $id);
    if (!$po) {
      throw new RuntimeException('발주를 찾을 수 없습니다.');
    }
    if ((string)($po['status'] ?? '') !== 'OPEN') {
      throw new RuntimeException('OPEN 상태의 발주만 삭제할 수 있습니다.');
    }

    if (!is_admin()) {
      $sid = (int)(current_supplier_id() ?? 0);
      if ($sid > 0 && (int)($po['supplier_id'] ?? 0) !== $sid) {
        throw new RuntimeException('접근 권한이 없습니다.');
      }
      if ((int)($po['ordered_by'] ?? 0) !== (int)current_user()['id']) {
        throw new RuntimeException('본인이 등록한 발주만 삭제할 수 있습니다.');
      }
    }

    // 안전장치: 연결된 입고가 있으면 삭제 불가
    $st = $db->prepare('SELECT COUNT(*) FROM receipts WHERE purchase_order_id = ?');
    $st->execute([$id]);
    $rcCnt = (int)$st->fetchColumn();
    if ($rcCnt > 0) {
      throw new RuntimeException('이미 입고 처리된 발주는 삭제할 수 없습니다.');
    }

    PurchaseOrder::delete($db, $id);
    flash_set('success', '발주가 삭제되었습니다.');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }

  // 상태/필터 유지해서 돌아가기
  $qs = [];
  foreach (['from', 'to', 'supplier_id', 'keyword', 'status'] as $k) {
    $v = trim((string)($_POST[$k] ?? ($_GET[$k] ?? '')));
    if ($v !== '' && $v !== '0') {
      $qs[$k] = $v;
    }
  }
  $qstr = $qs ? ('?' . http_build_query($qs)) : '';
  redirect('/po_list.php' . $qstr);
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$keyword = trim((string)($_GET['keyword'] ?? ''));
$statusKey = strtoupper(trim((string)($_GET['status'] ?? '')));
if (!in_array($statusKey, ['', 'ALL', 'OPEN', 'DONE', 'CANCEL'], true)) {
  $statusKey = '';
}
if ($statusKey === '') {
  $statusKey = 'ALL';
}

// DB status mapping (keep DB ENUM as-is)
$statusDb = '';
if ($statusKey === 'OPEN') {
  $statusDb = 'OPEN';
} elseif ($statusKey === 'DONE') {
  $statusDb = 'RECEIVED';
} elseif ($statusKey === 'CANCEL') {
  $statusDb = 'CANCELLED';
} elseif ($statusKey === 'ALL') {
  $statusDb = '';
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
  if ($dbStatus === 'OPEN') {
    return 'accent';
  }
  if ($dbStatus === 'RECEIVED') {
    return 'ok';
  }
  if ($dbStatus === 'CANCELLED') {
    return 'danger';
  }
  return '';
}

if (!is_admin()) {
  $supplierId = (int)(current_supplier_id() ?? 0);
}

$suppliers = is_admin() ? Supplier::listAll($db) : [];
$pos = PurchaseOrder::list($db, [
  'from' => $from,
  'to' => $to,
  'supplier_id' => $supplierId,
  'keyword' => $keyword,
  'status' => $statusDb,
]);

$extraHeadHtml = flatpickr_datepicker_head_html();
$extraBodyEndHtml = flatpickr_datepicker_body_html('input.js-date');

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">발주 조회</h1>
      <div class="muted">기간/거래처/품목명 키워드로 발주를 검색합니다.</div>
    </div>
    <div>
      <a class="btn" href="<?= e(url('/po_create.php')) ?>">발주 등록</a>
    </div>
  </div>

  <div style="margin-top:10px"></div>

  <div class="form-row" style="align-items:center">
    <div class="field" style="flex:1"></div>
  </div>

  <form method="get" action="<?= e(url('/po_list.php')) ?>" style="margin-top:12px">
    <div class="form-row">
      <div class="field" style="min-width:160px">
        <div class="label">상태</div>
        <select name="status">
          <option value="ALL" <?= $statusKey === 'ALL' ? 'selected' : '' ?>>전체</option>
          <option value="OPEN" <?= $statusKey === 'OPEN' ? 'selected' : '' ?>>진행 중</option>
          <option value="DONE" <?= $statusKey === 'DONE' ? 'selected' : '' ?>>입고</option>
          <option value="CANCEL" <?= $statusKey === 'CANCEL' ? 'selected' : '' ?>>취소</option>
        </select>
      </div>
      <div class="field">
        <div class="label">기간(From)</div>
        <input class="input js-date" type="text" name="from" value="<?= e($from) ?>" placeholder="YYYY-MM-DD" autocomplete="off" />
      </div>
      <div class="field">
        <div class="label">기간(To)</div>
        <input class="input js-date" type="text" name="to" value="<?= e($to) ?>" placeholder="YYYY-MM-DD" autocomplete="off" />
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
      <div class="field" style="min-width:220px">
        <div class="label">키워드(품목명/SKU)</div>
        <input class="input" type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="예: 박스 또는 SKU" />
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
        <th>발주번호</th>
        <th>거래처</th>
        <th>일자</th>
        <th>상태</th>
        <th>대표 품목</th>
        <th>등록자</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$pos): ?>
      <tr><td colspan="7" class="muted">검색 결과가 없습니다.</td></tr>
    <?php else: ?>
      <?php foreach ($pos as $po): ?>
        <?php
          $cnt = (int)$po['item_count'];
          $first = (string)($po['first_item_name'] ?? '');
          $summary = '-';
          if ($cnt > 0 && $first !== '') {
            $summary = $cnt > 1 ? ($first . ' 외 ' . ($cnt - 1) . '건') : $first;
          }
        ?>
        <tr>
          <td><a href="<?= e(url('/po_view.php?id=' . (int)$po['id'])) ?>"><?= e($po['po_no']) ?></a></td>
          <td><?= e($po['supplier_name']) ?></td>
          <td><?= e($po['order_date']) ?></td>
          <td>
            <?php $badgeClass = po_status_badge_class((string)($po['status'] ?? '')); ?>
            <span class="badge <?= e($badgeClass) ?>"><?= e(po_status_label((string)($po['status'] ?? ''))) ?></span>
          </td>
          <td><?= e($summary) ?></td>
          <td><?= e($po['ordered_by_name']) ?></td>
          <td>
            <?php
              $canEdit = false;
              if ((string)($po['status'] ?? '') === 'OPEN') {
                if (is_admin()) {
                  $canEdit = true;
                } else {
                  $canEdit = (int)($po['ordered_by_id'] ?? 0) === (int)current_user()['id'];
                }
              }
              $receiptId = (int)($po['receipt_id'] ?? 0);
            ?>
            <?php if ($canEdit): ?>
              <a class="btn secondary" href="<?= e(url('/po_edit.php?id=' . (int)$po['id'])) ?>">수정</a>
              <form method="post" action="<?= e(url('/po_list.php')) ?>" style="display:inline" onsubmit="return confirm('정말 이 발주를 삭제할까요? (되돌릴 수 없습니다)')">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?= e((string)$po['id']) ?>" />
                <input type="hidden" name="from" value="<?= e($from) ?>" />
                <input type="hidden" name="to" value="<?= e($to) ?>" />
                <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
                <input type="hidden" name="keyword" value="<?= e($keyword) ?>" />
                <input type="hidden" name="status" value="<?= e($statusKey) ?>" />
                <button class="btn secondary" type="submit">삭제</button>
              </form>
            <?php elseif ((string)($po['status'] ?? '') === 'RECEIVED' && $receiptId > 0): ?>
              <a class="btn secondary" href="<?= e(url('/receipt_view.php?id=' . $receiptId)) ?>">입고 내역</a>
            <?php else: ?>
              <span class="muted">-</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
