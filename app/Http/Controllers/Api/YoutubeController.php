<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateYoutubeUploadRequest;
use App\Jobs\UploadToYoutubeJob;
use App\Models\Compression;
use App\Models\File;
use App\Models\Upload;
use App\Services\GoogleOAuthService;
use App\Services\YoutubeUploadService;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class YoutubeController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuthService,
        private readonly YoutubeUploadService $youtubeUploadService,
    )
    {
    }

    public function account(Request $request): JsonResponse
    {
        $user = $request->user()->load('youtubeAccount');

        return response()->json([
            'connected' => $user->youtubeAccount !== null,
            'account' => $user->youtubeAccount,
        ]);
    }

    public function authRedirect(): JsonResponse
    {
        return response()->json([
            'url' => $this->googleOAuthService->getAuthUrl(request()->user()),
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $this->googleOAuthService->disconnect($request->user());

        return response()->json([
            'message' => 'Koneksi YouTube dilepas.',
        ]);
    }

    public function sources(Request $request): JsonResponse
    {
        $user = $request->user();

        $files = File::query()
            ->where('user_id', $user->id)
            ->where('type', 'video')
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
            ->whereHas('file', fn ($query) => $query->where('user_id', $user->id)->where('type', 'video'))
            ->with('file')
            ->latest()
            ->get()
            ->map(fn (Compression $compression) => [
                'source_type' => 'compression',
                'source_id' => $compression->id,
                'label' => $compression->file->name.' ('.$compression->format.')',
                'file_name' => $compression->file->name,
                'mime_type' => 'video/'.$compression->format,
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
            ->where('platform', 'youtube')
            ->latest()
            ->get();

        return response()->json($uploads);
    }

    public function store(CreateYoutubeUploadRequest $request): JsonResponse
    {
        abort_unless($request->user()->youtubeAccount, 422, 'Hubungkan akun YouTube dulu sebelum membuat upload.');

        $validated = $request->validated();

        $tags = is_array($validated['tags'] ?? null)
            ? collect($validated['tags'])->map(fn ($tag) => trim((string) $tag))->filter()->values()->all()
            : collect(explode(',', (string) ($validated['tags'] ?? '')))->map(fn ($tag) => trim($tag))->filter()->values()->all();

        $model = $validated['source_type'] === 'file'
            ? File::query()->where('user_id', $request->user()->id)->where('type', 'video')->findOrFail($validated['source_id'])
            : Compression::query()
                ->where('status', 'done')
                ->whereHas('file', fn ($query) => $query->where('user_id', $request->user()->id)->where('type', 'video'))
                ->findOrFail($validated['source_id']);

        $upload = new Upload([
            'user_id' => $request->user()->id,
            'platform' => 'youtube',
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'tags' => $tags,
            'category_id' => $validated['category_id'] ?? '22',
            'visibility' => $validated['visibility'],
            'status' => $validated['schedule_mode'] === 'scheduled' ? 'scheduled' : 'pending',
            'progress' => 0,
            'scheduled_at' => $validated['schedule_mode'] === 'scheduled' && ! empty($validated['scheduled_at'])
                ? Carbon::parse($validated['scheduled_at'])
                : null,
        ]);

        $upload->user()->associate($request->user());
        $upload->uploadable()->associate($model);
        $upload->save();

        if ($upload->status === 'pending') {
            UploadToYoutubeJob::dispatch($upload->id);
        }

        return response()->json($upload->fresh(), 201);
    }

    public function show(Request $request, Upload $upload): JsonResponse
    {
        abort_unless($upload->user_id === $request->user()->id, 403);

        return response()->json($upload->fresh());
    }

    public function destroy(Request $request, Upload $upload): JsonResponse
    {
        abort_unless($upload->user_id === $request->user()->id, 403);

        abort_unless(in_array($upload->status, ['scheduled', 'pending', 'processing', 'uploaded', 'failed', 'cancelled'], true), 422);

        $wasUploaded = $upload->status === 'uploaded';

        if ($wasUploaded && $upload->external_id) {
            try {
                $this->youtubeUploadService->deleteVideo($upload);
            } catch (GoogleServiceException $exception) {
                if ($exception->getCode() === 403 && str_contains($exception->getMessage(), 'ACCESS_TOKEN_SCOPE_INSUFFICIENT')) {
                    abort(422, 'Akun YouTube perlu dihubungkan ulang untuk memberi izin hapus video. Disconnect lalu Connect YouTube lagi.');
                }

                if ($exception->getCode() === 404 && str_contains($exception->getMessage(), 'videoNotFound')) {
                    abort(422, 'Video tidak ditemukan oleh akun YouTube yang sedang terhubung. Pastikan akun/channel yang connect sama dengan akun/channel yang mengupload video ini.');
                }

                throw $exception;
            }
        }

        $upload->update([
            'status' => 'cancelled',
            'cancel_requested_at' => $upload->cancel_requested_at ?: now(),
        ]);

        return response()->json([
            'message' => $wasUploaded ? 'Video YouTube dihapus.' : 'Upload dibatalkan.',
        ]);
    }
}
