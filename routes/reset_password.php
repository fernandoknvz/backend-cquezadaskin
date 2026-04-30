<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/PasswordResetController.php';

$controller = new PasswordResetController($pdo);
$body = json_decode(file_get_contents("php://input"), true) ?? [];
$controller->resetPassword($body);
