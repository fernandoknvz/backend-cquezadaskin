<?php
class JwtConfig {
    public static function secret(): string {
        $secret = getenv('JWT_SECRET');

        if ($secret === false || trim((string)$secret) === '') {
            $secret = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? '';
        }

        $secret = trim((string)$secret);
        return $secret !== '' ? $secret : 'clave_secreta_segura_cqs';
    }
}
