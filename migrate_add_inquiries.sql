-- 1:1 문의(inquiries) 테이블 추가
-- MySQL 8.0

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS inquiries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id BIGINT UNSIGNED NOT NULL,
  receiver_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inq_sender_created (sender_id, created_at),
  KEY idx_inq_receiver_created (receiver_id, created_at),
  KEY idx_inq_active_created (active, created_at),
  CONSTRAINT fk_inquiries_sender FOREIGN KEY (sender_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_inquiries_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
