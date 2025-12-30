-- 공지사항(notices) 테이블 추가
-- MySQL 8.0

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  priority TINYINT(1) NOT NULL DEFAULT 0,
  author_id BIGINT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notices_active_created (active, created_at),
  KEY idx_notices_active_priority_created (active, priority, created_at),
  KEY idx_notices_author (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
