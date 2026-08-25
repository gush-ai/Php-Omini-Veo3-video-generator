<?php
declare(strict_types=1);

namespace GVid;

final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }

    public static function storagePath(string $sub = ''): string
    {
        $base = dirname(__DIR__) . '/' . trim((string) self::get('GV_STORAGE_PATH', 'storage'), '/');
        return $sub ? $base . '/' . trim($sub, '/') : $base;
    }
}
