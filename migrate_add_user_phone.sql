-- 기존 DB를 유지한 채 업그레이드할 때 사용하는 마이그레이션입니다.
-- 목표: users.phone(연락처) 추가
-- 이 스크립트는 "이미 적용되어 있어도" 재실행 가능하도록 작성했습니다.

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'users'
    AND column_name = 'phone'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
