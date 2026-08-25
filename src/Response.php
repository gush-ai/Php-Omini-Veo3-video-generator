<?php
declare(strict_types=1);

namespace GVid;

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $status = 400, ?string $code = null): never
    {
        self::json([
            'success' => false,
            'error' => [
                'code' => $code ?: 'request_error',
                'message' => $message
            ]
        ], $status);
    }
}
