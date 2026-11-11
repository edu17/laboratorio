<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OrderExam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelLabOrder
{
    public function handle(Order $order, string $reason, User $actor): Order
    {
        Gate::forUser($actor)->authorize('cancel', $order);

        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'Indica un motivo de anulación de hasta 1000 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($order, $reason, $actor): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (in_array($order->status, [Order::STATUS_CANCELED, Order::STATUS_VALIDATED, Order::STATUS_PUBLISHED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'La orden no puede anularse en su estado actual.',
                ]);
            }

            $order->update([
                'status' => Order::STATUS_CANCELED,
                'cancellation_reason' => $reason,
                'canceled_at' => now(),
                'canceled_by' => $actor->getKey(),
            ]);

            $order->orderExams()
                ->whereIn('status', [OrderExam::STATUS_PENDING, OrderExam::STATUS_IN_PROGRESS])
                ->update(['status' => OrderExam::STATUS_CANCELED]);

            return $order->fresh(['patient', 'creator', 'canceler', 'orderExams.exam']);
        });
    }
}
