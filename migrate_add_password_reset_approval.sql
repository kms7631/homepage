-- 비밀번호 재설정 요청: 관리자 승인 플로우 지원
-- MySQL 8.0

ALTER TABLE password_resets
  ADD COLUMN approved_at DATETIME NULL AFTER expires_at,
  ADD COLUMN approved_by BIGINT UNSIGNED NULL AFTER approved_at,
  ADD KEY idx_password_resets_approved_used (approved_at, used_at),
  ADD KEY idx_password_resets_approved_by (approved_by),
  ADD CONSTRAINT fk_password_resets_approved_by FOREIGN KEY (approved_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL;
