<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Models\File;
use App\Services\FileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function __construct(private readonly FileService $fileService)
    {
    }

    public function create(): View
    {
        return view('files.create');
    }

    public function store(UploadFileRequest $request): RedirectResponse
    {
        $file = $this->fileService->upload($request->file('file'), $request->user());

        return redirect()->route('files.show', $file)->with('status', 'File berhasil diupload.');
    }

    public function show(Request $request, File $file): View
    {
        $this->authorize('view', $file);

        $file->load(['compressions' => fn ($query) => $query->latest()]);

        return view('files.show', compact('file'));
    }

    public function destroy(Request $request, File $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        Storage::disk('public')->delete($file->original_path);

        foreach ($file->compressions as $compression) {
            if ($compression->path) {
                Storage::disk('public')->delete($compression->path);
            }
        }

        $file->delete();

        return redirect()->route('dashboard')->with('status', 'File berhasil dihapus.');
    }
}
