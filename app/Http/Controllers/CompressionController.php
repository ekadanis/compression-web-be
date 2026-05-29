<?php

namespace App\Http\Controllers;

use App\Models\Compression;
use App\Models\File;
use App\Services\CompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompressionController extends Controller
{
    public function __construct(private readonly CompressionService $compressionService)
    {
    }

    public function create(Request $request, File $file): View
    {
        $this->authorize('createCompression', $file);

        return view('compressions.create', compact('file'));
    }

    public function store(Request $request, File $file): RedirectResponse
    {
        $this->authorize('createCompression', $file);

        $validated = $request->validate([
            'media_type' => ['required', 'in:video,audio'],
            'format' => ['required', 'string', 'in:mp4,mkv,avi,mov,mp3,wav,aac,ogg'],
            'codec' => ['nullable', 'string'],
            'bitrate' => ['nullable', 'integer', 'min:100'],
            'resolution' => ['nullable', 'string', 'regex:/^\d+:\d+$/'],
            'fps' => ['nullable', 'integer', 'min:1', 'max:120'],
            'audio_bitrate' => ['nullable', 'integer', 'min:32'],
            'sample_rate' => ['nullable', 'integer', 'in:22050,44100,48000'],
            'channel' => ['nullable', 'string', 'in:mono,stereo'],
            'is_recommended' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'format' => $validated['format'],
            'codec' => $validated['codec'] ?? null,
            'bitrate' => $validated['media_type'] === 'video' ? ($validated['bitrate'] ?? null) : null,
            'resolution' => $validated['media_type'] === 'video' ? ($validated['resolution'] ?? null) : null,
            'fps' => $validated['media_type'] === 'video' ? ($validated['fps'] ?? null) : null,
            'audio_bitrate' => $validated['audio_bitrate'] ?? null,
            'sample_rate' => $validated['media_type'] === 'audio' ? ($validated['sample_rate'] ?? null) : null,
            'channel' => $validated['media_type'] === 'audio' ? ($validated['channel'] ?? null) : null,
            'is_recommended' => $request->boolean('is_recommended'),
        ];

        $this->compressionService->create($payload, $file);

        return redirect()->route('files.show', $file)->with('status', 'Compression job berhasil dimulai.');
    }

    public function compare(Request $request, File $file): View
    {
        $this->authorize('view', $file);

        $ids = collect(explode(',', (string) $request->string('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        abort_if($ids->count() < 2, 422, 'Pilih minimal dua compression untuk compare.');

        $compressions = $file->compressions()
            ->whereIn('id', $ids)
            ->where('status', 'done')
            ->get();

        abort_if($compressions->count() < 2, 422, 'Compression yang dipilih belum cukup atau belum selesai.');

        $originalPath = Storage::disk('public')->path($file->original_path);

        return view('compressions.compare', [
            'file' => $file,
            'compressions' => $compressions,
            'originalMetadata' => $this->getOriginalFileMetadata($originalPath, $file->type),
        ]);
    }

    public function destroy(Request $request, Compression $compression): RedirectResponse
    {
        $compression->load('file');
        $this->authorize('delete', $compression);

        $file = $compression->file;
        $this->compressionService->delete($compression);

        return redirect()->route('files.show', $file)->with('status', 'Compression berhasil dihapus.');
    }

    private function getOriginalFileMetadata(string $path, string $type): array
    {
        $data = [
            'codec' => null,
            'bitrate' => null,
            'resolution' => null,
            'audio_bitrate' => null,
            'channel' => null,
        ];

        if (! file_exists($path)) {
            return $data;
        }

        $output = shell_exec('ffprobe -v error -print_format json -show_format -show_streams '.escapeshellarg($path));
        $info = json_decode($output ?? '', true);

        if (! is_array($info)) {
            return $data;
        }

        $format = $info['format'] ?? [];
        $streams = $info['streams'] ?? [];
        $videoStream = null;
        $audioStream = null;

        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? null) === 'video' && ! $videoStream) {
                $videoStream = $stream;
            }

            if (($stream['codec_type'] ?? null) === 'audio' && ! $audioStream) {
                $audioStream = $stream;
            }
        }

        if ($type === 'video' && $videoStream) {
            $data['codec'] = $videoStream['codec_name'] ?? null;
            if (isset($videoStream['width'], $videoStream['height'])) {
                $data['resolution'] = $videoStream['width'].':'.$videoStream['height'];
            }
        }

        if ($type === 'audio' && $audioStream) {
            $data['codec'] = $audioStream['codec_name'] ?? null;
        }

        if (isset($format['bit_rate'])) {
            $data['bitrate'] = (int) round($format['bit_rate'] / 1000);
        }

        if ($audioStream) {
            if (isset($audioStream['bit_rate'])) {
                $data['audio_bitrate'] = (int) round($audioStream['bit_rate'] / 1000);
            }

            if (isset($audioStream['channels'])) {
                $data['channel'] = $audioStream['channels'] === 2 ? 'stereo' : ($audioStream['channels'] === 1 ? 'mono' : (string) $audioStream['channels']);
            }
        }

        return $data;
    }
}
