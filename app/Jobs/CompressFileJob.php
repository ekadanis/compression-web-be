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
use RuntimeException;

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

        $durationSeconds = $this->resolveDurationSeconds($inputPath);
        $command = $this->buildCommand($inputPath, $outputPath);
        $startedAt = microtime(true);

        Log::info('CompressFileJob: running', ['command' => $command]);

        [$exitCode, $output] = $this->runCommandWithProgress($command, $durationSeconds, $startedAt);

        if ($exitCode !== 0) {
            $errorMsg = implode("\n", array_slice($output, -20)); // last 20 lines
            $this->compression->update([
                'status' => 'failed',
                'estimated_seconds_remaining' => null,
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
            'progress' => 100,
            'estimated_seconds_remaining' => null,
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
            'progress'      => 0,
            'estimated_seconds_remaining' => null,
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

        $cmd .= " -progress pipe:1 -nostats " . escapeshellarg($output);

        return $cmd;
    }

    /**
     * @return array{0:int,1:array<int,string>}
     */
    private function runCommandWithProgress(string $command, ?float $durationSeconds, float $startedAt): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start FFmpeg process.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stderrOutput = [];
        $stdoutBuffer = '';
        $stderrBuffer = '';

        while (true) {
            $status = proc_get_status($process);
            $stdoutChunk = stream_get_contents($pipes[1]);
            $stderrChunk = stream_get_contents($pipes[2]);

            if ($stdoutChunk !== false && $stdoutChunk !== '') {
                $stdoutBuffer .= $stdoutChunk;

                while (($pos = strpos($stdoutBuffer, "\n")) !== false) {
                    $line = trim(substr($stdoutBuffer, 0, $pos));
                    $stdoutBuffer = substr($stdoutBuffer, $pos + 1);
                    $this->handleProgressLine($line, $durationSeconds, $startedAt);
                }
            }

            if ($stderrChunk !== false && $stderrChunk !== '') {
                $stderrBuffer .= $stderrChunk;

                while (($pos = strpos($stderrBuffer, "\n")) !== false) {
                    $line = trim(substr($stderrBuffer, 0, $pos));
                    $stderrBuffer = substr($stderrBuffer, $pos + 1);
                    if ($line !== '') {
                        $stderrOutput[] = $line;
                    }
                }
            }

            if (! $status['running']) {
                break;
            }

            usleep(200000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if (trim($stderrBuffer) !== '') {
            $stderrOutput[] = trim($stderrBuffer);
        }

        if (trim($stdoutBuffer) !== '') {
            $stderrOutput[] = trim($stdoutBuffer);
        }

        return [$exitCode, $stderrOutput];
    }

    private function handleProgressLine(string $line, ?float $durationSeconds, float $startedAt): void
    {
        if ($line === '' || ! str_contains($line, '=')) {
            return;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, null);

        if ($key !== 'out_time_ms' || $value === null || ! is_numeric($value) || ! $durationSeconds || $durationSeconds <= 0) {
            return;
        }

        $processedSeconds = ((float) $value) / 1000000;
        $progress = min(99, max(1, (int) floor(($processedSeconds / $durationSeconds) * 100)));
        $elapsed = max(1, microtime(true) - $startedAt);
        $eta = $progress > 0 ? max(1, (int) round(($elapsed / $progress) * (100 - $progress))) : null;

        if ($progress !== (int) $this->compression->progress) {
            $this->compression->forceFill([
                'progress' => $progress,
                'estimated_seconds_remaining' => $eta,
            ])->save();
        }
    }

    private function resolveDurationSeconds(string $inputPath): ?float
    {
        if ($this->file->duration && $this->file->duration > 0) {
            return (float) $this->file->duration;
        }

        $cmd = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($inputPath);
        $output = shell_exec($cmd);

        if (! $output) {
            return null;
        }

        $duration = (float) trim($output);

        return $duration > 0 ? $duration : null;
    }
}
