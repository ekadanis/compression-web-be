<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'error_message',
    ];

    protected $appends = ['url'];

    protected $casts = [
        'bitrate'       => 'integer',
        'fps'           => 'integer',
        'audio_bitrate' => 'integer',
        'sample_rate'   => 'integer',
        'size'          => 'integer',
        'is_recommended'=> 'boolean',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
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
}
