-- Seed Data
-- 비밀번호는 실제 배포에서 반드시 변경하세요.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Suppliers
INSERT INTO suppliers (name, contact_name, phone, email, notes)
VALUES
  ('한빛상사', '김담당', '010-1111-2222', 'sales@hanbit.example', '주 거래처'),
  ('서진트레이딩', '이담당', '010-3333-4444', 'contact@seojin.example', '긴급 납기 가능');

-- Users
INSERT INTO users (email, password_hash, name, role, supplier_id)
VALUES
  ('admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'admin', NULL),
  ('user1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User One', 'user', 1);
-- 위 해시는 예시용(비번: password)

-- Items (10)
INSERT INTO items (sku, name, supplier_id, unit, min_stock, active)
VALUES
  ('RM-AL-001', '알루미늄 판재 1mm', 1, 'EA', 20, 1),
  ('RM-AL-002', '알루미늄 판재 2mm', 1, 'EA', 15, 1),
  ('RM-ST-001', '스테인리스 파이프 10mm', 2, 'EA', 30, 1),
  ('RM-ST-002', '스테인리스 파이프 20mm', 2, 'EA', 25, 1),
  ('PK-BOX-001', '포장 박스 S', 1, 'EA', 50, 1),
  ('PK-BOX-002', '포장 박스 M', 1, 'EA', 50, 1),
  ('PK-TAPE-001', '포장 테이프', 2, 'EA', 40, 1),
  ('CP-SCR-001', '십자나사 M3', 2, 'EA', 200, 1),
  ('CP-NUT-001', '너트 M3', 2, 'EA', 200, 1),
  ('FG-PROD-001', '완제품 A(샘플)', NULL, 'EA', 5, 1);

-- Inventory 초기값
INSERT INTO inventory (item_id, on_hand)
SELECT id,
  CASE sku
    WHEN 'RM-AL-001' THEN 12
    WHEN 'RM-AL-002' THEN 18
    WHEN 'RM-ST-001' THEN 10
    WHEN 'RM-ST-002' THEN 30
    WHEN 'PK-BOX-001' THEN 45
    WHEN 'PK-BOX-002' THEN 80
    WHEN 'PK-TAPE-001' THEN 35
    WHEN 'CP-SCR-001' THEN 150
    WHEN 'CP-NUT-001' THEN 220
    WHEN 'FG-PROD-001' THEN 3
    ELSE 0
  END AS on_hand
FROM items;

-- Sample Purchase Order + Items
INSERT INTO purchase_orders (po_no, supplier_id, ordered_by, status, order_date, notes)
VALUES ('PO-20251217-0001', 1, 2, 'OPEN', '2025-12-16', '샘플 발주');

INSERT INTO purchase_order_items (purchase_order_id, item_id, qty, unit_cost)
VALUES
  (1, (SELECT id FROM items WHERE sku='RM-AL-001'), 30, 1200.00),
  (1, (SELECT id FROM items WHERE sku='PK-BOX-001'), 100, 150.00);

-- Sample Receipt (부분 입고)
INSERT INTO receipts (receipt_no, purchase_order_id, supplier_id, received_by, receipt_date, notes)
VALUES ('RC-20251217-0001', 1, 1, 1, '2025-12-17', '샘플 입고');

INSERT INTO receipt_items (receipt_id, item_id, qty_received)
VALUES
  (1, (SELECT id FROM items WHERE sku='PK-BOX-001'), 30);

-- 입고분 재고 반영(시드 단계에서도 정합성 있게)
UPDATE inventory i
JOIN items it ON it.id = i.item_id
SET i.on_hand = i.on_hand + 30
WHERE it.sku = 'PK-BOX-001';
