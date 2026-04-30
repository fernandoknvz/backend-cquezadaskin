<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$controller = new AuthController($pdo);
$controller->logout();
