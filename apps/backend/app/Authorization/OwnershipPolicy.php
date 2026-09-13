<?php

namespace App\Authorization;

use App\Models\User;

class OwnershipPolicy
{
    public function owns(User $user, string $ownerId): bool
    {
        return hash_equals($user->id, $ownerId);
    }
}
