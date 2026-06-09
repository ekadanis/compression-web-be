<?php

namespace App\Jobs;

use App\Models\Upload;
use App\Services\YoutubeUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadToYoutubeJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 14400;

    public function __construct(public int $uploadId)
    {
        $this->onQueue('youtube');
    }

    public function handle(YoutubeUploadService $youtubeUploadService): void
    {
        $upload = Upload::query()->with(['user.youtubeAccount', 'uploadable'])->findOrFail($this->uploadId);

        if (! in_array($upload->status, ['pending', 'processing'], true)) {
            return;
        }

        if ($upload->cancel_requested_at) {
            $upload->forceFill(['status' => 'cancelled'])->save();
            return;
        }

        $upload->forceFill([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $result = $youtubeUploadService->upload($upload);
        } catch (\RuntimeException $exception) {
            $upload->refresh();

            if ($upload->cancel_requested_at || $exception->getMessage() === 'Upload dibatalkan.') {
                $upload->forceFill([
                    'status' => 'cancelled',
                    'error_message' => null,
                ])->save();

                return;
            }

            throw $exception;
        }

        $upload->refresh();

        if ($upload->cancel_requested_at) {
            if (! empty($result['external_id'])) {
                $youtubeUploadService->deleteVideo($upload, $result['external_id']);
            }

            $upload->forceFill([
                'status' => 'cancelled',
                'error_message' => null,
            ])->save();

            return;
        }

        $upload->forceFill([
            'status' => 'uploaded',
            'progress' => 100,
            'uploaded_at' => now(),
            'external_id' => $result['external_id'],
            'url' => $result['url'],
            'metadata' => $result['metadata'],
        ])->save();
    }

    public function failed(\Throwable $exception): void
    {
        Upload::query()->whereKey($this->uploadId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('YouTube upload job failed.', [
            'upload_id' => $this->uploadId,
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }
}
