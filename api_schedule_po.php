<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

function schedule_parse_month(string $month): array {
  $month = trim($month);
  if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
  }

  $tz = new DateTimeZone(APP_TIMEZONE);
  $first = new DateTimeImmutable($month . '-01', $tz);
  $last = $first->modify('last day of this month');

  return [
    'month' => $first->format('Y-m'),
    'from' => $first->format('Y-m-d'),
    'to' => $last->format('Y-m-d'),
  ];
}

function schedule_parse_date(string $date): ?string {
  $date = trim($date);
  if ($date === '') {
    return null;
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    return null;
  }
  return $date;
}

function schedule_effective_supplier_id(int $requestedSupplierId): int {
  if (is_admin()) {
    return $requestedSupplierId > 0 ? $requestedSupplierId : 0;
  }
  return (int)(current_supplier_id() ?? 0);
}

function schedule_json(array $payload, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $db = db();

  $monthInfo = schedule_parse_month((string)($_GET['month'] ?? ''));
  $date = schedule_parse_date((string)($_GET['date'] ?? ''));

  $requestedSupplierId = (int)($_GET['supplier_id'] ?? 0);
  $supplierId = schedule_effective_supplier_id($requestedSupplierId);

  $limitMonth = 400;
  $limitDay = 200;

  if ($date !== null) {
    $where = ["po.order_date = ?", "po.status <> 'CANCELLED'"];
    $params = [$date];

    if ($supplierId > 0) {
      $where[] = 'po.supplier_id = ?';
      $params[] = $supplierId;
    }

    $sql = 'SELECT po.id, po.po_no, po.status, po.order_date, sp.name AS supplier_name
            FROM purchase_orders po
            JOIN suppliers sp ON sp.id = po.supplier_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY po.id DESC
            LIMIT ' . (int)$limitDay;

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    schedule_json([
      'ok' => true,
      'mode' => 'day',
      'date' => $date,
      'scope' => [
        'is_admin' => is_admin(),
        'supplier_id' => $supplierId,
      ],
      'rows' => $rows,
    ]);
  }

  // month summary
  $where = ["po.order_date >= ?", "po.order_date <= ?", "po.status <> 'CANCELLED'"];
  $params = [$monthInfo['from'], $monthInfo['to']];

  if ($supplierId > 0) {
    $where[] = 'po.supplier_id = ?';
    $params[] = $supplierId;
  }

  $sql = 'SELECT po.id, po.po_no, po.status, po.order_date, sp.name AS supplier_name
          FROM purchase_orders po
          JOIN suppliers sp ON sp.id = po.supplier_id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY po.order_date ASC, po.id ASC
          LIMIT ' . (int)$limitMonth;

  $st = $db->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();

  $byDate = [];
  foreach ($rows as $r) {
    $d = (string)($r['order_date'] ?? '');
    if ($d === '') {
      continue;
    }
    if (!isset($byDate[$d])) {
      $byDate[$d] = [];
    }
    $byDate[$d][] = [
      'id' => (int)($r['id'] ?? 0),
      'po_no' => (string)($r['po_no'] ?? ''),
      'status' => (string)($r['status'] ?? ''),
      'supplier_name' => (string)($r['supplier_name'] ?? ''),
    ];
  }

  schedule_json([
    'ok' => true,
    'mode' => 'month',
    'month' => $monthInfo['month'],
    'from' => $monthInfo['from'],
    'to' => $monthInfo['to'],
    'scope' => [
      'is_admin' => is_admin(),
      'supplier_id' => $supplierId,
    ],
    'days' => $byDate,
    'meta' => [
      'limit_month' => $limitMonth,
      'returned_rows' => count($rows),
    ],
  ]);
} catch (Throwable $e) {
  schedule_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
