<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

date_default_timezone_set(APP_TIMEZONE);

ini_set('display_errors', '1');
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
