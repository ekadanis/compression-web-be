<?php

namespace App\Services;

use App\Models\Compression;
use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UploadSourceResolver
{
    /**
     * @return array{model: Model, path: string, title: string, mime_type: string}
     */
    public function resolve(User $user, string $sourceType, int $sourceId): array
    {
        if ($sourceType === 'file') {
            $file = File::query()
                ->where('user_id', $user->id)
                ->where('type', 'video')
                ->findOrFail($sourceId);

            return [
                'model' => $file,
                'path' => Storage::disk('public')->path($file->original_path),
                'title' => pathinfo($file->name, PATHINFO_FILENAME),
                'mime_type' => $file->mime_type,
            ];
        }

        if ($sourceType === 'compression') {
            $compression = Compression::query()
                ->where('status', 'done')
                ->whereHas('file', function ($query) use ($user) {
                    $query->where('user_id', $user->id)->where('type', 'video');
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
                'mime_type' => 'video/'.$compression->format,
            ];
        }

        throw new RuntimeException('Source type tidak valid.');
    }
}
