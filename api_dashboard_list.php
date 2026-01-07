<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();
require_once __DIR__ . '/includes/dashboard.php';

try {
  $db = db();

  $rangeInfo = dashboard_parse_range((string)($_GET['range'] ?? '30d'));
  $requestedSupplierId = (int)($_GET['supplier_id'] ?? 0);
  $supplierId = dashboard_effective_supplier_id($requestedSupplierId);

  $status = trim((string)($_GET['status'] ?? ''));
  if (!in_array($status, ['', 'complete', 'partial', 'none'], true)) {
    $status = '';
  }

  $itemId = (int)($_GET['item_id'] ?? 0);
  if ($itemId < 0) {
    $itemId = 0;
  }

  $from = $rangeInfo['from'];
  $to = $rangeInfo['to'];

  $where = ["po.order_date >= ?", "po.order_date <= ?", "po.status <> 'CANCELLED'"];
  $params = [$from, $to];

  if ($supplierId > 0) {
    $where[] = 'po.supplier_id = ?';
    $params[] = $supplierId;
  }

  $sqlBase = "
    SELECT
      x.po_id,
      x.po_no,
      x.order_date,
      x.supplier_id,
      x.supplier_name,
      x.item_id,
      x.item_name,
      x.ordered_qty,
      x.received_qty,
      x.remaining_qty,
      x.receive_rate,
      x.last_receipt_date,
      x.line_status
    FROM (
      SELECT
        po.id AS po_id,
        po.po_no AS po_no,
        po.order_date AS order_date,
        po.supplier_id AS supplier_id,
        sp.name AS supplier_name,
        poi.item_id AS item_id,
        it.name AS item_name,
        poi.qty AS ordered_qty,
        LEAST(COALESCE(SUM(ri.qty_received), 0), poi.qty) AS received_qty,
        GREATEST(poi.qty - LEAST(COALESCE(SUM(ri.qty_received), 0), poi.qty), 0) AS remaining_qty,
        ROUND((LEAST(COALESCE(SUM(ri.qty_received), 0), poi.qty) / NULLIF(poi.qty, 0)) * 100, 1) AS receive_rate,
        MAX(r.receipt_date) AS last_receipt_date,
        CASE
          WHEN LEAST(COALESCE(SUM(ri.qty_received), 0), poi.qty) >= poi.qty THEN 'complete'
          WHEN LEAST(COALESCE(SUM(ri.qty_received), 0), poi.qty) > 0 THEN 'partial'
          ELSE 'none'
        END AS line_status
      FROM purchase_orders po
      JOIN suppliers sp ON sp.id = po.supplier_id
      JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
      JOIN items it ON it.id = poi.item_id
      LEFT JOIN receipts r ON r.purchase_order_id = po.id
      LEFT JOIN receipt_items ri ON ri.receipt_id = r.id AND ri.item_id = poi.item_id
      WHERE " . implode(' AND ', $where) . "
      GROUP BY po.id, poi.item_id
    ) x
    WHERE 1=1
  ";

  $where2 = [];
  $params2 = [];

  // vendor 기본: 진행중(잔량>0)
  // admin 기본: 지연/미입고 Top (잔량>0)
  if ($status !== '') {
    $where2[] = 'x.line_status = ?';
    $params2[] = $status;
  } else {
    $where2[] = 'x.remaining_qty > 0';
  }

  if (!is_admin()) {
    // vendor drilldown: item_id만 허용
    if ($itemId > 0) {
      $where2[] = 'x.item_id = ?';
      $params2[] = $itemId;
    }
  } else {
    // admin에서도 item_id 필터는 허용(막대 클릭은 supplier 중심이지만 확장 가능)
    if ($itemId > 0) {
      $where2[] = 'x.item_id = ?';
      $params2[] = $itemId;
    }
  }

  if ($where2) {
    $sqlBase .= ' AND ' . implode(' AND ', $where2);
  }

  if (is_admin()) {
    $sqlBase .= ' ORDER BY x.remaining_qty DESC, x.order_date ASC, x.po_id DESC LIMIT 200';
  } else {
    $sqlBase .= ' ORDER BY x.remaining_qty DESC, x.order_date ASC, x.po_id DESC LIMIT 200';
  }

  $st = $db->prepare($sqlBase);
  $st->execute(array_merge($params, $params2));
  $rows = $st->fetchAll();

  dashboard_json([
    'ok' => true,
    'range' => $rangeInfo['range'],
    'from' => $from,
    'to' => $to,
    'filters' => [
      'status' => $status,
      'supplier_id' => $supplierId,
      'item_id' => $itemId,
    ],
    'rows' => $rows,
  ]);
} catch (Throwable $e) {
  dashboard_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
