<?php

namespace App\Policies;

use App\Models\ReferringDoctor;
use App\Models\User;

class ReferringDoctorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ReferringDoctor');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReferringDoctor $referringDoctor): bool
    {
        return $user->can('View:ReferringDoctor');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:ReferringDoctor');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReferringDoctor $referringDoctor): bool
    {
        return $user->can('Update:ReferringDoctor');
    }

    /**
     * Los médicos remitentes no se eliminan: se desactivan para conservar el historial de órdenes.
     */
    public function delete(User $user, ReferringDoctor $referringDoctor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReferringDoctor $referringDoctor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReferringDoctor $referringDoctor): bool
    {
        return false;
    }
}
