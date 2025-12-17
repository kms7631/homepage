<?php
declare(strict_types=1);

final class PurchaseOrder {
  public static function create(PDO $db, int $supplierId, int $orderedBy, string $orderDate, ?string $notes, array $items): int {
    // $items: [ ['item_id'=>int,'qty'=>int,'unit_cost'=>float] ... ]
    $clean = [];
    foreach ($items as $row) {
      $itemId = (int)($row['item_id'] ?? 0);
      $qty = (int)($row['qty'] ?? 0);
      if ($itemId <= 0 || $qty <= 0) {
        continue;
      }
      $clean[] = [
        'item_id' => $itemId,
        'qty' => $qty,
        'unit_cost' => (float)($row['unit_cost'] ?? 0),
      ];
    }
    if (count($clean) === 0) {
      throw new RuntimeException('발주 품목이 없습니다.');
    }

    $db->beginTransaction();
    try {
      $poNo = self::generateNo('PO');
      $st = $db->prepare('INSERT INTO purchase_orders (po_no, supplier_id, ordered_by, status, order_date, notes)
                          VALUES (?, ?, ?, \'OPEN\', ?, ?)');
      $st->execute([$poNo, $supplierId, $orderedBy, $orderDate, $notes]);
      $poId = (int)$db->lastInsertId();

      $stItem = $db->prepare('INSERT INTO purchase_order_items (purchase_order_id, item_id, qty, unit_cost)
                              VALUES (?, ?, ?, ?)');
      foreach ($clean as $row) {
        $stItem->execute([$poId, $row['item_id'], $row['qty'], $row['unit_cost']]);
      }

      $db->commit();
      return $poId;
    } catch (Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  public static function list(PDO $db, array $filters = []): array {
    $from = trim((string)($filters['from'] ?? ''));
    $to = trim((string)($filters['to'] ?? ''));
    $supplierId = (int)($filters['supplier_id'] ?? 0);
    $keyword = trim((string)($filters['keyword'] ?? ''));
    $status = trim((string)($filters['status'] ?? ''));

    $where = ['1=1'];
    $params = [];

    if ($from !== '') {
      $where[] = 'po.order_date >= ?';
      $params[] = $from;
    }
    if ($to !== '') {
      $where[] = 'po.order_date <= ?';
      $params[] = $to;
    }
    if ($supplierId > 0) {
      $where[] = 'po.supplier_id = ?';
      $params[] = $supplierId;
    }
    if ($status !== '') {
      $where[] = 'po.status = ?';
      $params[] = $status;
    }
    if ($keyword !== '') {
      $where[] = 'EXISTS (
          SELECT 1
          FROM purchase_order_items poi
          JOIN items it ON it.id = poi.item_id
          WHERE poi.purchase_order_id = po.id
            AND (it.name LIKE ? OR it.sku LIKE ?)
        )';
      $params[] = '%' . $keyword . '%';
      $params[] = '%' . $keyword . '%';
    }

    $sql = 'SELECT po.id, po.po_no, po.status, po.order_date, po.created_at,
             po.ordered_by AS ordered_by_id,
                   sp.name AS supplier_name,
                   u.name AS ordered_by_name,
                   (
                     SELECT r.id
                     FROM receipts r
                     WHERE r.purchase_order_id = po.id
                     ORDER BY r.id DESC
                     LIMIT 1
                   ) AS receipt_id,
                   (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS item_count,
                   (
                     SELECT it.name
                     FROM purchase_order_items poi
                     JOIN items it ON it.id = poi.item_id
                     WHERE poi.purchase_order_id = po.id
                     ORDER BY it.name ASC
                     LIMIT 1
                   ) AS first_item_name
            FROM purchase_orders po
            JOIN suppliers sp ON sp.id = po.supplier_id
            JOIN users u ON u.id = po.ordered_by
            WHERE ' . implode(' AND ', $where) .
            ' ORDER BY po.order_date DESC, po.id DESC
            LIMIT 200';

    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function find(PDO $db, int $id): ?array {
    $st = $db->prepare('SELECT po.*, sp.name AS supplier_name, u.name AS ordered_by_name
                        FROM purchase_orders po
                        JOIN suppliers sp ON sp.id = po.supplier_id
                        JOIN users u ON u.id = po.ordered_by
                        WHERE po.id = ?');
    $st->execute([$id]);
    $po = $st->fetch();
    if (!$po) {
      return null;
    }

    $stItems = $db->prepare('SELECT poi.*, it.sku, it.name, it.unit
                             FROM purchase_order_items poi
                             JOIN items it ON it.id = poi.item_id
                             WHERE poi.purchase_order_id = ?
                             ORDER BY it.name ASC');
    $stItems->execute([$id]);
    $po['items'] = $stItems->fetchAll();

    return $po;
  }

  public static function update(PDO $db, int $id, string $orderDate, ?string $notes, array $qtyByItemId): void {
    $clean = [];
    foreach ($qtyByItemId as $itemIdStr => $qtyVal) {
      $itemId = (int)$itemIdStr;
      $qty = (int)$qtyVal;
      if ($itemId <= 0) {
        continue;
      }
      if ($qty < 1) {
        throw new RuntimeException('수량은 1 이상이어야 합니다.');
      }
      $clean[$itemId] = $qty;
    }
    if (!$clean) {
      throw new RuntimeException('수정할 품목이 없습니다.');
    }

    $db->beginTransaction();
    try {
      $st = $db->prepare('UPDATE purchase_orders SET order_date = ?, notes = ? WHERE id = ?');
      $st->execute([$orderDate, $notes, $id]);

      $stItem = $db->prepare('UPDATE purchase_order_items SET qty = ? WHERE purchase_order_id = ? AND item_id = ?');
      foreach ($clean as $itemId => $qty) {
        $stItem->execute([$qty, $id, $itemId]);
      }

      $db->commit();
    } catch (Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  public static function delete(PDO $db, int $id): void {
    $st = $db->prepare('DELETE FROM purchase_orders WHERE id = ?');
    $st->execute([$id]);
  }

  public static function latest(PDO $db, int $limit = 5, int $supplierId = 0): array {
    $where = '1=1';
    if ($supplierId > 0) {
      $where = 'po.supplier_id = ?';
    }

    $sql = 'SELECT po.id, po.po_no, po.status, po.order_date, sp.name AS supplier_name
            FROM purchase_orders po
            JOIN suppliers sp ON sp.id = po.supplier_id
            WHERE ' . $where . '
            ORDER BY po.id DESC
            LIMIT ?';
    $st = $db->prepare($sql);
    $pos = 1;
    if ($supplierId > 0) {
      $st->bindValue($pos, $supplierId, PDO::PARAM_INT);
      $pos++;
    }
    $st->bindValue($pos, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  private static function generateNo(string $prefix): string {
    // 충돌 확률을 낮춘 간단한 번호
    return sprintf('%s-%s-%04d', $prefix, date('Ymd'), random_int(0, 9999));
  }
}
