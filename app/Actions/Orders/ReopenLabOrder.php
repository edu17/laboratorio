<?php

namespace App\Actions\Orders;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\OrderValidationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReopenLabOrder
{
    public function handle(Order $order, string $reason, User $actor): Order
    {
        $validated = Validator::make(
            ['reason' => $reason],
            ['reason' => ['required', 'string', 'max:1000']],
        )->validate();
        $reason = trim($validated['reason']);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Escribe el motivo para reabrir la orden.',
            ]);
        }

        Gate::forUser($actor)->authorize('reopenResults', $order);

        return DB::transaction(function () use ($order, $reason, $actor): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            Gate::forUser($actor)->authorize('reopenResults', $lockedOrder);

            $actedAt = now();

            OrderValidationEvent::create([
                'order_id' => $lockedOrder->getKey(),
                'version' => $lockedOrder->validation_version,
                'action' => OrderValidationEvent::ACTION_REOPENED,
                'actor_id' => $actor->getKey(),
                'reason' => $reason,
                'acted_at' => $actedAt,
            ]);

            OrderExam::query()
                ->where('order_id', $lockedOrder->getKey())
                ->where('status', OrderExam::STATUS_VALIDATED)
                ->update([
                    'status' => OrderExam::STATUS_IN_PROGRESS,
                    'updated_at' => $actedAt,
                ]);

            $lockedOrder->reportAccessTokens()
                ->whereNull('revoked_at')
                ->update([
                    'revoked_by' => $actor->getKey(),
                    'revoked_at' => $actedAt,
                    'updated_at' => $actedAt,
                ]);

            $lockedOrder->update([
                'status' => Order::STATUS_IN_PROGRESS,
                'validated_by' => null,
                'validated_at' => null,
            ]);

            AuditLog::create([
                'user_id' => $actor->getKey(),
                'action' => 'order_reopened',
                'entity_type' => $lockedOrder->getMorphClass(),
                'entity_id' => $lockedOrder->getKey(),
                'metadata' => ['version' => $lockedOrder->validation_version],
            ]);

            return $lockedOrder->fresh(['validatedBy', 'orderExams']);
        }, attempts: 3);
    }
}
