<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('Manage:Role');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('Manage:Role');
    }

    public function create(User $user): bool
    {
        return $user->can('Manage:Role');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('Manage:Role');
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
