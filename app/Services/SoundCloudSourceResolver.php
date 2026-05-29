<?php

namespace App\Services;

use App\Models\Compression;
use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SoundCloudSourceResolver
{
    /**
     * @return array{model: Model, path: string, title: string, mime_type: string, file_name: string}
     */
    public function resolve(User $user, string $sourceType, int $sourceId): array
    {
        if ($sourceType === 'file') {
            $file = File::query()
                ->where('user_id', $user->id)
                ->where('type', 'audio')
                ->findOrFail($sourceId);

            return [
                'model' => $file,
                'path' => Storage::disk('public')->path($file->original_path),
                'title' => pathinfo($file->name, PATHINFO_FILENAME),
                'mime_type' => $file->mime_type,
                'file_name' => $file->name,
            ];
        }

        if ($sourceType === 'compression') {
            $compression = Compression::query()
                ->where('status', 'done')
                ->whereHas('file', function ($query) use ($user) {
                    $query->where('user_id', $user->id)->where('type', 'audio');
                })
                ->with('file')
                ->findOrFail($sourceId);

            if (! $compression->path) {
                throw new RuntimeException('Compression path tidak ditemukan.');
            }

            return [
                'model' => $compression,
                'path' => Storage::disk('public')->path($compression->path),
                'title' => pathinfo($compression->file->name, PATHINFO_FILENAME).'_'.$compression->format,
                'mime_type' => $this->mimeTypeForFormat($compression->format),
                'file_name' => $compression->file->name,
            ];
        }

        throw new RuntimeException('Source type SoundCloud tidak valid.');
    }

    private function mimeTypeForFormat(string $format): string
    {
        return match (strtolower($format)) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',
            default => 'audio/'.$format,
        };
    }
}
