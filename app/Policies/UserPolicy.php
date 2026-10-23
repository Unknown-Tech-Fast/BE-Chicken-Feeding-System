<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasRole('admin')
            || $user->is($target);
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasRole('admin')
            || $user->is($target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasRole('admin')
            && !$user->is($target);
    }
}
