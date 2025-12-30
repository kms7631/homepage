<?php
declare(strict_types=1);

final class Item {
  public static function canonicalSkuForName(PDO $db, string $name): ?string {
    $name = trim($name);
    if ($name === '') {
      return null;
    }
    // 같은 품목명에서 SUP* 랜덤 SKU를 제외한 SKU 중 하나를 canonical로 선택
    $st = $db->prepare(
      "SELECT MIN(sku) AS sku
       FROM items
       WHERE active = 1
         AND name = ?
         AND sku NOT LIKE 'SUP%'
         AND sku <> ''"
    );
    $st->execute([$name]);
    $sku = (string)($st->fetchColumn() ?: '');
    return $sku !== '' ? $sku : null;
  }

  /**
   * 품목명 기준으로 SKU를 단일화합니다.
   * - canonical: name별 SUP 제외 SKU의 MIN 값
   * - SUP* 포함 모든 row를 canonical로 변경 시도
   * - 같은 거래처에 이미 canonical SKU가 있으면 충돌로 스킵
   */
  public static function normalizeSkuByName(PDO $db): array {
    $canonSt = $db->query(
      "SELECT name, MIN(sku) AS sku
       FROM items
       WHERE active = 1
         AND name <> ''
         AND sku NOT LIKE 'SUP%'
         AND sku <> ''
       GROUP BY name"
    );
    $canon = [];
    foreach ($canonSt->fetchAll() as $r) {
      $n = (string)($r['name'] ?? '');
      $s = (string)($r['sku'] ?? '');
      if ($n !== '' && $s !== '') {
        $canon[$n] = $s;
      }
    }

    $rowsSt = $db->query(
      "SELECT id, supplier_id, name, sku
       FROM items
       WHERE active = 1
         AND name <> ''
       ORDER BY id ASC"
    );
    $rows = $rowsSt->fetchAll();

    $check = $db->prepare('SELECT id FROM items WHERE active = 1 AND supplier_id = ? AND sku = ? AND id <> ? LIMIT 1');
    $upd = $db->prepare('UPDATE items SET sku = ? WHERE id = ?');

    $updated = 0;
    $skippedNoCanon = 0;
    $skippedNoSupplier = 0;
    $skippedConflict = 0;
    $skippedSame = 0;
    $skippedError = 0;

    foreach ($rows as $r) {
      $id = (int)($r['id'] ?? 0);
      $supplierId = (int)($r['supplier_id'] ?? 0);
      $name = (string)($r['name'] ?? '');
      $sku = (string)($r['sku'] ?? '');
      if ($id <= 0) continue;
      if ($supplierId <= 0) {
        // 템플릿(공용) 품목은 supplier_id가 NULL일 수 있음: 건드리지 않음
        $skippedNoSupplier++;
        continue;
      }
      if (!isset($canon[$name])) {
        $skippedNoCanon++;
        continue;
      }
      $targetSku = (string)$canon[$name];
      if ($targetSku === '' || $sku === $targetSku) {
        $skippedSame++;
        continue;
      }

      $check->execute([$supplierId, $targetSku, $id]);
      if ($check->fetchColumn()) {
        $skippedConflict++;
        continue;
      }

      try {
        $upd->execute([$targetSku, $id]);
        $updated++;
      } catch (Throwable $e) {
        $skippedError++;
      }
    }

    return [
      'updated' => $updated,
      'skipped_no_canon' => $skippedNoCanon,
      'skipped_no_supplier' => $skippedNoSupplier,
      'skipped_conflict' => $skippedConflict,
      'skipped_same' => $skippedSame,
      'skipped_error' => $skippedError,
      'total' => count($rows),
    ];
  }
  public static function findBySkuForSupplier(PDO $db, string $sku, int $supplierId): ?array {
    $sku = trim($sku);
    if ($sku === '' || $supplierId <= 0) {
      return null;
    }
    $st = $db->prepare(
      'SELECT it.*, sp.name AS supplier_name, COALESCE(inv.on_hand,0) AS on_hand
       FROM items it
       LEFT JOIN suppliers sp ON sp.id = it.supplier_id
       LEFT JOIN inventory inv ON inv.item_id = it.id
       WHERE it.active = 1 AND it.sku = ? AND it.supplier_id = ?
       ORDER BY it.id DESC
       LIMIT 1'
    );
    $st->execute([$sku, $supplierId]);
    $row = $st->fetch();
    return $row ?: null;
  }

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
    $nameExact = trim((string)($filters['name_exact'] ?? ''));
    $supplierId = (int)($filters['supplier_id'] ?? 0);

