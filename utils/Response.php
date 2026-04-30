<?php
class Response {
    public static function json($data, $code = 200) {
        http_response_code($code);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error($message, $code = 400) {
        self::json(["error" => $message], $code);
    }
}
