-- 발주·재고·입고 통합 관리 시스템
-- MySQL 8.0

SET NAMES utf8mb4;
SET time_zone = '+00:00';

DROP TABLE IF EXISTS receipt_items;
DROP TABLE IF EXISTS receipts;
DROP TABLE IF EXISTS purchase_order_items;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS inquiry_messages;
DROP TABLE IF EXISTS inquiries;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS notices;

CREATE TABLE suppliers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  contact_name VARCHAR(100) NULL,
  phone VARCHAR(50) NULL,
  email VARCHAR(190) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(50) NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  supplier_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_supplier (supplier_id),
  CONSTRAINT fk_users_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  approved_at DATETIME NULL,
  approved_by BIGINT UNSIGNED NULL,
  used_at DATETIME NULL,
  request_ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token_hash (token_hash),
  KEY idx_password_resets_user_created (user_id, created_at),
  KEY idx_password_resets_expires_used (expires_at, used_at),
  KEY idx_password_resets_approved_used (approved_at, used_at),
  KEY idx_password_resets_approved_by (approved_by),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE
  ,CONSTRAINT fk_password_resets_approved_by FOREIGN KEY (approved_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inquiries (
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

CREATE TABLE inquiry_messages (
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

CREATE TABLE items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(50) NOT NULL,
  name VARCHAR(200) NOT NULL,
  supplier_id BIGINT UNSIGNED NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'EA',
  min_stock INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- SKU는 거래처별로 유니크(템플릿/미지정 supplier_id=NULL도 0으로 취급)
  UNIQUE KEY uq_items_supplier_sku ((COALESCE(supplier_id, 0)), sku),
  KEY idx_items_name (name),
  KEY idx_items_supplier (supplier_id),
  CONSTRAINT fk_items_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inventory (
  item_id BIGINT UNSIGNED NOT NULL,
  on_hand INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (item_id),
  CONSTRAINT fk_inventory_item FOREIGN KEY (item_id) REFERENCES items(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_no VARCHAR(30) NOT NULL,
  supplier_id BIGINT UNSIGNED NOT NULL,
  ordered_by BIGINT UNSIGNED NOT NULL,
  status ENUM('OPEN','RECEIVED','CANCELLED') NOT NULL DEFAULT 'OPEN',
  order_date DATE NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_po_no (po_no),
  KEY idx_po_supplier_date (supplier_id, order_date),
  KEY idx_po_ordered_by (ordered_by),
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_po_user FOREIGN KEY (ordered_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_poi_po_item (purchase_order_id, item_id),
  KEY idx_poi_item (item_id),
  CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_poi_item FOREIGN KEY (item_id) REFERENCES items(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_no VARCHAR(30) NOT NULL,
  purchase_order_id BIGINT UNSIGNED NULL,
  supplier_id BIGINT UNSIGNED NOT NULL,
  received_by BIGINT UNSIGNED NOT NULL,
  receipt_date DATE NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_receipt_no (receipt_no),
  KEY idx_receipts_supplier_date (supplier_id, receipt_date),
  KEY idx_receipts_po (purchase_order_id),
  CONSTRAINT fk_receipts_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_receipts_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_receipts_user FOREIGN KEY (received_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE receipt_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  qty_received INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ri_receipt_item (receipt_id, item_id),
  KEY idx_ri_item (item_id),
  CONSTRAINT fk_ri_receipt FOREIGN KEY (receipt_id) REFERENCES receipts(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ri_item FOREIGN KEY (item_id) REFERENCES items(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notices (
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
