-- 일정관리(캘린더) 월 단위 조회 성능 개선용 인덱스
-- 주의: 이미 같은 이름의 인덱스가 존재하면 에러가 날 수 있으니, 필요 시 이름을 바꿔서 적용하세요.

CREATE INDEX idx_purchase_orders_created_supplier ON purchase_orders (created_at, supplier_id);
CREATE INDEX idx_receipts_created_supplier ON receipts (created_at, supplier_id);
