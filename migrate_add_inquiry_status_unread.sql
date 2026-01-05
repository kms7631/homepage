-- 1:1 문의 상태/해결/미읽음(관리자) 컬럼 추가
-- MySQL 8.0
-- 정책: 기존 문의는 IN_PROGRESS로 간주

SET NAMES utf8mb4;

ALTER TABLE inquiries
  ADD COLUMN status ENUM('NEW','IN_PROGRESS','RESOLVED') NOT NULL DEFAULT 'IN_PROGRESS' AFTER body,
  ADD COLUMN resolved_at DATETIME NULL AFTER status,
  ADD COLUMN resolved_by_role VARCHAR(10) NULL AFTER resolved_at,
  ADD COLUMN resolved_by_id BIGINT UNSIGNED NULL AFTER resolved_by_role,
  ADD COLUMN resolved_reason VARCHAR(50) NULL AFTER resolved_by_id,
  ADD COLUMN resolved_note TEXT NULL AFTER resolved_reason,
  ADD COLUMN last_message_at DATETIME NULL AFTER resolved_note,
  ADD COLUMN admin_last_read_at DATETIME NULL AFTER last_message_at,
  ADD KEY idx_inquiries_status_last (status, last_message_at),
  ADD KEY idx_inquiries_last_message_at (last_message_at);

-- 기존 데이터 기본값 세팅
UPDATE inquiries i
SET i.status = 'IN_PROGRESS'
WHERE i.status IS NULL;

-- last_message_at: 메시지가 있으면 마지막 메시지 시간, 없으면 updated_at/created_at
UPDATE inquiries i
SET i.last_message_at = COALESCE(
  (SELECT MAX(m.created_at) FROM inquiry_messages m WHERE m.inquiry_id = i.id AND m.active = 1),
  i.updated_at,
  i.created_at
)
WHERE i.last_message_at IS NULL;
