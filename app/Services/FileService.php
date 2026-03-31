<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FileService
{
    /**
     * Upload a file and persist to DB.
     */
    public function upload(UploadedFile $uploadedFile, User $user): File
    {
        $uuid      = Str::uuid();
        $extension = $uploadedFile->getClientOriginalExtension();
        $mimeType  = $uploadedFile->getMimeType();
        $type      = $this->resolveType($mimeType, $extension);
        $filename  = $uuid . '.' . $extension;
        $storagePath = "original/{$user->id}/{$filename}";

        // Store to storage/app/public/original/{user_id}/
        $uploadedFile->storeAs("original/{$user->id}", $filename, 'public');

        // Try to get duration via ffprobe
        $duration = $this->getDuration(\Illuminate\Support\Facades\Storage::disk('public')->path($storagePath));

        return File::create([
            'user_id'       => $user->id,
            'name'          => $uploadedFile->getClientOriginalName(),
            'type'          => $type,
            'mime_type'     => $mimeType,
            'original_path' => $storagePath,
            'size'          => $uploadedFile->getSize(),
            'duration'      => $duration,
            'status'        => 'uploaded',
        ]);
    }

    private function resolveType(string $mimeType, string $extension): string
    {
        $audioMimes = ['audio/mpeg', 'audio/wav', 'audio/aac', 'audio/ogg', 'audio/mp4'];
        $audioExts  = ['mp3', 'wav', 'aac', 'ogg', 'm4a'];

        if (in_array($mimeType, $audioMimes) || in_array(strtolower($extension), $audioExts)) {
            return 'audio';
        }

        return 'video';
    }

    private function getDuration(string $filePath): ?int
    {
        if (! file_exists($filePath)) {
            return null;
        }

        $cmd    = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($filePath) . " 2>&1";
        $output = shell_exec($cmd);

        if ($output && is_numeric(trim($output))) {
            return (int) round((float) trim($output));
        }

        return null;
    }
}
