<?php
declare(strict_types=1);

namespace GVid;

final class JobStore
{
    private static function path(string $id): string
    {
        return Config::storagePath('jobs/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.json');
    }

    public static function create(array $job): void
    {
        file_put_contents(self::path($job['id']), json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public static function get(string $id): ?array
    {
        $path = self::path($id);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path) ?: '', true);
        return is_array($data) ? $data : null;
    }

    public static function update(string $id, array $patch): ?array
    {
        $job = self::get($id);
        if (!$job) return null;
        $job = array_merge($job, $patch, ['updated_at' => gmdate('c')]);
        self::create($job);
        return $job;
    }
}
