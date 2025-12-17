<?php
declare(strict_types=1);

final class Inventory {
  public static function adjust(PDO $db, int $itemId, int $delta): void {
    // Upsert 안전 처리
    $st = $db->prepare('INSERT INTO inventory (item_id, on_hand) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE on_hand = on_hand + VALUES(on_hand)');
    $st->execute([$itemId, $delta]);
  }

  public static function setOnHand(PDO $db, int $itemId, int $onHand): void {
    $st = $db->prepare('INSERT INTO inventory (item_id, on_hand) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE on_hand = VALUES(on_hand)');
    $st->execute([$itemId, $onHand]);
  }

  public static function lowStockTop(PDO $db, int $limit = 5, int $supplierId = 0): array {
    $where = ['it.active = 1', 'COALESCE(inv.on_hand,0) <= it.min_stock'];
    $params = [];
    if ($supplierId > 0) {
      $where[] = 'it.supplier_id = ?';
      $params[] = $supplierId;
    }

    $sql = 'SELECT it.id, it.sku, it.name, it.min_stock, COALESCE(inv.on_hand,0) AS on_hand,
                   (it.min_stock - COALESCE(inv.on_hand,0)) AS shortage
            FROM items it
            LEFT JOIN inventory inv ON inv.item_id = it.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY shortage DESC, it.name ASC
            LIMIT ?';
    $st = $db->prepare($sql);
    $pos = 1;
    foreach ($params as $p) {
      $st->bindValue($pos, $p, PDO::PARAM_INT);
      $pos++;
    }
    $st->bindValue($pos, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }
}
