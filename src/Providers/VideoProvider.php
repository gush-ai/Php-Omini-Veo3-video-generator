<?php
declare(strict_types=1);

namespace GVid\Providers;

interface VideoProvider
{
    public function generate(array $input): array;
    public function status(string $operationName): array;
    public function download(string $uri, string $destination): void;
}
