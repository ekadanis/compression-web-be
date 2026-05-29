<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoutubeAccount extends Model
{
    protected $fillable = [
        'user_id',
        'google_email',
        'channel_id',
        'channel_title',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'revoked_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'scopes' => 'array',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
