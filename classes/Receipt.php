<?php
declare(strict_types=1);

final class Receipt {
  public static function create(PDO $db, int $supplierId, int $receivedBy, string $receiptDate, ?string $notes, ?int $purchaseOrderId, array $items): int {
    // $items: [ ['item_id'=>int,'qty_received'=>int] ... ]
    $clean = [];
    foreach ($items as $row) {
      $itemId = (int)($row['item_id'] ?? 0);
      $qty = (int)($row['qty_received'] ?? 0);
      if ($itemId <= 0 || $qty <= 0) {
        continue;
      }
      $clean[] = ['item_id' => $itemId, 'qty_received' => $qty];
    }
    if (count($clean) === 0) {
      throw new RuntimeException('입고 품목이 없습니다.');
    }

    $db->beginTransaction();
    try {
      $receiptNo = self::generateNo('RC');
      $st = $db->prepare('INSERT INTO receipts (receipt_no, purchase_order_id, supplier_id, received_by, receipt_date, notes)
                          VALUES (?, ?, ?, ?, ?, ?)');
      $st->execute([
        $receiptNo,
        $purchaseOrderId,
        $supplierId,
        $receivedBy,
        $receiptDate,
        $notes,
      ]);
      $receiptId = (int)$db->lastInsertId();

      $stItem = $db->prepare('INSERT INTO receipt_items (receipt_id, item_id, qty_received)
                              VALUES (?, ?, ?)');

      foreach ($clean as $row) {
        $stItem->execute([$receiptId, $row['item_id'], $row['qty_received']]);
        Inventory::adjust($db, $row['item_id'], $row['qty_received']);
      }

      // 단순화: PO 연결 입고는 완료 상태로 처리 (UI에서는 DONE으로 표시)
      if ($purchaseOrderId && $purchaseOrderId > 0) {
        $stUp = $db->prepare("UPDATE purchase_orders SET status='RECEIVED' WHERE id = ? AND supplier_id = ? AND status='OPEN'");
        $stUp->execute([$purchaseOrderId, $supplierId]);
        if ($stUp->rowCount() !== 1) {
          throw new RuntimeException('발주 상태가 OPEN이 아니거나 접근 권한이 없습니다.');
        }
      }

      $db->commit();
      return $receiptId;
    } catch (Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  public static function list(PDO $db, array $filters = []): array {
    $from = trim((string)($filters['from'] ?? ''));
    $to = trim((string)($filters['to'] ?? ''));
    $supplierId = (int)($filters['supplier_id'] ?? 0);

    $where = ['1=1'];
    $params = [];

    if ($from !== '') {
      $where[] = 'r.receipt_date >= ?';
      $params[] = $from;
    }
    if ($to !== '') {
      $where[] = 'r.receipt_date <= ?';
      $params[] = $to;
    }
    if ($supplierId > 0) {
      $where[] = 'r.supplier_id = ?';
      $params[] = $supplierId;
    }

    $sql = 'SELECT r.id, r.receipt_no, r.receipt_date, r.created_at,
                   sp.name AS supplier_name,
                   u.name AS received_by_name,
                   r.purchase_order_id
            FROM receipts r
            JOIN suppliers sp ON sp.id = r.supplier_id
            JOIN users u ON u.id = r.received_by
            WHERE ' . implode(' AND ', $where) .
            ' ORDER BY r.receipt_date DESC, r.id DESC
            LIMIT 200';

    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function find(PDO $db, int $id): ?array {
    $st = $db->prepare('SELECT r.*, sp.name AS supplier_name, u.name AS received_by_name
                        FROM receipts r
                        JOIN suppliers sp ON sp.id = r.supplier_id
                        JOIN users u ON u.id = r.received_by
                        WHERE r.id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
      return null;
    }

    $stItems = $db->prepare('SELECT ri.*, it.sku, it.name, it.unit
                             FROM receipt_items ri
                             JOIN items it ON it.id = ri.item_id
                             WHERE ri.receipt_id = ?
                             ORDER BY it.name ASC');
    $stItems->execute([$id]);
    $r['items'] = $stItems->fetchAll();

    return $r;
  }

  public static function latest(PDO $db, int $limit = 5, int $supplierId = 0): array {
    $where = '1=1';
    if ($supplierId > 0) {
      $where = 'r.supplier_id = ?';
    }

    $sql = 'SELECT r.id, r.receipt_no, r.receipt_date, sp.name AS supplier_name
            FROM receipts r
            JOIN suppliers sp ON sp.id = r.supplier_id
            WHERE ' . $where . '
            ORDER BY r.id DESC
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
    return sprintf('%s-%s-%04d', $prefix, date('Ymd'), random_int(0, 9999));
  }
}
