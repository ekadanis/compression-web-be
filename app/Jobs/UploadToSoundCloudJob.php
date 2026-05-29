<?php

namespace App\Jobs;

use App\Models\Upload;
use App\Services\SoundCloudUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadToSoundCloudJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 14400;

    public function __construct(public int $uploadId)
    {
        $this->onQueue('soundcloud');
    }

    public function handle(SoundCloudUploadService $soundCloudUploadService): void
    {
        $upload = Upload::query()->with(['user.soundcloudAccount', 'uploadable'])->findOrFail($this->uploadId);

        if (! in_array($upload->status, ['pending', 'processing'], true)) {
            return;
        }

        $upload->forceFill([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        $result = $soundCloudUploadService->upload($upload);

        $upload->forceFill([
            'status' => 'uploaded',
            'progress' => 100,
            'uploaded_at' => now(),
            'external_id' => $result['external_id'],
            'url' => $result['url'],
            'metadata' => array_merge($upload->metadata ?? [], $result['metadata'] ?? []),
        ])->save();
    }

    public function failed(\Throwable $exception): void
    {
        Upload::query()->whereKey($this->uploadId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('SoundCloud upload job failed.', [
            'upload_id' => $this->uploadId,
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }
}
