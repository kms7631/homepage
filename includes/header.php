<?php
$me = current_user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (defined('APP_BASE') && APP_BASE !== '') {
  if (starts_with($path, APP_BASE . '/')) {
    $path = substr($path, strlen(APP_BASE));
  } elseif ($path === APP_BASE) {
    $path = '/';
  }
}
function nav_active(string $needle, string $path): string {
  return starts_with($path, $needle) ? 'active' : '';
}

$isAuthPage = (!$me && $path === '/login.php');
if (!defined('LAYOUT_AUTH_PAGE')) {
  define('LAYOUT_AUTH_PAGE', $isAuthPage);
}

$cssVer = @filemtime(__DIR__ . '/../assets/app.css');
if ($cssVer === false) {
  $cssVer = 1;
}
?><!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('/assets/app.css?v=' . (string)$cssVer)) ?>" />
  <?php if (isset($extraHeadHtml) && is_string($extraHeadHtml) && $extraHeadHtml !== ''): ?>
    <?= $extraHeadHtml ?>
  <?php endif; ?>
</head>
<body>
  <?php if (!$isAuthPage): ?>
    <div class="topbar">
      <div class="brand"><a class="brand" href="<?= e(url('/index.php')) ?>"><?= e(APP_NAME) ?></a></div>
      <div class="nav">
        <a class="<?= e(nav_active('/index.php', $path)) ?>" href="<?= e(url('/index.php')) ?>">메인</a>
        <a class="<?= e(nav_active('/items.php', $path)) ?>" href="<?= e(url('/items.php')) ?>">품목</a>
        <a class="<?= e(nav_active('/po', $path)) ?>" href="<?= e(url('/po_list.php')) ?>">발주</a>
        <a class="<?= e(nav_active('/receipt', $path)) ?>" href="<?= e(url('/receipt_list.php')) ?>">입고</a>
        <?php if (!$me): ?>
          <a class="<?= e(nav_active('/login.php', $path)) ?>" href="<?= e(url('/login.php')) ?>">로그인</a>
          <a class="<?= e(nav_active('/register.php', $path)) ?>" href="<?= e(url('/register.php')) ?>">회원가입</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($me): ?>
    <div class="layout">
      <aside class="sidebar">
        <div class="sidebar-card">
          <div class="sidebar-user-name"><?= e($me['name']) ?>님 환영합니다</div>
          <div class="sidebar-user-meta">
            <div class="muted">
              소속:
              <?php if (is_admin()): ?>
                <?= e('관리자') ?>
              <?php else: ?>
                <?= e(($me['supplier_name'] ?? '') ?: ('거래처 #' . (int)($me['supplier_id'] ?? 0))) ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="sidebar-user-actions">
            <a class="btn secondary" href="<?= e(url('/profile.php')) ?>">프로필 설정</a>
            <a class="btn secondary" href="<?= e(url('/logout.php')) ?>">로그아웃</a>
          </div>
        </div>

        <div class="sidebar-card sidebar-tree">
          <a class="tree-link <?= e(nav_active('/index.php', $path)) ?>" href="<?= e(url('/index.php')) ?>">메인</a>
          <a class="tree-link <?= e(nav_active('/notice', $path)) ?>" href="<?= e(url('/notice.php')) ?>">공지사항</a>

          <a class="tree-link <?= e(nav_active('/inquiry', $path)) ?>" href="<?= e(url('/inquiry_list.php')) ?>">1:1 문의</a>

          <details class="tree-group" open>
            <summary class="tree-summary">품목</summary>
            <div class="tree-items">
              <a class="tree-link <?= e(nav_active('/items.php', $path)) ?>" href="<?= e(url('/items.php')) ?>">품목</a>
              <?php if (is_admin()): ?>
                <a class="tree-link <?= e(nav_active('/admin/items.php', $path)) ?>" href="<?= e(url('/admin/items.php')) ?>">품목 관리</a>
              <?php else: ?>
                <a class="tree-link <?= e(nav_active('/inventory_manage.php', $path)) ?>" href="<?= e(url('/inventory_manage.php')) ?>">품목 관리</a>
              <?php endif; ?>
              <a class="tree-link" href="<?= e(url('/items.php#catalog-add')) ?>">품목 추가</a>
            </div>
          </details>

          <details class="tree-group" open>
            <summary class="tree-summary">발주</summary>
            <div class="tree-items">
              <a class="tree-link" href="<?= e(url('/po_list.php?status=OPEN')) ?>">진행중인 발주 목록</a>
              <a class="tree-link" href="<?= e(url('/po_list.php?status=DONE')) ?>">완료된 발주 목록</a>
            </div>
          </details>

          <details class="tree-group" open>
            <summary class="tree-summary">입고</summary>
            <div class="tree-items">
              <a class="tree-link <?= e(nav_active('/receipt_list.php', $path)) ?>" href="<?= e(url('/receipt_list.php')) ?>">입고 조회</a>
              <a class="tree-link <?= e(nav_active('/receipt_create.php', $path)) ?>" href="<?= e(url('/receipt_create.php')) ?>">입고 처리</a>
            </div>
          </details>

          <?php if (is_admin()): ?>
            <details class="tree-group" open>
              <summary class="tree-summary">관리자</summary>
              <div class="tree-items">
                <a class="tree-link <?= e(nav_active('/admin/suppliers.php', $path)) ?>" href="<?= e(url('/admin/suppliers.php')) ?>">거래처 추가</a>
                <a class="tree-link <?= e(nav_active('/admin/items.php', $path)) ?>" href="<?= e(url('/admin/items.php')) ?>">품목관리</a>
                <a class="tree-link <?= e(nav_active('/admin/inventory.php', $path)) ?>" href="<?= e(url('/admin/inventory.php')) ?>">재고 관리</a>
                <a class="tree-link <?= e(nav_active('/admin/users.php', $path)) ?>" href="<?= e(url('/admin/users.php')) ?>">사용자</a>
              </div>
            </details>
          <?php endif; ?>
        </div>
      </aside>

      <main class="main">
        <div class="container">
          <?php $flash = flash_get(); if ($flash): ?>
            <div class="flash <?= e($flash['type']) ?>">
              <?= e($flash['message']) ?>
            </div>
          <?php endif; ?>
  <?php else: ?>
    <?php if ($isAuthPage): ?>
      <?php $flash = flash_get(); if ($flash): ?>
        <div class="auth-flash flash <?= e($flash['type']) ?>">
          <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="container">
        <?php $flash = flash_get(); if ($flash): ?>
          <div class="flash <?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
          </div>
        <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
