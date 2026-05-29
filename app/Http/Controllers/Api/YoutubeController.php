<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateYoutubeUploadRequest;
use App\Jobs\UploadToYoutubeJob;
use App\Models\Compression;
use App\Models\File;
use App\Models\Upload;
use App\Services\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class YoutubeController extends Controller
{
    public function __construct(private readonly GoogleOAuthService $googleOAuthService)
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
        abort_unless(in_array($upload->status, ['scheduled', 'pending'], true), 422);

        $upload->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Upload dibatalkan.',
        ]);
    }
}
