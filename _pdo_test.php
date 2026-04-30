<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$dsn = "mysql:host=127.0.0.1;port=3306;dbname=c2701519_iskio;charset=utf8mb4";

try {
  $pdo = new PDO($dsn, "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  echo "✅ PDO OK\n";
  var_dump($pdo->query("SELECT DATABASE() AS db, NOW() AS now")->fetch());
} catch (Throwable $e) {
  echo "❌ PDO ERROR:\n" . $e->getMessage() . "\n";
}
