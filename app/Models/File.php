<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'mime_type',
        'original_path',
        'size',
        'duration',
        'status',
    ];

    protected $appends = ['url'];

    protected $casts = [
        'size' => 'integer',
        'duration' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function compressions(): HasMany
    {
        return $this->hasMany(Compression::class);
    }

    public function isAudio(): bool
    {
        return $this->type === 'audio';
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->original_path) {
            return null;
        }
        return asset('storage/' . $this->original_path);
    }
}
