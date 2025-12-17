<?php

function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
  return current_user() !== null;
}

function is_admin(): bool {
  $u = current_user();
  return $u && ($u['role'] ?? '') === 'admin';
}

function current_supplier_id(): ?int {
  $u = current_user();
  if (!$u) {
    return null;
  }
  $sid = (int)($u['supplier_id'] ?? 0);
  return $sid > 0 ? $sid : null;
}

function require_login(): void {
  if (!is_logged_in()) {
    flash_set('error', '로그인이 필요합니다.');
    redirect('/login.php');
  }
}

function require_admin(): void {
  require_login();
  if (!is_admin()) {
    flash_set('error', '접근 권한이 없습니다.');
    redirect('/index.php');
  }
}

function require_supplier(): void {
  require_login();
  if (!is_admin()) {
    $u = current_user();
    $sid = (int)($u['supplier_id'] ?? 0);
    if ($sid <= 0) {
      flash_set('error', '거래처 소속이 설정되지 않았습니다. 프로필에서 거래처를 지정하세요.');
      redirect('/profile.php');
    }
  }
}

function auth_login(array $userRow): void {
  session_regenerate_id(true);
  $_SESSION['user'] = [
    'id' => (int)$userRow['id'],
    'email' => $userRow['email'],
    'name' => $userRow['name'],
    'role' => $userRow['role'],
    'supplier_id' => isset($userRow['supplier_id']) ? (int)$userRow['supplier_id'] : null,
    'supplier_name' => isset($userRow['supplier_name']) ? (string)$userRow['supplier_name'] : null,
  ];
}

function auth_logout(): void {
  unset($_SESSION['user']);
  session_regenerate_id(true);
}
