<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

date_default_timezone_set(APP_TIMEZONE);

// 운영 환경 기본: 화면에 에러를 노출하지 않음(로그는 유지)
ini_set('log_errors', '1');
ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Helpers
function e(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path): string {
  $path = '/' . ltrim($path, '/');
  if (defined('APP_BASE') && APP_BASE !== '') {
    return APP_BASE . $path;
  }
  return $path;
}

function redirect(string $path): void {
  header('Location: ' . url($path));
  exit;
}

function is_post(): bool {
  return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function starts_with(string $haystack, string $needle): bool {
  if ($needle === '') {
    return true;
  }
  return strncmp($haystack, $needle, strlen($needle)) === 0;
}

// PDO
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  // 서버 시간/타임존 일관성(+09:00) 유지
  // - DB의 NOW()/CURRENT_TIMESTAMP가 세션 time_zone에 영향을 받는 환경에서도 동일하게 동작하도록 고정
  // - 권한/설정에 따라 실패할 수 있으므로, 실패 시에는 조용히 무시
  try {
    $pdo->exec("SET time_zone = '+09:00'");
  } catch (Throwable $e) {
    // ignore
  }

  return $pdo;
}

// Autoload classes
spl_autoload_register(function ($class) {
  $base = __DIR__ . '/../classes/';
  $path = $base . str_replace('\\', '/', $class) . '.php';
  if (is_file($path)) {
    require_once $path;
  }
});

require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/auth.php';
