<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'GVid\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

date_default_timezone_set('UTC');

$storage = getenv('GV_STORAGE_PATH') ?: 'storage';
$storage = dirname(__DIR__) . '/' . trim($storage, '/');

foreach ([$storage, "$storage/jobs", "$storage/videos", "$storage/locks"] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
}
