<?php
// DB 설정
// 운영/공유 시에는 환경변수 사용을 권장합니다.

$_dbHost = getenv('DB_HOST');
$_dbName = getenv('DB_NAME');
$_dbUser = getenv('DB_USER');
$_dbPass = getenv('DB_PASS');

define('DB_HOST', $_dbHost !== false ? $_dbHost : '127.0.0.1');
define('DB_NAME', $_dbName !== false ? $_dbName : 'homepage_demo');
define('DB_USER', $_dbUser !== false ? $_dbUser : 'root');
// 비밀번호가 빈 문자열인 환경(예: root 비번 없음)도 지원
define('DB_PASS', $_dbPass !== false ? $_dbPass : '0000');
define('DB_CHARSET', 'utf8mb4');

// 앱 설정

define('APP_NAME', '유통 물류 운영 관리 시스템');

// Apache DocumentRoot 하위 서브폴더에 설치한 경우 설정 (예: /homepage)
// - 우선순위: 환경변수 APP_BASE > 자동 감지(DOCUMENT_ROOT 기준) > ''
$__appBaseEnv = getenv('APP_BASE');
$__appBase = is_string($__appBaseEnv) ? trim($__appBaseEnv) : '';
if ($__appBase === '') {
	$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : false;
	$appRoot = realpath(__DIR__ . '/..');
	if ($docRoot && $appRoot) {
		$docRootNorm = rtrim(str_replace('\\', '/', $docRoot), '/');
		$appRootNorm = rtrim(str_replace('\\', '/', $appRoot), '/');
		if (stripos($appRootNorm, $docRootNorm) === 0) {
			$rel = substr($appRootNorm, strlen($docRootNorm));
			$rel = $rel === false ? '' : $rel;
			$__appBase = $rel;
		}
	}
}
define('APP_BASE', rtrim($__appBase, '/'));

define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'Asia/Seoul');

// 디버그(에러 화면 출력) 설정
// - 운영 환경에서는 APP_DEBUG=0 권장
// - 개발 환경에서만 APP_DEBUG=1
$__appDebugEnv = getenv('APP_DEBUG');
$__appDebug = false;
if ($__appDebugEnv !== false) {
	$__appDebug = filter_var($__appDebugEnv, FILTER_VALIDATE_BOOLEAN);
}
define('APP_DEBUG', $__appDebug);

// 안전재고 기본값(온보딩/템플릿 복제 등에서 사용)
$__defaultMinStockEnv = getenv('DEFAULT_MIN_STOCK');
$__defaultMinStock = is_string($__defaultMinStockEnv) && trim($__defaultMinStockEnv) !== '' ? (int)$__defaultMinStockEnv : 50;
if ($__defaultMinStock <= 0) {
	$__defaultMinStock = 50;
}
define('DEFAULT_MIN_STOCK', $__defaultMinStock);
