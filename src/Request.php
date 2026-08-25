<?php
declare(strict_types=1);

namespace GVid;

final class Request
{
    public static function json(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if (strlen($raw) > 1024 * 1024 * 12) {
            Response::error('Request body is too large.', 413, 'payload_too_large');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::error('Invalid JSON body.', 400, 'invalid_json');
        }
        return $data;
    }

    public static function query(string $key): ?string
    {
        $value = $_GET[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
