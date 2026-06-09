<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Upload extends Model
{
    protected $fillable = [
        'user_id',
        'uploadable_type',
        'uploadable_id',
        'platform',
        'title',
        'description',
        'tags',
        'category_id',
        'visibility',
        'status',
        'progress',
        'error_message',
        'scheduled_at',
        'started_at',
        'uploaded_at',
        'cancel_requested_at',
        'external_id',
        'url',
        'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'progress' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadable(): MorphTo
    {
        return $this->morphTo();
    }
}
