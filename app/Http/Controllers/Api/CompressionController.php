<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCompressionRequest;
use App\Models\Compression;
use App\Models\File;
use App\Services\CompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompressionController extends Controller
{
    public function __construct(private CompressionService $compressionService) {}

    /**
     * POST /api/compressions
     * Start a new compression job.
     */
    public function store(CreateCompressionRequest $request): JsonResponse
    {
        $file = File::where('user_id', $request->user()->id)
            ->findOrFail($request->integer('file_id'));

        $compression = $this->compressionService->create($request->validated(), $file);

        return response()->json($compression, 201);
    }

    /**
     * GET /api/compressions?file_id=
     * List compressions for a specific file.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['file_id' => ['required', 'exists:files,id']]);

        $file = File::where('user_id', $request->user()->id)
            ->findOrFail($request->integer('file_id'));

        $compressions = $file->compressions()->latest()->get();

        return response()->json($compressions);
    }

    /**
     * GET /api/compressions/{id}
     * Show single compression detail.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $compression = Compression::whereHas('file', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })->findOrFail($id);

        return response()->json($compression);
    }

    /**
     * DELETE /api/compressions/{id}
     * Delete a compression.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $compression = Compression::whereHas('file', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })->findOrFail($id);

        $this->compressionService->delete($compression);

        return response()->json(null, 204);
    }

    /**
     * GET /api/compressions/compare?file_id=&ids[]=1&ids[]=2
     * Compare multiple compressions side-by-side.
     * Note: comparison data is NOT persisted (per brief).
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => ['required', 'exists:files,id'],
            'ids'     => ['required', 'array', 'min:2'],
            'ids.*'   => ['integer'],
        ]);

        $file = File::where('user_id', $request->user()->id)
            ->findOrFail($request->integer('file_id'));

        $compressions = $file->compressions()
            ->whereIn('id', $request->input('ids'))
            ->where('status', 'done')
            ->get()
            ->map(function ($c) use ($file) {
                return [
                    'id'            => $c->id,
                    'format'        => $c->format,
                    'codec'         => $c->codec,
                    'bitrate'       => $c->bitrate,
                    'resolution'    => $c->resolution,
                    'fps'           => $c->fps,
                    'audio_bitrate' => $c->audio_bitrate,
                    'sample_rate'   => $c->sample_rate,
                    'channel'       => $c->channel,
                    'size'          => $c->size,
                    'url'           => $c->url,
                    'is_recommended'=> $c->is_recommended,
                    'size_reduction'=> $file->size > 0
                        ? round((1 - $c->size / $file->size) * 100, 1)
                        : null,
                ];
            });

        return response()->json([
            'original' => [
                'name'     => $file->name,
                'size'     => $file->size,
                'type'     => $file->type,
                'duration' => $file->duration,
            ],
            'compressions' => $compressions,
        ]);
    }
}
