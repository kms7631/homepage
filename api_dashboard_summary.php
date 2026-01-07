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

  $where = ["po.order_date >= ?", "po.order_date <= ?", "po.status <> 'CANCELLED'"];
  $params = [$from, $to];

  if ($supplierId > 0) {
    $where[] = 'po.supplier_id = ?';
    $params[] = $supplierId;
  }

  $sql = "
    SELECT
      COALESCE(SUM(t.ordered_qty), 0) AS ordered_qty,
      COALESCE(SUM(t.received_qty), 0) AS received_qty,
      COALESCE(SUM(GREATEST(t.ordered_qty - t.received_qty, 0)), 0) AS remaining_qty,
      COALESCE(SUM(CASE WHEN t.received_qty >= t.ordered_qty THEN t.ordered_qty ELSE 0 END), 0) AS ordered_complete_qty,
      COALESCE(SUM(CASE WHEN t.received_qty > 0 AND t.received_qty < t.ordered_qty THEN t.ordered_qty ELSE 0 END), 0) AS ordered_partial_qty,
      COALESCE(SUM(CASE WHEN t.received_qty = 0 THEN t.ordered_qty ELSE 0 END), 0) AS ordered_none_qty
    FROM (
      SELECT
        poi.qty AS ordered_qty,
        LEAST(COALESCE(SUM(ri.qty_received), 0), poi.qty) AS received_qty
      FROM purchase_orders po
      JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
      LEFT JOIN receipts r ON r.purchase_order_id = po.id
      LEFT JOIN receipt_items ri ON ri.receipt_id = r.id AND ri.item_id = poi.item_id
      WHERE " . implode(' AND ', $where) . "
      GROUP BY po.id, poi.item_id
    ) t
  ";

  $st = $db->prepare($sql);
  $st->execute($params);
  $row = $st->fetch() ?: [];

  $orderedQty = (int)($row['ordered_qty'] ?? 0);
  $receivedQty = (int)($row['received_qty'] ?? 0);
  $remainingQty = (int)($row['remaining_qty'] ?? 0);
  $orderedCompleteQty = (int)($row['ordered_complete_qty'] ?? 0);
  $orderedPartialQty = (int)($row['ordered_partial_qty'] ?? 0);
  $orderedNoneQty = (int)($row['ordered_none_qty'] ?? 0);

  $avgRate = 0.0;
  if ($orderedQty > 0) {
    $avgRate = round(($receivedQty / $orderedQty) * 100, 1);
  }

  dashboard_json([
    'ok' => true,
    'range' => $rangeInfo['range'],
    'from' => $from,
    'to' => $to,
    'scope' => [
      'is_admin' => is_admin(),
      'supplier_id' => $supplierId,
    ],
    'kpi' => [
      'ordered_qty' => $orderedQty,
      'received_qty' => $receivedQty,
      'avg_receive_rate' => $avgRate,
      'remaining_qty' => $remainingQty,
    ],
    'donut' => [
      'labels' => ['입고완료', '부분입고', '미입고/지연'],
      'keys' => ['complete', 'partial', 'none'],
      'values' => [$orderedCompleteQty, $orderedPartialQty, $orderedNoneQty],
      'center_rate' => $avgRate,
    ],
  ]);
} catch (Throwable $e) {
  dashboard_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
