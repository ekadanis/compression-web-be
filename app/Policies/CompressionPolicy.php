<?php

namespace App\Policies;

use App\Models\Compression;
use App\Models\User;

class CompressionPolicy
{
    public function view(User $user, Compression $compression): bool
    {
        return $compression->file?->user_id === $user->id;
    }

    public function delete(User $user, Compression $compression): bool
    {
        return $compression->file?->user_id === $user->id;
    }
}
