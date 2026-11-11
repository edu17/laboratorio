<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\OrderExam;
use App\Models\User;

class OrderExamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OrderExam $orderExam): bool
    {
        return $user->can('View:Order') && $user->can('View:Result');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OrderExam $orderExam): bool
    {
        return $this->captureResults($user, $orderExam);
    }

    public function viewResults(User $user, OrderExam $orderExam): bool
    {
        return $this->view($user, $orderExam);
    }

    public function captureResults(User $user, OrderExam $orderExam): bool
    {
        return $user->can('Capture:Result')
            && ! in_array($orderExam->status, [OrderExam::STATUS_CANCELED, OrderExam::STATUS_WITHDRAWN, OrderExam::STATUS_VALIDATED], true)
            && ! in_array($orderExam->order?->status, [
                Order::STATUS_CANCELED,
                Order::STATUS_VALIDATED,
                Order::STATUS_PUBLISHED,
            ], true);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OrderExam $orderExam): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, OrderExam $orderExam): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, OrderExam $orderExam): bool
    {
        return false;
    }
}
