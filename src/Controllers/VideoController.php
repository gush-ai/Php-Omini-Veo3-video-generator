<?php
declare(strict_types=1);

namespace GVid\Controllers;

use GVid\Auth;
use GVid\Config;
use GVid\JobStore;
use GVid\Request;
use GVid\Response;
use GVid\Providers\GoogleVeoProvider;

final class VideoController
{
    public function generate(): void
    {
        Auth::require();
        $input = Request::json();

        $prompt = trim((string)($input['prompt'] ?? ''));
        $maxPrompt = (int) Config::get('GV_MAX_PROMPT_LENGTH', 5000);

        if ($prompt === '') {
            Response::error('prompt is required.', 422, 'missing_prompt');
        }
        if (mb_strlen($prompt) > $maxPrompt) {
            Response::error('prompt is too long.', 422, 'prompt_too_long');
        }

        $model = (string)($input['model'] ?? 'veo-3.1-generate-preview');
        $allowedModels = [
            'veo-3.1-generate-preview',
            'veo-3.1-fast-generate-preview',
            'veo-3.1-lite-generate-preview'
        ];
        if (!in_array($model, $allowedModels, true)) {
            Response::error('Unsupported model.', 422, 'invalid_model');
        }

        $aspect = $input['aspect_ratio'] ?? '16:9';
        if (!in_array($aspect, ['16:9', '9:16'], true)) {
            Response::error('aspect_ratio must be 16:9 or 9:16.', 422, 'invalid_aspect_ratio');
        }

        $resolution = $input['resolution'] ?? '720p';
        if (!in_array($resolution, ['720p', '1080p', '4k'], true)) {
            Response::error('Invalid resolution.', 422, 'invalid_resolution');
        }

        if (!empty($input['image']['data'])) {
            $raw = (string)$input['image']['data'];
            $estimated = (int)(strlen($raw) * 0.75);
            $max = (int)Config::get('GV_MAX_IMAGE_BYTES', 10485760);
            if ($estimated > $max) {
                Response::error('Reference image is too large.', 413, 'image_too_large');
            }
        }

        $jobId = bin2hex(random_bytes(16));
        $job = [
            'id' => $jobId,
            'status' => 'submitting',
            'provider' => 'google_veo',
            'model' => $model,
            'prompt' => $prompt,
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c')
        ];
        JobStore::create($job);

        try {
            $provider = new GoogleVeoProvider();
            $result = $provider->generate([
                'prompt' => $prompt,
                'model' => $model,
                'aspect_ratio' => $aspect,
                'resolution' => $resolution,
                'number_of_videos' => 1,
                'image' => $input['image'] ?? null,
            ]);

            $job = JobStore::update($jobId, [
                'status' => 'processing',
                'operation_name' => $result['operation_name'],
                'parameters' => [
                    'aspect_ratio' => $aspect,
                    'resolution' => $resolution
                ]
            ]);

            Response::json([
                'success' => true,
                'job_id' => $jobId,
                'status' => 'processing',
                'operation' => $result['operation_name']
            ], 202);
        } catch (\Throwable $e) {
            JobStore::update($jobId, [
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);
            Response::error($e->getMessage(), 502, 'provider_error');
        }
    }

    public function status(): void
    {
        Auth::require();
        $id = Request::query('job_id');
        if (!$id) Response::error('job_id is required.', 422, 'missing_job_id');

        $job = JobStore::get($id);
        if (!$job) Response::error('Job not found.', 404, 'job_not_found');

        if (!empty($job['operation_name']) && in_array($job['status'], ['processing', 'submitting'], true)) {
            try {
                $provider = new GoogleVeoProvider();
                $remote = $provider->status($job['operation_name']);

                if (!empty($remote['done'])) {
                    if (!empty($remote['error'])) {
                        $job = JobStore::update($id, [
                            'status' => 'failed',
                            'error' => $remote['error']['message'] ?? 'Video generation failed.',
                            'provider_response' => $remote
                        ]);
                    } else {
                        $uri = $remote['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] ?? null;

                        if ($uri) {
                            $job = JobStore::update($id, [
                                'status' => 'completed',
                                'video_uri' => $uri,
                                'provider_response' => $remote
                            ]);
                        } else {
                            $job = JobStore::update($id, [
                                'status' => 'failed',
                                'error' => 'Provider completed without a video URI.',
                                'provider_response' => $remote
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Keep the job processing; transient provider polling errors should not destroy the job.
            }
        }

        Response::json([
            'success' => true,
            'job' => self::publicJob($job)
        ]);
    }

    public function result(): void
    {
        Auth::require();
        $id = Request::query('job_id');
        if (!$id) Response::error('job_id is required.', 422, 'missing_job_id');

        $job = JobStore::get($id);
        if (!$job) Response::error('Job not found.', 404, 'job_not_found');

        if (($job['status'] ?? '') !== 'completed' || empty($job['video_uri'])) {
            Response::error('Video is not ready.', 409, 'video_not_ready');
        }

        $local = Config::storagePath('videos/' . $id . '.mp4');

        if (!is_file($local)) {
            try {
                (new GoogleVeoProvider())->download($job['video_uri'], $local);
            } catch (\Throwable $e) {
                Response::error($e->getMessage(), 502, 'download_error');
            }
        }

        $job = JobStore::update($id, [
            'local_video' => 'videos/' . $id . '.mp4'
        ]);

        Response::json([
            'success' => true,
            'job_id' => $id,
            'status' => 'completed',
            'video_url' => '/video/file?job_id=' . rawurlencode($id)
        ]);
    }

    public function file(): void
    {
        Auth::require();
        $id = Request::query('job_id');
        if (!$id) Response::error('job_id is required.', 422, 'missing_job_id');

        $job = JobStore::get($id);
        if (!$job || empty($job['local_video'])) {
            Response::error('Video file not available.', 404, 'file_not_found');
        }

        $path = Config::storagePath($job['local_video']);
        $real = realpath($path);
        $base = realpath(Config::storagePath('videos'));
        if (!$real || !$base || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
            Response::error('Video file not available.', 404, 'file_not_found');
        }

        header('Content-Type: video/mp4');
        header('Content-Length: ' . filesize($real));
        header('Content-Disposition: inline; filename="gvid-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.mp4"');
        header('X-Content-Type-Options: nosniff');
        readfile($real);
        exit;
    }

    public function cancel(): void
    {
        Auth::require();
        $input = Request::json();
        $id = trim((string)($input['job_id'] ?? ''));
        if (!$id) Response::error('job_id is required.', 422, 'missing_job_id');

        $job = JobStore::get($id);
        if (!$job) Response::error('Job not found.', 404, 'job_not_found');

        $job = JobStore::update($id, [
            'status' => 'cancelled',
            'cancelled_at' => gmdate('c')
        ]);

        Response::json([
            'success' => true,
            'job' => self::publicJob($job),
            'note' => 'This marks the GVid job cancelled. Provider-side cancellation is not available through this adapter.'
        ]);
    }

    public function health(): void
    {
        Response::json([
            'success' => true,
            'service' => 'GVid',
            'status' => 'ok',
            'time' => gmdate('c')
        ]);
    }

    private static function publicJob(array $job): array
    {
        unset($job['prompt'], $job['provider_response'], $job['video_uri']);
        return $job;
    }
}
