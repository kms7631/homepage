<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_supplier();

function schedule_events_parse_date(string $s): ?string {
  $s = trim($s);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
    return null;
  }
  return $s;
}

function schedule_events_effective_supplier_id(int $requestedSupplierId): int {
  if (is_admin()) {
    return $requestedSupplierId > 0 ? $requestedSupplierId : 0;
  }
  return (int)(current_supplier_id() ?? 0);
}

function schedule_events_json(array $payload, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $start = schedule_events_parse_date((string)($_GET['start'] ?? ''));
  $end = schedule_events_parse_date((string)($_GET['end'] ?? ''));
  if (!$start || !$end) {
    throw new RuntimeException('start/end(YYYY-MM-DD)가 필요합니다.');
  }

  // sanity: limit range (~45 days)
  $tz = new DateTimeZone(APP_TIMEZONE);
  $ds = new DateTimeImmutable($start, $tz);
  $de = new DateTimeImmutable($end, $tz);
  if ($de < $ds) {
    throw new RuntimeException('end는 start 이후여야 합니다.');
  }
  $days = (int)$de->diff($ds)->format('%a');
  if ($days > 45) {
    throw new RuntimeException('조회 범위가 너무 큽니다. (최대 45일)');
  }

  $db = db();

  $requestedSupplierId = (int)($_GET['supplier_id'] ?? 0);
  $supplierId = schedule_events_effective_supplier_id($requestedSupplierId);

  $events = [];

  // PO: 발주 신청일 = created_at
  $wherePo = ["po.created_at >= ?", "po.created_at < DATE_ADD(?, INTERVAL 1 DAY)", "po.status <> 'CANCELLED'"];
  $paramsPo = [$start . ' 00:00:00', $end . ' 00:00:00'];
  if ($supplierId > 0) {
    $wherePo[] = 'po.supplier_id = ?';
    $paramsPo[] = $supplierId;
  }

  $sqlPo = 'SELECT po.id, po.po_no, po.status, po.supplier_id, sp.name AS supplier_name,
                   DATE(po.created_at) AS event_date
            FROM purchase_orders po
            JOIN suppliers sp ON sp.id = po.supplier_id
            WHERE ' . implode(' AND ', $wherePo) . '
            ORDER BY po.created_at ASC, po.id ASC
            LIMIT 1000';

  $st = $db->prepare($sqlPo);
  $st->execute($paramsPo);
  foreach (($st->fetchAll() ?: []) as $r) {
    $poId = (int)($r['id'] ?? 0);
    $poNo = (string)($r['po_no'] ?? '');
    $date = (string)($r['event_date'] ?? '');
    $events[] = [
      'id' => 'PO:' . $poId,
      'type' => 'po',
      'title' => '발주신청 ' . ($poNo !== '' ? $poNo : ('PO#' . $poId)),
      'date' => $date,
      'ref_id' => $poId,
      'po_id' => $poId,
      'receipt_id' => null,
      'purchase_order_id' => null,
      'supplier_id' => (int)($r['supplier_id'] ?? 0),
      'supplier_name' => (string)($r['supplier_name'] ?? ''),
      'color' => 'accent',
    ];
  }

  // Receipt: 입고 완료일 = created_at (received_at 컬럼이 없으므로)
  $whereRc = ["r.created_at >= ?", "r.created_at < DATE_ADD(?, INTERVAL 1 DAY)"];
  $paramsRc = [$start . ' 00:00:00', $end . ' 00:00:00'];
  if ($supplierId > 0) {
    $whereRc[] = 'r.supplier_id = ?';
    $paramsRc[] = $supplierId;
  }

  $sqlRc = 'SELECT r.id, r.receipt_no, r.purchase_order_id, r.supplier_id, sp.name AS supplier_name,
                   DATE(r.created_at) AS event_date
            FROM receipts r
            JOIN suppliers sp ON sp.id = r.supplier_id
            WHERE ' . implode(' AND ', $whereRc) . '
            ORDER BY r.created_at ASC, r.id ASC
            LIMIT 1000';

  $st = $db->prepare($sqlRc);
  $st->execute($paramsRc);
  foreach (($st->fetchAll() ?: []) as $r) {
    $rcId = (int)($r['id'] ?? 0);
    $rcNo = (string)($r['receipt_no'] ?? '');
    $date = (string)($r['event_date'] ?? '');
    $poId = (int)($r['purchase_order_id'] ?? 0);
    $events[] = [
      'id' => 'RECEIPT:' . $rcId,
      'type' => 'receipt',
      'title' => '입고완료 ' . ($rcNo !== '' ? $rcNo : (string)$rcId),
      'date' => $date,
      'ref_id' => $rcId,
      'po_id' => null,
      'receipt_id' => $rcId,
      'purchase_order_id' => $poId > 0 ? $poId : null,
      'supplier_id' => (int)($r['supplier_id'] ?? 0),
      'supplier_name' => (string)($r['supplier_name'] ?? ''),
      'color' => 'ok',
    ];
  }

  schedule_events_json([
    'ok' => true,
    'start' => $start,
    'end' => $end,
    'scope' => [
      'is_admin' => is_admin(),
      'supplier_id' => $supplierId,
    ],
    'events' => $events,
  ]);
} catch (Throwable $e) {
  schedule_events_json(['ok' => false, 'error' => $e->getMessage()], 400);
}
