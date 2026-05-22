<?php
/**
 * ===========================================================
 * 🧠 CQuezadaSkin Backend (PHP)
 * Router único - versión completa (hosting Ferozo compatible)
 * ===========================================================
 */

header("Content-Type: application/json; charset=UTF-8");

// === MODO DEBUG (puedes desactivarlo en producción) ===
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// === CONFIGURACIONES GLOBALES ===
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/config/database.php';

// === CAPTURAR RUTA Y MÉTODO ===
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method     = $_SERVER['REQUEST_METHOD'];

// Prefijo base (para hosting con /api)
// Prefijo base soportando subcarpeta (ej: /backend-cquezadaskin/api en XAMPP)
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'); // /backend-cquezadaskin
$basePath = $scriptDir . '/api'; // /backend-cquezadaskin/api

$route = $requestUri;

// Quitar basePath sólo si está al inicio
if (str_starts_with($route, $basePath)) {
    $route = substr($route, strlen($basePath));
} else {
    // fallback: hosting donde cuelga directo en /api
    if (str_starts_with($route, '/api')) {
        $route = substr($route, 4);
    }
}

$route = trim($route, '/');

// Compatibilidad extra: URLs que incluyen index.php o prefijos de subcarpeta.
if (str_contains($route, '/api/')) {
    $route = substr($route, strpos($route, '/api/') + 5);
}
if (str_starts_with($route, 'api/')) {
    $route = substr($route, 4);
}
if (str_starts_with($route, 'index.php/')) {
    $route = substr($route, strlen('index.php/'));
}


// === CUERPO JSON GLOBAL ===
$rawInput = file_get_contents("php://input");
$body = json_decode($rawInput, true) ?? [];
$params = $_GET ?? [];

/* ===========================================================
   🔍 RUTA BASE: información general de la API
   =========================================================== */
if ($route === '' || $route === false) {
    echo json_encode([
        "status" => "✅ API funcionando correctamente",
        "message" => "Bienvenido a CQuezadaSkin Backend",
        "rutas_disponibles" => [
            "CATEGORÍAS" => [
                "GET /api/categorias" => "Obtiene todas las categorías",
                "GET /api/categorias/1" => "Obtiene una categoría específica",
                "POST /api/categorias" => "Crea una nueva categoría",
                "PUT /api/categorias/1" => "Actualiza una categoría",
                "DELETE /api/categorias/1" => "Elimina una categoría"
            ],
            "SERVICIOS" => [
                "GET /api/servicios" => "Obtiene todos los servicios",
                "GET /api/servicios/1" => "Obtiene un servicio específico",
                "GET /api/servicios?categoria=facial" => "Filtra servicios por categoría",
                "POST /api/servicios" => "Crea un nuevo servicio",
                "PUT /api/servicios/1" => "Actualiza un servicio",
                "DELETE /api/servicios/1" => "Elimina un servicio"
            ],
            "OTROS" => [
                "GET /api/testimonios" => "Obtiene todos los testimonios",
                "GET /api/nosotros" => "Obtiene la información de 'Sobre Nosotros'",
                "GET /api/instagram" => "Obtiene los posts de Instagram",
                "GET /api/cuidados" => "Obtiene los cuidados post tratamiento",
                "GET /api/config" => "Configuración del sitio",
                "GET /api/citas" => "Gestión de citas"
            ],
            "ADMINISTRACIÓN" => [
                "POST /api/login" => "Inicia sesión de administrador",
                "POST /api/logout" => "Cierra sesión (invalida token)",
                "GET /api/me" => "Obtiene datos del usuario autenticado",
                "POST /api/register_admin" => "Crea un nuevo usuario administrador",
                "GET /api/account" => "Obtiene perfil del administrador",
                "PUT /api/account" => "Actualiza correo o contraseña del administrador",
                "POST /api/forgot-password" => "Solicita recuperación de contraseña",
                "POST /api/reset-password" => "Restablece contraseña con token"
            ]
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* ===========================================================
   🔀 RUTEO PRINCIPAL (soporta /recurso/:id)
   =========================================================== */

// Detectar segmentos de la ruta
$segments = explode('/', $route);
$resource = $segments[0] ?? '';
$subroute = $segments[1] ?? null;
$_GET['route'] = $route;

// Si el segundo segmento es numérico → lo tratamos como ID
if (is_numeric($subroute)) {
    $_GET['id'] = (int)$subroute;
}

/* ===========================================================
   🚦 DESPACHO DE RUTAS
   =========================================================== */
switch ($resource) {
    case 'categorias':
        require __DIR__ . '/routes/categorias.php';
        break;

    case 'servicios':
        require __DIR__ . '/routes/servicios.php';
        break;

    case 'testimonios':
        require __DIR__ . '/routes/testimonios.php';
        break;

    case 'nosotros':
        require __DIR__ . '/routes/sobreNosotros.php';
        break;

    case 'instagram':
        require __DIR__ . '/routes/instagram.php';
        break;

    case 'home-content':
    case 'contenido-home':
        require __DIR__ . '/routes/homeContent.php';
        break;

    case 'cuidados':
        require __DIR__ . '/routes/postCareTips.php';
        break;

    case 'config':
        require __DIR__ . '/routes/config.php';
        break;

    case 'citas':
        require __DIR__ . '/routes/citas.php';
        break;

    case 'reservas':
        require __DIR__ . '/routes/reservas.php';
        break;

    case 'disponibilidad':
        require __DIR__ . '/routes/disponibilidad.php';
        break;

    case 'clientes':
        require __DIR__ . '/routes/clientes.php';
        break;

    case 'admin':
        require __DIR__ . '/routes/admin.php';
        break;

    case 'login':
        require __DIR__ . '/routes/login.php';
        break;

    case 'logout':
        require __DIR__ . '/routes/logout.php';
        break;

    case 'me':
        require __DIR__ . '/routes/me.php';
        break;

    case 'register_admin':
        require __DIR__ . '/routes/register_admin.php';
        break;

    case 'account':
        require __DIR__ . '/routes/account.php';
        break;

    case 'forgot-password':
        require __DIR__ . '/routes/forgot_password.php';
        break;

    case 'reset-password':
        require __DIR__ . '/routes/reset_password.php';
        break;

    case 'dashboard':
        require_once __DIR__ . '/routes/dashboard.php';
        break;

    default:
        http_response_code(404);
        echo json_encode([
            "error" => "Ruta no encontrada",
            "ruta_recibida" => $route
        ]);
        break;
}
