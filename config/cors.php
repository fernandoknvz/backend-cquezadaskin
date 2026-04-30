<?php
$allowed_origins = [
    "https://www.masoterapiaiskio.cl",
    "https://masoterapiaiskio.cl",
    "https://agenda.masoterapiaiskio.cl",
    "https://agenda-dev.masoterapiaiskio.cl",
    "https://api-dev.masoterapiaiskio.cl",
    "http://localhost:5173",
    "http://127.0.0.1:5173",
    "http://192.168.1.142:5173"
];

$envOrigins = getenv('CORS_ALLOWED_ORIGINS') ?: ($_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
if ($envOrigins !== '') {
    $allowed_origins = array_values(array_unique(array_merge(
        $allowed_origins,
        array_filter(array_map('trim', explode(',', $envOrigins)))
    )));
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? null;

if ($origin && in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
