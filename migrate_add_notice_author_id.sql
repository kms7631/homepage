-- 공지사항(notices) 작성자(author_id) 컬럼 추가
-- MySQL 8.0

SET NAMES utf8mb4;

ALTER TABLE notices
  ADD COLUMN author_id BIGINT UNSIGNED NULL AFTER priority,
  ADD KEY idx_notices_author (author_id);
