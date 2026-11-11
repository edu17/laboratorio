<?php

namespace App\Policies;

use App\Models\ExamSection;
use App\Models\User;

class ExamSectionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ExamSection');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExamSection $examSection): bool
    {
        return $user->can('View:ExamSection');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:ExamSection');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExamSection $examSection): bool
    {
        return $user->can('Update:ExamSection');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExamSection $examSection): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExamSection $examSection): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExamSection $examSection): bool
    {
        return false;
    }
}
