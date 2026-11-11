<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->can('View:Order');
    }

    /**
     * Exportar a Excel saca datos de pacientes del sistema: es un permiso aparte de ver la lista.
     */
    public function export(User $user): bool
    {
        return $user->can('ViewAny:Order') && $user->can('Export:Order');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:Order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->can('Update:Order')
            && ! in_array($order->status, [Order::STATUS_VALIDATED, Order::STATUS_PUBLISHED, Order::STATUS_CANCELED], true);
    }

    public function validateResults(User $user, Order $order): bool
    {
        return $user->can('Validate:Order')
            && in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_IN_PROGRESS], true);
    }

    public function reopenResults(User $user, Order $order): bool
    {
        return $user->can('Reopen:Order')
            && in_array($order->status, [Order::STATUS_VALIDATED, Order::STATUS_PUBLISHED], true);
    }

    public function generateReport(User $user, Order $order): bool
    {
        return $user->can('Generate:Report')
            && in_array($order->status, [Order::STATUS_VALIDATED, Order::STATUS_PUBLISHED], true)
            && $order->validation_version > 0;
    }

    public function publishReport(User $user, Order $order): bool
    {
        return $user->can('Publish:Report')
            && $order->status === Order::STATUS_VALIDATED
            && $order->validation_version > 0;
    }

    public function revokeReport(User $user, Order $order): bool
    {
        return $user->can('Revoke:Report')
            && $order->status === Order::STATUS_PUBLISHED
            && $order->validation_version > 0;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->can('Cancel:Order')
            && in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_IN_PROGRESS], true);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }
}
