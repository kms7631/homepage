<?php
require_once __DIR__ . '/includes/bootstrap.php';

auth_logout();
flash_set('success', '로그아웃 되었습니다.');
redirect('/login.php');
