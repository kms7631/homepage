-- 1:1 문의 답변(inquiry_messages) 테이블 추가
-- MySQL 8.0

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS inquiry_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inquiry_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inqm_inquiry_created (inquiry_id, created_at),
  KEY idx_inqm_sender_created (sender_id, created_at),
  CONSTRAINT fk_inqm_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_inqm_sender FOREIGN KEY (sender_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
