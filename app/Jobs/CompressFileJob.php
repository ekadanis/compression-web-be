<?php

namespace App\Jobs;

use App\Models\Compression;
use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CompressFileJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public Compression $compression,
        public File $file,
    ) {}

    public function handle(): void
    {
        $inputPath  = Storage::disk('public')->path($this->file->original_path);
        $outputPath = Storage::disk('public')->path($this->compression->path);

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $command = $this->buildCommand($inputPath, $outputPath);

        Log::info('CompressFileJob: running', ['command' => $command]);

        $output   = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $errorMsg = implode("\n", array_slice($output, -20)); // last 20 lines
            $this->compression->update([
                'status'        => 'failed',
                'error_message' => $errorMsg,
            ]);
            $this->file->update(['status' => 'failed']);
            Log::error('CompressFileJob: failed', ['output' => $errorMsg]);
            return;
        }

        $size = file_exists($outputPath) ? filesize($outputPath) : 0;

        $this->compression->update([
            'status' => 'done',
            'size'   => $size,
        ]);

        // Mark parent file as done if all compressions are done
        $pendingCount = $this->file->compressions()
            ->whereIn('status', ['processing'])
            ->count();

        if ($pendingCount === 0) {
            $this->file->update(['status' => 'done']);
        }

        Log::info('CompressFileJob: done', ['compression_id' => $this->compression->id]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->compression->update([
            'status'        => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
        $this->file->update(['status' => 'failed']);
        Log::error('CompressFileJob: exception', ['error' => $exception->getMessage()]);
    }

    private function buildCommand(string $input, string $output): string
    {
        $c = $this->compression;

        // Base command
        $cmd = "ffmpeg -y -i " . escapeshellarg($input);

        if ($this->file->isVideo()) {
            // Video compression
            if ($c->codec) {
                $cmd .= " -vcodec " . escapeshellarg($c->codec);
            }
            if ($c->resolution) {
                $cmd .= " -vf scale=" . escapeshellarg($c->resolution);
            }
            if ($c->fps) {
                $cmd .= " -r " . (int) $c->fps;
            }
            if ($c->bitrate) {
                $cmd .= " -b:v " . (int) $c->bitrate . "k";
            }
            if ($c->audio_bitrate) {
                $cmd .= " -b:a " . (int) $c->audio_bitrate . "k";
            }
        } else {
            // Audio compression
            $acodec = $c->codec;

            // Auto-correct incompatible audio codecs for certain containers to prevent errors or unplayable files
            if ($c->format === 'ogg') {
                $acodec = 'libvorbis';
            } elseif ($c->format === 'aac') {
                $acodec = 'aac';
            } elseif ($c->format === 'wav') {
                $acodec = 'pcm_s16le';
            } elseif ($c->format === 'mp3') {
                $acodec = 'libmp3lame';
            }

            if ($acodec) {
                $cmd .= " -acodec " . escapeshellarg($acodec);
            }
            if ($c->audio_bitrate) {
                $cmd .= " -b:a " . (int) $c->audio_bitrate . "k";
            }
            if ($c->sample_rate) {
                $cmd .= " -ar " . (int) $c->sample_rate;
            }
            if ($c->channel === 'mono') {
                $cmd .= " -ac 1";
            } elseif ($c->channel === 'stereo') {
                $cmd .= " -ac 2";
            }
            // Remove video stream for audio output
            $cmd .= " -vn";
        }

        $cmd .= " " . escapeshellarg($output);

        return $cmd;
    }
}
