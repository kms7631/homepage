<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('error', '잘못된 접근입니다.');
  redirect('/notice.php');
}

// 보드형(좌 목록 + 우 상세) 화면으로 통합
redirect('/notice.php?id=' . $id);

