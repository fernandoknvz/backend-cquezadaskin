<?php
/**
 * ===========================================================
 * Configuración de conexión PDO + carga robusta de .env
 * Compatible Windows / XAMPP / Hosting Linux
 * ===========================================================
 */

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignorar comentarios / vacíos
            if ($line === '' || str_starts_with($line, '#')) continue;

            // Aceptar formato: export KEY=VALUE
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Quitar comillas si existen
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $value = trim($value);

            // Exportar a env
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// === Cargar variables desde .env (raíz del proyecto) ===
loadEnv(__DIR__ . '/../.env');

// Defaults seguros
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: 'root';

$passEnv = getenv('DB_PASS');
$pass = ($passEnv === false) ? '' : (string)$passEnv;

$charset = getenv('DB_CHARSET') ?: 'utf8mb4';
$charset = trim($charset);

// Fail fast si falta DB_NAME
if ($db === '') {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(["error" => "DB_NAME no está definido en .env"]);
    exit;
}

// DSN robusto (incluye puerto). Evitamos charset en DSN para compatibilidad hosting.
$dsn = "mysql:host={$host};port={$port};dbname={$db}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,

        // Fuerza charset sin usar charset= en DSN (evita error 2019 en algunos casos)
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
    ]);

    // Mantener compatibilidad con código existente que use $GLOBALS['pdo']
    if (!isset($GLOBALS['pdo'])) {
        $GLOBALS['pdo'] = $pdo;
    }

} catch (PDOException $e) {
    // Log local (este archivo lo vamos a ignorar en git)
    error_log("DB Connection Error: " . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../error_log.txt');

    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');

    // No exponer credenciales/host en respuesta (seguridad)
    echo json_encode([
        "error" => "Error de conexión con la base de datos",
        "detail" => $e->getMessage(), // si quieres ocultarlo en prod, lo quitamos luego con APP_DEBUG
    ]);

    exit;
}