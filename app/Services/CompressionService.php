<?php

namespace App\Services;

use App\Jobs\CompressFileJob;
use App\Models\Compression;
use App\Models\File;
use Illuminate\Support\Str;

class CompressionService
{
    /**
     * Create a compression record and dispatch the FFMPEG job.
     */
    public function create(array $data, File $file): Compression
    {
        $uuid      = Str::uuid();
        $format    = $data['format'];
        $outputPath = "compressed/{$file->id}/{$uuid}.{$format}";

        $compression = Compression::create([
            'file_id'       => $file->id,
            'format'        => $format,
            'codec'         => $data['codec']         ?? null,
            'bitrate'       => $data['bitrate']        ?? null,
            'resolution'    => $data['resolution']     ?? null,
            'fps'           => $data['fps']            ?? null,
            'audio_bitrate' => $data['audio_bitrate']  ?? null,
            'sample_rate'   => $data['sample_rate']    ?? null,
            'channel'       => $data['channel']        ?? null,
            'is_recommended'=> $data['is_recommended'] ?? false,
            'path'          => $outputPath,
            'status'        => 'processing',
        ]);

        // Mark parent file as processing
        $file->update(['status' => 'processing']);

        // Dispatch job to queue
        CompressFileJob::dispatch($compression, $file);

        return $compression;
    }

    /**
     * Delete a compression record and its file.
     */
    public function delete(Compression $compression): void
    {
        if ($compression->path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($compression->path);
        }
        $compression->delete();
    }

    /**
     * Build a list of compressions for comparison.
     */
    public function compare(File $file, array $compressionIds): array
    {
        return $file->compressions()
            ->whereIn('id', $compressionIds)
            ->where('status', 'done')
            ->get()
            ->toArray();
    }
}
