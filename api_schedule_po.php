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
    // day detail: purchase orders + receipts
    $events = [];

    $wherePo = ["po.order_date = ?", "po.status <> 'CANCELLED'"];
    $paramsPo = [$date];
    if ($supplierId > 0) {
      $wherePo[] = 'po.supplier_id = ?';
      $paramsPo[] = $supplierId;
    }

    $sqlPo = 'SELECT po.id, po.po_no, po.status, po.order_date, sp.name AS supplier_name
              FROM purchase_orders po
              JOIN suppliers sp ON sp.id = po.supplier_id
              WHERE ' . implode(' AND ', $wherePo) . '
              ORDER BY po.id DESC
              LIMIT ' . (int)$limitDay;
    $st = $db->prepare($sqlPo);
    $st->execute($paramsPo);
    foreach (($st->fetchAll() ?: []) as $r) {
      $events[] = [
        'type' => 'po',
        'id' => (int)($r['id'] ?? 0),
        'po_no' => (string)($r['po_no'] ?? ''),
        'status' => (string)($r['status'] ?? ''),
        'supplier_name' => (string)($r['supplier_name'] ?? ''),
      ];
    }

    $whereRc = ['r.receipt_date = ?'];
    $paramsRc = [$date];
    if ($supplierId > 0) {
      $whereRc[] = 'r.supplier_id = ?';
      $paramsRc[] = $supplierId;
    }

    $sqlRc = 'SELECT r.id, r.receipt_no, r.receipt_date, r.purchase_order_id, sp.name AS supplier_name
              FROM receipts r
              JOIN suppliers sp ON sp.id = r.supplier_id
              WHERE ' . implode(' AND ', $whereRc) . '
              ORDER BY r.id DESC
              LIMIT ' . (int)$limitDay;
    $st = $db->prepare($sqlRc);
    $st->execute($paramsRc);
    foreach (($st->fetchAll() ?: []) as $r) {
      $events[] = [
        'type' => 'receipt',
        'id' => (int)($r['id'] ?? 0),
        'receipt_no' => (string)($r['receipt_no'] ?? ''),
        'purchase_order_id' => (int)($r['purchase_order_id'] ?? 0),
        'supplier_name' => (string)($r['supplier_name'] ?? ''),
      ];
    }

    usort($events, function (array $a, array $b): int {
      $ta = (string)($a['type'] ?? '');
      $tb = (string)($b['type'] ?? '');
      if ($ta !== $tb) {
        // PO first, then receipts
        return ($ta === 'po' ? -1 : 1);
      }
      return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
    });

    schedule_json([
      'ok' => true,
      'mode' => 'day',
      'date' => $date,
      'scope' => [
        'is_admin' => is_admin(),
        'supplier_id' => $supplierId,
      ],
      'events' => $events,
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
      'type' => 'po',
      'id' => (int)($r['id'] ?? 0),
      'po_no' => (string)($r['po_no'] ?? ''),
      'status' => (string)($r['status'] ?? ''),
      'supplier_name' => (string)($r['supplier_name'] ?? ''),
    ];
  }

  // receipts for the month
  $whereRc = ['r.receipt_date >= ?', 'r.receipt_date <= ?'];
  $paramsRc = [$monthInfo['from'], $monthInfo['to']];
  if ($supplierId > 0) {
    $whereRc[] = 'r.supplier_id = ?';
    $paramsRc[] = $supplierId;
  }

  $sqlRc = 'SELECT r.id, r.receipt_no, r.receipt_date, r.purchase_order_id, sp.name AS supplier_name
            FROM receipts r
            JOIN suppliers sp ON sp.id = r.supplier_id
            WHERE ' . implode(' AND ', $whereRc) . '
            ORDER BY r.receipt_date ASC, r.id ASC
            LIMIT ' . (int)$limitMonth;
  $st = $db->prepare($sqlRc);
  $st->execute($paramsRc);
  $rcRows = $st->fetchAll();

  foreach (($rcRows ?: []) as $r) {
    $d = (string)($r['receipt_date'] ?? '');
    if ($d === '') {
      continue;
    }
    if (!isset($byDate[$d])) {
      $byDate[$d] = [];
    }
    $byDate[$d][] = [
      'type' => 'receipt',
      'id' => (int)($r['id'] ?? 0),
      'receipt_no' => (string)($r['receipt_no'] ?? ''),
      'purchase_order_id' => (int)($r['purchase_order_id'] ?? 0),
      'supplier_name' => (string)($r['supplier_name'] ?? ''),
    ];
  }

  foreach ($byDate as $d => $arr) {
    usort($arr, function (array $a, array $b): int {
      $ta = (string)($a['type'] ?? '');
      $tb = (string)($b['type'] ?? '');
      if ($ta !== $tb) {
        return ($ta === 'po' ? -1 : 1);
      }
      return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    });
    $byDate[$d] = $arr;
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
      'returned_rows' => count($rows) + count($rcRows ?: []),
    ],
  ]);
} catch (Throwable $e) {
  schedule_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
