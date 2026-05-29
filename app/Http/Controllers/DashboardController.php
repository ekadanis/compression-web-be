<?php

namespace App\Http\Controllers;

use App\Models\Compression;
use App\Models\File;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $files = File::query()
            ->where('user_id', $user->id)
            ->withCount('compressions')
            ->latest()
            ->paginate(12);

        $stats = [
            'total_files' => File::query()->where('user_id', $user->id)->count(),
            'video_files' => File::query()->where('user_id', $user->id)->where('type', 'video')->count(),
            'audio_files' => File::query()->where('user_id', $user->id)->where('type', 'audio')->count(),
            'original_size' => (int) File::query()->where('user_id', $user->id)->sum('size'),
            'compressed_size' => (int) Compression::query()
                ->whereHas('file', fn ($query) => $query->where('user_id', $user->id))
                ->sum(DB::raw('COALESCE(size, 0)')),
        ];

        $stats['total_storage'] = $stats['original_size'] + $stats['compressed_size'];

        return view('dashboard.index', compact('files', 'stats'));
    }
}
