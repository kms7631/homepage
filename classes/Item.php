<?php
declare(strict_types=1);

final class Item {
  public static function listByIds(PDO $db, array $ids): array {
    $ids = array_values(array_filter(array_map('intval', $ids), function ($v) {
      return $v > 0;
    }));
    if (!$ids) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT it.id, it.sku, it.name, it.unit, it.min_stock, it.supplier_id,
                   sp.name AS supplier_name,
                   COALESCE(inv.on_hand, 0) AS on_hand
            FROM items it
            LEFT JOIN suppliers sp ON sp.id = it.supplier_id
            LEFT JOIN inventory inv ON inv.item_id = it.id
            WHERE it.id IN (' . $placeholders . ')';
    $st = $db->prepare($sql);
    $st->execute($ids);
    $rows = $st->fetchAll();

    // 입력 id 순서대로 정렬
    $byId = [];
    foreach ($rows as $r) {
      $byId[(int)$r['id']] = $r;
    }
    $out = [];
    foreach ($ids as $id) {
      if (isset($byId[$id])) {
        $out[] = $byId[$id];
      }
    }
    return $out;
  }

  public static function list(PDO $db, array $filters = []): array {
    $q = trim((string)($filters['q'] ?? ''));
    $supplierId = (int)($filters['supplier_id'] ?? 0);

    $where = ['it.active = 1'];
    $params = [];

    if ($q !== '') {
      $where[] = '(it.name LIKE ? OR it.sku LIKE ?)';
      $params[] = '%' . $q . '%';
      $params[] = '%' . $q . '%';
    }
    if ($supplierId > 0) {
      $where[] = 'it.supplier_id = ?';
      $params[] = $supplierId;
    }

    $sql = 'SELECT it.id, it.sku, it.name, it.unit, it.min_stock, it.supplier_id,
              sp.name AS supplier_name,
              COALESCE(inv.on_hand, 0) AS on_hand
            FROM items it
            LEFT JOIN suppliers sp ON sp.id = it.supplier_id
            LEFT JOIN inventory inv ON inv.item_id = it.id
            WHERE ' . implode(' AND ', $where) .
            ' ORDER BY it.name ASC';

    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function find(PDO $db, int $id): ?array {
    $st = $db->prepare('SELECT it.*, sp.name AS supplier_name, COALESCE(inv.on_hand,0) AS on_hand
                        FROM items it
                        LEFT JOIN suppliers sp ON sp.id = it.supplier_id
                        LEFT JOIN inventory inv ON inv.item_id = it.id
                        WHERE it.id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function listBySupplier(PDO $db, int $supplierId): array {
    $st = $db->prepare('SELECT it.id, it.sku, it.name, it.unit, it.min_stock, COALESCE(inv.on_hand,0) AS on_hand
                        FROM items it
                        LEFT JOIN inventory inv ON inv.item_id = it.id
                        WHERE it.active = 1 AND it.supplier_id = ?
                        ORDER BY it.name ASC');
    $st->execute([$supplierId]);
    return $st->fetchAll();
  }

  public static function create(PDO $db, array $data): int {
    $st = $db->prepare('INSERT INTO items (sku, name, supplier_id, unit, min_stock, active)
                        VALUES (?, ?, ?, ?, ?, 1)');

    $supplierId = (int)($data['supplier_id'] ?? 0);

    $st->execute([
      trim((string)($data['sku'] ?? '')),
      trim((string)($data['name'] ?? '')),
      $supplierId > 0 ? $supplierId : null,
      trim((string)($data['unit'] ?? 'EA')) ?: 'EA',
      (int)($data['min_stock'] ?? 0),
    ]);

    $itemId = (int)$db->lastInsertId();
    $inv = $db->prepare('INSERT INTO inventory (item_id, on_hand) VALUES (?, 0)');
    $inv->execute([$itemId]);

    return $itemId;
  }

  public static function update(PDO $db, int $id, array $data): void {
    $supplierId = (int)($data['supplier_id'] ?? 0);
    $st = $db->prepare('UPDATE items SET name = ?, supplier_id = ?, unit = ?, min_stock = ? WHERE id = ?');
    $st->execute([
      trim((string)($data['name'] ?? '')),
      $supplierId > 0 ? $supplierId : null,
      trim((string)($data['unit'] ?? 'EA')) ?: 'EA',
      (int)($data['min_stock'] ?? 0),
      $id,
    ]);
  }

  public static function deleteSampleItems(PDO $db): int {
    // 발주/입고에 연결된 품목은 FK로 삭제가 막히므로, 참조 없는 샘플(초기) 품목만 삭제
    $sql = "DELETE it
            FROM items it
            LEFT JOIN purchase_order_items poi ON poi.item_id = it.id
            LEFT JOIN receipt_items ri ON ri.item_id = it.id
            WHERE (it.name LIKE '데모 품목 %' OR it.name LIKE '샘플 품목 %')
              AND poi.id IS NULL
              AND ri.id IS NULL";
    $st = $db->prepare($sql);
    $st->execute();
    return $st->rowCount();
  }

  /** @deprecated 기존 호환용. 신규 코드에서는 deleteSampleItems() 사용 */
  public static function deleteDemoItems(PDO $db): int {
    return self::deleteSampleItems($db);
  }
}
