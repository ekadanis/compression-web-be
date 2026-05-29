<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSoundCloudUploadRequest;
use App\Jobs\UploadToSoundCloudJob;
use App\Models\Compression;
use App\Models\File;
use App\Models\Upload;
use App\Services\SoundCloudOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SoundCloudController extends Controller
{
    public function __construct(private readonly SoundCloudOAuthService $soundCloudOAuthService)
    {
    }

    public function account(Request $request): JsonResponse
    {
        $user = $request->user()->load('soundcloudAccount');

        return response()->json([
            'connected' => $user->soundcloudAccount !== null,
            'account' => $user->soundcloudAccount,
        ]);
    }

    public function authRedirect(Request $request): JsonResponse
    {
        return response()->json([
            'url' => $this->soundCloudOAuthService->getAuthUrl($request->user()),
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $this->soundCloudOAuthService->disconnect($request->user());

        return response()->json([
            'message' => 'Koneksi SoundCloud dilepas.',
        ]);
    }

    public function sources(Request $request): JsonResponse
    {
        $user = $request->user();

        $files = File::query()
            ->where('user_id', $user->id)
            ->where('type', 'audio')
            ->latest()
            ->get()
            ->map(fn (File $file) => [
                'source_type' => 'file',
                'source_id' => $file->id,
                'label' => $file->name,
                'file_name' => $file->name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'created_at' => $file->created_at,
            ]);

        $compressions = Compression::query()
            ->where('status', 'done')
            ->whereHas('file', fn ($query) => $query->where('user_id', $user->id)->where('type', 'audio'))
            ->with('file')
            ->latest()
            ->get()
            ->map(fn (Compression $compression) => [
                'source_type' => 'compression',
                'source_id' => $compression->id,
                'label' => $compression->file->name.' ('.$compression->format.')',
                'file_name' => $compression->file->name,
                'mime_type' => $this->mimeTypeForFormat($compression->format),
                'size' => $compression->size,
                'created_at' => $compression->created_at,
            ]);

        return response()->json([
            'sources' => $files->concat($compressions)->sortByDesc('created_at')->values(),
        ]);
    }

    public function uploads(Request $request): JsonResponse
    {
        $uploads = Upload::query()
            ->where('user_id', $request->user()->id)
            ->where('platform', 'soundcloud')
            ->latest()
            ->get();

        return response()->json($uploads);
    }

    public function store(CreateSoundCloudUploadRequest $request): JsonResponse
    {
        abort_unless($request->user()->soundcloudAccount, 422, 'Hubungkan akun SoundCloud dulu sebelum membuat upload.');

        $validated = $request->validated();
        $tags = is_array($validated['tags'] ?? null)
            ? collect($validated['tags'])->map(fn ($tag) => trim((string) $tag))->filter()->values()->all()
            : collect(explode(',', (string) ($validated['tags'] ?? '')))->map(fn ($tag) => trim($tag))->filter()->values()->all();

        $model = $validated['source_type'] === 'file'
            ? File::query()->where('user_id', $request->user()->id)->where('type', 'audio')->findOrFail($validated['source_id'])
            : Compression::query()
                ->where('status', 'done')
                ->whereHas('file', fn ($query) => $query->where('user_id', $request->user()->id)->where('type', 'audio'))
                ->findOrFail($validated['source_id']);

        $upload = new Upload([
            'user_id' => $request->user()->id,
            'platform' => 'soundcloud',
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'tags' => $tags,
            'category_id' => null,
            'visibility' => $validated['sharing'],
            'status' => $validated['schedule_mode'] === 'scheduled' ? 'scheduled' : 'pending',
            'progress' => 0,
            'scheduled_at' => $validated['schedule_mode'] === 'scheduled' && ! empty($validated['scheduled_at'])
                ? Carbon::parse($validated['scheduled_at'])
                : null,
            'metadata' => [
                'genre' => $validated['genre'] ?? null,
            ],
        ]);

        $upload->user()->associate($request->user());
        $upload->uploadable()->associate($model);
        $upload->save();

        if ($upload->status === 'pending') {
            UploadToSoundCloudJob::dispatch($upload->id);
        }

        return response()->json($upload->fresh(), 201);
    }

    public function show(Request $request, Upload $upload): JsonResponse
    {
        abort_unless($upload->user_id === $request->user()->id && $upload->platform === 'soundcloud', 403);

        return response()->json($upload->fresh());
    }

    public function destroy(Request $request, Upload $upload): JsonResponse
    {
        abort_unless($upload->user_id === $request->user()->id && $upload->platform === 'soundcloud', 403);
        abort_unless(in_array($upload->status, ['scheduled', 'pending'], true), 422);

        $upload->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Upload SoundCloud dibatalkan.',
        ]);
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
