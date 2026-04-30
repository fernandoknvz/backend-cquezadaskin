<?php

class Url
{
    public static function normalizeAssetUrl(string $url): string
    {
        $u = trim($url);
        if ($u === '') return '';

        // Si ya es absoluta, devolver tal cual
        if (preg_match('#^https?://#i', $u)) return $u;

        // Si es relativa, prefijar con ASSET_BASE_URL
        $base = rtrim(getenv('ASSET_BASE_URL') ?: '', '/');
        if ($base === '') return $u;

        return $base . '/' . ltrim($u, '/');
    }
}
