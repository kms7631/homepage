<?php
declare(strict_types=1);

final class Supplier {
  public static function listAll(PDO $db): array {
    $st = $db->query('SELECT id, name, contact_name, phone, email FROM suppliers ORDER BY name ASC');
    return $st->fetchAll();
  }

  public static function find(PDO $db, int $id): ?array {
    $st = $db->prepare('SELECT * FROM suppliers WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function create(PDO $db, array $data): int {
    $st = $db->prepare('INSERT INTO suppliers (name, contact_name, phone, email, notes) VALUES (?, ?, ?, ?, ?)');
    $st->execute([
      trim((string)($data['name'] ?? '')),
      trim((string)($data['contact_name'] ?? '')) ?: null,
      trim((string)($data['phone'] ?? '')) ?: null,
      trim((string)($data['email'] ?? '')) ?: null,
      trim((string)($data['notes'] ?? '')) ?: null,
    ]);
    return (int)$db->lastInsertId();
  }

  public static function update(PDO $db, int $id, array $data): void {
    $st = $db->prepare('UPDATE suppliers SET name = ?, contact_name = ?, phone = ?, email = ?, notes = ? WHERE id = ?');
    $st->execute([
      trim((string)($data['name'] ?? '')),
      trim((string)($data['contact_name'] ?? '')) ?: null,
      trim((string)($data['phone'] ?? '')) ?: null,
      trim((string)($data['email'] ?? '')) ?: null,
      trim((string)($data['notes'] ?? '')) ?: null,
      $id,
    ]);
  }

  public static function delete(PDO $db, int $id): void {
    $st = $db->prepare('DELETE FROM suppliers WHERE id = ?');
    $st->execute([$id]);
  }

  public static function createInitialItems(PDO $db, int $supplierId, int $count = 6): void {
    // "기존에 있던 품목"을 랜덤으로 선택해 신규 거래처에 복제(이름/단위/안전재고 기반) 후,
    // 재고(on_hand)를 여유/부족 랜덤으로 세팅합니다.
    // ※ 기존 거래처의 품목을 '이동'시키지 않고, 신규 거래처용으로 새 row를 생성합니다(SKU는 새로 생성).

    $templates = [];
    try {
      // 템플릿은 SUP* 랜덤 SKU를 제외하고, SKU 기준으로 중복을 줄여 선택
      $stTpl = $db->prepare(
        "SELECT MIN(id) AS id, sku, name, unit, min_stock
         FROM items
         WHERE active = 1 AND sku NOT LIKE 'SUP%'
         GROUP BY sku, name, unit, min_stock
         ORDER BY RAND()
         LIMIT ?"
      );
      $stTpl->bindValue(1, $count, PDO::PARAM_INT);
      $stTpl->execute();
      $templates = $stTpl->fetchAll();
    } catch (Throwable $e) {
      $templates = [];
    }

    $insertItem = $db->prepare('INSERT INTO items (sku, name, supplier_id, unit, min_stock, active) VALUES (?, ?, ?, ?, ?, 1)');

    // 템플릿이 없으면 최소한의 fallback(이름만 단순 생성)
    if (!$templates) {
      $templates = [];
      for ($i = 1; $i <= $count; $i++) {
        $templates[] = ['name' => '품목 ' . $i, 'unit' => 'EA', 'min_stock' => random_int(10, 80)];
      }
    }

    foreach ($templates as $tpl) {
      $name = (string)($tpl['name'] ?? '품목');
      $sku = trim((string)($tpl['sku'] ?? ''));
      $unit = trim((string)($tpl['unit'] ?? 'EA')) ?: 'EA';
      $min = (int)($tpl['min_stock'] ?? 0);
      if ($min <= 0) {
        $min = random_int(10, 80);
      }

      $mode = random_int(0, 1);
      $onHand = $mode === 0 ? random_int(0, max(0, $min)) : random_int($min + 1, $min + 100);

      // 같은 SKU가 이미 있으면 스킵(또는 DB 유니크 인덱스에 의해 예외)
      if ($sku !== '') {
        try {
          $insertItem->execute([$sku, $name, $supplierId, $unit, $min]);
        } catch (PDOException $e) {
          // 기존 DB에서 sku 유니크 제약(uq_items_sku)이 남아있거나, 이미 같은 SKU가 존재하면 스킵
          continue;
        }
      } else {
        // 템플릿 SKU가 없으면 fallback 생성
        $sku2 = sprintf('ITEM-%s', strtoupper(bin2hex(random_bytes(3))));
        $insertItem->execute([$sku2, $name, $supplierId, $unit, $min]);
      }

      $itemId = (int)$db->lastInsertId();
      Inventory::setOnHand($db, $itemId, $onHand);
    }
  }

  /** @deprecated 기존 호환용. 신규 코드에서는 createInitialItems() 사용 */
  public static function createDemoItems(PDO $db, int $supplierId, int $count = 6): void {
    self::createInitialItems($db, $supplierId, $count);
  }
}
