<?php

define('FLASH_KEY', '__flash');

function flash_set(string $type, string $message): void {
  $_SESSION[FLASH_KEY] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array {
  if (!isset($_SESSION[FLASH_KEY])) {
    return null;
  }
  $flash = $_SESSION[FLASH_KEY];
  unset($_SESSION[FLASH_KEY]);
  return is_array($flash) ? $flash : null;
}
