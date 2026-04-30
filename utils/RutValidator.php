<?php
class RutValidator {
    public static function normalize(?string $rut): ?string {
        $clean = strtoupper(preg_replace('/[^0-9Kk]/', '', (string)$rut));

        if (!preg_match('/^([0-9]{7,8})([0-9K])$/', $clean, $matches)) {
            return null;
        }

        $number = $matches[1];
        $dv = $matches[2];

        if (self::calculateVerifier($number) !== $dv) {
            return null;
        }

        return number_format((int)$number, 0, '', '.') . '-' . $dv;
    }

    private static function calculateVerifier(string $number): string {
        $sum = 0;
        $multiplier = 2;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += ((int)$number[$i]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $value = 11 - ($sum % 11);

        if ($value === 11) {
            return '0';
        }

        if ($value === 10) {
            return 'K';
        }

        return (string)$value;
    }
}
