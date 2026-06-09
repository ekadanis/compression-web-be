<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadFileRequest;
use App\Models\File;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function __construct(private FileService $fileService) {}

    /**
     * GET /api/files
     * List all files belonging to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:all,audio,video'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:updated_desc,updated_asc,name_asc,name_desc'],
        ]);

        $sort = $validated['sort'] ?? 'updated_desc';

        $query = File::query()
            ->where('user_id', $request->user()->id)
            ->withCount('compressions');

        if (($validated['type'] ?? 'all') !== 'all') {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mime_type', 'like', "%{$search}%");
            });
        }

        match ($sort) {
            'updated_asc' => $query->orderBy('updated_at')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };

        $files = $query->paginate(15)->withQueryString();

        return response()->json($files);
    }

    /**
     * POST /api/files
     * Upload a new file.
     */
    public function store(UploadFileRequest $request): JsonResponse
    {
        $file = $this->fileService->upload($request->file('file'), $request->user());

        return response()->json($file, 201);
    }

    /**
     * GET /api/files/{id}
     * Show a file with all its compressions.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $file = File::where('user_id', $request->user()->id)
            ->with('compressions')
            ->findOrFail($id);

        return response()->json($file);
    }

    /**
     * DELETE /api/files/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $file = File::where('user_id', $request->user()->id)->findOrFail($id);

        // Delete original file
        Storage::delete($file->original_path);

        // Delete compressed files
        foreach ($file->compressions as $compression) {
            if ($compression->path) {
                Storage::delete('public/' . $compression->path);
            }
        }

        $file->delete();

        return response()->json(['message' => 'File deleted successfully.']);
    }
}
