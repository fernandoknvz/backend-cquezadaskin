<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  "__FILE__" => __FILE__,
  "__DIR__" => __DIR__,
  "cwd" => getcwd(),
], JSON_PRETTY_PRINT);
