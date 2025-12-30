-- 공지사항(notices) 중요도(priority) 컬럼 추가
-- MySQL 8.0

SET NAMES utf8mb4;

ALTER TABLE notices
  ADD COLUMN priority TINYINT(1) NOT NULL DEFAULT 0 AFTER body,
  ADD KEY idx_notices_active_priority_created (active, priority, created_at);
