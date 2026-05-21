<?php
class JwtConfig {
    public static function secret(): string {
        $secret = getenv('JWT_SECRET');

        if ($secret === false || trim((string)$secret) === '') {
            $secret = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? '';
        }

        $secret = trim((string)$secret);
        if ($secret === '') {
            http_response_code(500);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "error" => "JWT_SECRET no esta definido en .env"
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        return $secret;
    }
}
