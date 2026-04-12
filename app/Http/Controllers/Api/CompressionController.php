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
     * GET /api/compressions/{id}/download
     * Force download the file.
     */
    public function download(int $id)
    {
        $compression = Compression::findOrFail($id);
        $path = \Illuminate\Support\Facades\Storage::disk('public')->path($compression->path);

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        $filename = 'compressed_' . $compression->file_id . '_' . $compression->id . '.' . $compression->format;

        return response()->streamDownload(function () use ($path) {
            $stream = fopen($path, 'r');
            fpassthru($stream);
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Access-Control-Allow-Origin' => '*',
        ]);
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

        $originalPath = \Illuminate\Support\Facades\Storage::disk('public')->path($file->original_path);
        $metadata     = $this->getOriginalFileMetadata($originalPath, $file->type);

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
            'original' => array_merge([
                'name'     => $file->name,
                'size'     => $file->size,
                'type'     => $file->type,
                'duration' => $file->duration,
            ], $metadata),
            'compressions' => $compressions,
        ]);
    }

    private function getOriginalFileMetadata(string $path, string $type): array
    {
        $data = [
            'codec'         => null,
            'bitrate'       => null,
            'resolution'    => null,
            'audio_bitrate' => null,
            'channel'       => null,
        ];

        if (! file_exists($path)) {
            return $data;
        }

        $cmd = 'ffprobe -v error -print_format json -show_format -show_streams ' . escapeshellarg($path);
        $output = shell_exec($cmd);
        $info = json_decode($output, true);

        if (!$info) {
            return $data;
        }

        $format  = $info['format'] ?? [];
        $streams = $info['streams'] ?? [];

        $videoStream = null;
        $audioStream = null;

        foreach ($streams as $s) {
            if (isset($s['codec_type'])) {
                if ($s['codec_type'] === 'video' && !$videoStream) {
                    $videoStream = $s;
                } elseif ($s['codec_type'] === 'audio' && !$audioStream) {
                    $audioStream = $s;
                }
            }
        }

        if ($type === 'video') {
            if ($videoStream) {
                $data['codec'] = $videoStream['codec_name'] ?? null;
                if (isset($videoStream['width']) && isset($videoStream['height'])) {
                    $data['resolution'] = $videoStream['width'] . ':' . $videoStream['height'];
                }
            }
        } else {
            if ($audioStream) {
                $data['codec'] = $audioStream['codec_name'] ?? null;
            }
        }

        if (isset($format['bit_rate'])) {
            $data['bitrate'] = round($format['bit_rate'] / 1000);
        } elseif (isset($videoStream['bit_rate'])) {
            $data['bitrate'] = round($videoStream['bit_rate'] / 1000);
        }

        if ($audioStream) {
            if (isset($audioStream['bit_rate'])) {
                $data['audio_bitrate'] = round($audioStream['bit_rate'] / 1000);
            }
            if (isset($audioStream['channels'])) {
                $data['channel'] = $audioStream['channels'] == 2 ? 'stereo' : ($audioStream['channels'] == 1 ? 'mono' : (string)$audioStream['channels']);
            }
        }

        return $data;
    }
}
