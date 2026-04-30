<?php
require_once __DIR__ . '/config/database.php'; // asegura loadEnv()

header('Content-Type: application/json; charset=utf-8');

function g($k) {
  $v = getenv($k);
  if ($v !== false && $v !== '') return $v;
  return $_ENV[$k] ?? null;
}

echo json_encode([
  "MAIL_HOST" => g("MAIL_HOST"),
  "MAIL_PORT" => g("MAIL_PORT"),
  "MAIL_USERNAME" => g("MAIL_USERNAME") ?: g("MAIL_USER"),
  "MAIL_FROM_ADDRESS" => g("MAIL_FROM_ADDRESS") ?: g("MAIL_FROM"),
  "MAIL_FROM_NAME" => g("MAIL_FROM_NAME"),
  "MAIL_NOTIFY_TO" => g("MAIL_NOTIFY_TO"),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);