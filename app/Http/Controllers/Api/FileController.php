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
        $files = File::where('user_id', $request->user()->id)
            ->withCount('compressions')
            ->latest()
            ->paginate(15);

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
