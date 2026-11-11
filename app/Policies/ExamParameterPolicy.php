<?php

namespace App\Policies;

use App\Models\ExamParameter;
use App\Models\User;

class ExamParameterPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ExamParameter');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExamParameter $examParameter): bool
    {
        return $user->can('View:ExamParameter');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:ExamParameter');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExamParameter $examParameter): bool
    {
        return $user->can('Update:ExamParameter');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExamParameter $examParameter): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExamParameter $examParameter): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExamParameter $examParameter): bool
    {
        return false;
    }
}
