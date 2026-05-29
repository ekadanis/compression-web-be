<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoundCloudAccount extends Model
{
    protected $fillable = [
        'user_id',
        'soundcloud_user_id',
        'username',
        'permalink_url',
        'avatar_url',
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
