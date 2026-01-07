<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();
require_once __DIR__ . '/includes/dashboard.php';

try {
  $db = db();

  $rangeInfo = dashboard_parse_range((string)($_GET['range'] ?? '30d'));
  $requestedSupplierId = (int)($_GET['supplier_id'] ?? 0);
  $supplierId = dashboard_effective_supplier_id($requestedSupplierId);

  $from = $rangeInfo['from'];
  $to = $rangeInfo['to'];

  $limit = (int)($_GET['limit'] ?? 8);
  if ($limit < 3) {
    $limit = 3;
  }
  if ($limit > 10) {
    $limit = 10;
  }

  $where = ["po.order_date >= ?", "po.order_date <= ?", "po.status <> 'CANCELLED'"];
  $params = [$from, $to];

  if ($supplierId > 0) {
    $where[] = 'po.supplier_id = ?';
    $params[] = $supplierId;
  }

  if (is_admin()) {
    $sql = "
      SELECT
        t.supplier_id,
        t.supplier_name,
        SUM(t.ordered_qty) AS ordered_qty,
        SUM(t.received_qty) AS received_qty,
        ROUND((SUM(t.received_qty) / NULLIF(SUM(t.ordered_qty), 0)) * 100, 1) AS receive_rate
      FROM (
        SELECT
          po.supplier_id AS supplier_id,
          sp.name AS supplier_name,
          poi.qty AS ordered_qty,
          LEAST(COALESCE(SUM(ri.qty_received), 0), poi.qty) AS received_qty
        FROM purchase_orders po
        JOIN suppliers sp ON sp.id = po.supplier_id
        JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
        LEFT JOIN receipts r ON r.purchase_order_id = po.id
        LEFT JOIN receipt_items ri ON ri.receipt_id = r.id AND ri.item_id = poi.item_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY po.id, poi.item_id
      ) t
      GROUP BY t.supplier_id
      ORDER BY receive_rate DESC, ordered_qty DESC
      LIMIT " . (int)$limit . "
    ";

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $labels = [];
    $values = [];
    $ids = [];
    foreach ($rows as $r) {
      $labels[] = (string)($r['supplier_name'] ?? '');
      $values[] = (float)($r['receive_rate'] ?? 0);
      $ids[] = (int)($r['supplier_id'] ?? 0);
    }

    dashboard_json([
      'ok' => true,
      'type' => 'admin',
      'range' => $rangeInfo['range'],
      'from' => $from,
      'to' => $to,
      'bar' => [
        'labels' => $labels,
        'values' => $values,
        'ids' => $ids,
        'metric' => 'receive_rate',
      ],
    ]);
  }

  // vendor: 내 발주 품목 TOP N (발주수량 기준)
  $sql = "
    SELECT
      t.item_id,
      t.item_name,
      SUM(t.ordered_qty) AS ordered_qty,
      SUM(t.received_qty) AS received_qty,
      ROUND((SUM(t.received_qty) / NULLIF(SUM(t.ordered_qty), 0)) * 100, 1) AS receive_rate
    FROM (
      SELECT
        poi.item_id AS item_id,
        it.name AS item_name,
        poi.qty AS ordered_qty,
        LEAST(COALESCE(SUM(ri.qty_received), 0), poi.qty) AS received_qty
      FROM purchase_orders po
      JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
      JOIN items it ON it.id = poi.item_id
      LEFT JOIN receipts r ON r.purchase_order_id = po.id
      LEFT JOIN receipt_items ri ON ri.receipt_id = r.id AND ri.item_id = poi.item_id
      WHERE " . implode(' AND ', $where) . "
      GROUP BY po.id, poi.item_id
    ) t
    GROUP BY t.item_id
    ORDER BY ordered_qty DESC
    LIMIT " . (int)$limit . "
  ";

  $st = $db->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();

  $labels = [];
  $values = [];
  $ids = [];
  foreach ($rows as $r) {
    $labels[] = (string)($r['item_name'] ?? '');
    $values[] = (int)($r['ordered_qty'] ?? 0);
    $ids[] = (int)($r['item_id'] ?? 0);
  }

  dashboard_json([
    'ok' => true,
    'type' => 'vendor',
    'range' => $rangeInfo['range'],
    'from' => $from,
    'to' => $to,
    'bar' => [
      'labels' => $labels,
      'values' => $values,
      'ids' => $ids,
      'metric' => 'ordered_qty',
    ],
  ]);
} catch (Throwable $e) {
  error_log('[API_ERROR] api_dashboard_bar.php ' . $e->getMessage());
  $status = ($e instanceof InvalidArgumentException) ? 400 : 500;
  dashboard_json(['ok' => false, 'error' => '요청 처리 중 오류가 발생했습니다.'], $status);
}