    $where = ['it.active = 1'];
    $params = [];

    if ($nameExact !== '') {
      $where[] = 'it.name = ?';
      $params[] = $nameExact;
    } elseif ($q !== '') {
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

  public static function delete(PDO $db, int $id): void {
    if ($id <= 0) {
      throw new RuntimeException('삭제할 품목이 올바르지 않습니다.');
    }
    // 하드 삭제는 발주/입고 등 FK로 실패할 수 있어, 기본은 soft delete
    $st = $db->prepare('UPDATE items SET active = 0 WHERE id = ?');
    $st->execute([$id]);
  }

  /**
   * 과거 로직으로 생성된 SUP* 랜덤 SKU를 정리합니다.
   * - 같은 품목명(name)에서 SUP가 아닌 SKU 중 하나를 canonical로 선택
   * - 해당 canonical SKU로 변경(단, 같은 거래처에 이미 동일 SKU가 있으면 스킵)
   */
  public static function normalizeRandomSupplierSkus(PDO $db): array {
    // canonical: name -> sku (SUP 제외)
    $canonSt = $db->query(
      "SELECT name, MIN(sku) AS sku
       FROM items
       WHERE active = 1
         AND name <> ''
         AND sku NOT LIKE 'SUP%'
       GROUP BY name"
    );
    $canon = [];
    foreach ($canonSt->fetchAll() as $r) {
      $n = (string)($r['name'] ?? '');
      $s = (string)($r['sku'] ?? '');
      if ($n !== '' && $s !== '') {
        $canon[$n] = $s;
      }
    }

    $rowsSt = $db->query(
      "SELECT id, supplier_id, name, sku
       FROM items
       WHERE active = 1
         AND sku LIKE 'SUP%'
       ORDER BY id ASC"
    );
    $rows = $rowsSt->fetchAll();

    $check = $db->prepare('SELECT id FROM items WHERE active = 1 AND supplier_id = ? AND sku = ? AND id <> ? LIMIT 1');
    $upd = $db->prepare('UPDATE items SET sku = ? WHERE id = ?');

    $updated = 0;
    $skippedNoCanon = 0;
    $skippedNoSupplier = 0;
    $skippedConflict = 0;
    $skippedError = 0;

    foreach ($rows as $r) {
      $id = (int)($r['id'] ?? 0);
      $supplierId = (int)($r['supplier_id'] ?? 0);
      $name = (string)($r['name'] ?? '');
      if ($id <= 0) continue;
      if ($supplierId <= 0) {
        $skippedNoSupplier++;
        continue;
      }
      if ($name === '' || !isset($canon[$name])) {
        $skippedNoCanon++;
        continue;
      }
      $targetSku = (string)$canon[$name];
      if ($targetSku === '') {
        $skippedNoCanon++;
        continue;
      }

      $check->execute([$supplierId, $targetSku, $id]);
      $exists = $check->fetchColumn();
      if ($exists) {
        $skippedConflict++;
        continue;
      }

      try {
        $upd->execute([$targetSku, $id]);
        $updated++;
      } catch (Throwable $e) {
        $skippedError++;
      }
    }

    return [
      'updated' => $updated,
      'skipped_no_canon' => $skippedNoCanon,
      'skipped_no_supplier' => $skippedNoSupplier,
      'skipped_conflict' => $skippedConflict,
      'skipped_error' => $skippedError,
      'total_sup' => count($rows),
    ];
  }

}
