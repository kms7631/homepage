-- 기존 DB를 유지한 채 업그레이드할 때 사용하는 마이그레이션입니다.
-- 목표: items.sku 유니크 제약을 "거래처별 유니크"로 변경
-- MySQL 8.0 (functional index 사용)

SET NAMES utf8mb4;

-- 1) 기존 uq_items_sku 드롭
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'items'
    AND index_name = 'uq_items_sku'
);

SET @sql := IF(
  @idx_exists > 0,
  'ALTER TABLE items DROP INDEX uq_items_sku',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) 신규 uq_items_supplier_sku 생성(이미 있으면 스킵)
SET @idx2_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'items'
    AND index_name = 'uq_items_supplier_sku'
);

SET @sql := IF(
  @idx2_exists = 0,
  'CREATE UNIQUE INDEX uq_items_supplier_sku ON items ((COALESCE(supplier_id, 0)), sku)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (선택) sku 검색 성능용 일반 인덱스가 필요하면 추가 가능하지만, 위 유니크 인덱스가 커버합니다.
