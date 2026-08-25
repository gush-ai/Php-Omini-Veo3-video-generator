<?php
declare(strict_types=1);

namespace GVid\Providers;

use GVid\Config;
use RuntimeException;

final class GoogleVeoProvider implements VideoProvider
{
    public function generate(array $input): array
    {
        $key = (string) Config::get('GEMINI_API_KEY', '');
        if ($key === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $base = rtrim((string) Config::get('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $model = $input['model'] ?? 'veo-3.1-generate-preview';

        $instance = ['prompt' => $input['prompt']];

        if (!empty($input['image']['data'])) {
            $mime = $input['image']['mime_type'] ?? 'image/jpeg';
            $data = $input['image']['data'];

            if (str_starts_with($data, 'data:')) {
                if (!preg_match('#^data:([^;]+);base64,(.+)$#s', $data, $m)) {
                    throw new RuntimeException('Invalid image data URL.');
                }
                $mime = $m[1];
                $data = $m[2];
            }

            if (base64_decode($data, true) === false) {
                throw new RuntimeException('Invalid base64 image.');
            }

            $instance['image'] = [
                'inlineData' => [
                    'mimeType' => $mime,
                    'data' => $data
                ]
            ];
        }

        $parameters = [];
        foreach (['aspect_ratio' => 'aspectRatio', 'resolution' => 'resolution', 'number_of_videos' => 'numberOfVideos'] as $from => $to) {
            if (isset($input[$from])) {
                $parameters[$to] = $input[$from];
            }
        }

        $payload = ['instances' => [$instance]];
        if ($parameters) {
            $payload['parameters'] = $parameters;
        }

        $url = $base . '/models/' . rawurlencode($model) . ':predictLongRunning';
        $response = $this->request('POST', $url, $payload, $key);

        if (empty($response['name'])) {
            throw new RuntimeException('Google did not return an operation name.');
        }

        return ['operation_name' => $response['name'], 'raw' => $response];
    }

    public function status(string $operationName): array
    {
        $key = (string) Config::get('GEMINI_API_KEY', '');
        $base = rtrim((string) Config::get('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        return $this->request('GET', $base . '/' . ltrim($operationName, '/'), null, $key);
    }

    public function download(string $uri, string $destination): void
    {
        $key = (string) Config::get('GEMINI_API_KEY', '');
        $fp = fopen($destination, 'wb');
        if (!$fp) throw new RuntimeException('Unable to create video file.');

        $ch = curl_init($uri);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $key],
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $code >= 400) {
            @unlink($destination);
            throw new RuntimeException('Video download failed: ' . ($err ?: 'HTTP ' . $code));
        }
    }

    private function request(string $method, string $url, ?array $payload, string $key): array
    {
        $ch = curl_init($url);
        $headers = [
            'x-goog-api-key: ' . $key,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 120,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err) {
            throw new RuntimeException('Provider request failed: ' . $err);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Provider returned invalid JSON.');
        }

        if ($code >= 400) {
            $message = $data['error']['message'] ?? ('HTTP ' . $code);
            throw new RuntimeException('Google API error: ' . $message);
        }

        return $data;
    }
}
