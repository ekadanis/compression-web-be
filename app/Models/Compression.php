<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Compression extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'format',
        'codec',
        'bitrate',
        'resolution',
        'fps',
        'audio_bitrate',
        'sample_rate',
        'channel',
        'size',
        'path',
        'is_recommended',
        'status',
        'progress',
        'estimated_seconds_remaining',
        'error_message',
    ];

    protected $appends = ['url', 'stream_url'];

    protected $casts = [
        'bitrate'       => 'integer',
        'fps'           => 'integer',
        'audio_bitrate' => 'integer',
        'sample_rate'   => 'integer',
        'size'          => 'integer',
        'progress'      => 'integer',
        'estimated_seconds_remaining' => 'integer',
        'is_recommended'=> 'boolean',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function uploads(): MorphMany
    {
        return $this->morphMany(Upload::class, 'uploadable');
    }

    /**
     * Full public URL to the compressed file
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }
        return url('/api/compressions/' . $this->id . '/download');
    }

    public function getStreamUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return url('/api/compressions/' . $this->id . '/stream');
    }
}
