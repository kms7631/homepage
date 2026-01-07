<?php
declare(strict_types=1);

function dashboard_parse_range(string $range): array {
  $range = trim($range);
  if (!in_array($range, ['7d', '30d', 'thisMonth', 'lastMonth'], true)) {
    $range = '30d';
  }

  $tz = new DateTimeZone(APP_TIMEZONE);
  $today = new DateTimeImmutable('today', $tz);

  if ($range === '7d') {
    $from = $today->sub(new DateInterval('P6D'));
    $to = $today;
  } elseif ($range === '30d') {
    $from = $today->sub(new DateInterval('P29D'));
    $to = $today;
  } elseif ($range === 'thisMonth') {
    $from = $today->modify('first day of this month');
    $to = $today;
  } else { // lastMonth
    $from = $today->modify('first day of last month');
    $to = $today->modify('last day of last month');
  }

  return [
    'range' => $range,
    'from' => $from->format('Y-m-d'),
    'to' => $to->format('Y-m-d'),
  ];
}

function dashboard_effective_supplier_id(int $requestedSupplierId): int {
  if (is_admin()) {
    return $requestedSupplierId > 0 ? $requestedSupplierId : 0;
  }
  return (int)(current_supplier_id() ?? 0);
}

function dashboard_json(array $payload, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
