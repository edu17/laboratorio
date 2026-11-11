<?php

namespace App\Actions\Orders;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RevokePublishedReport
{
    public function handle(Order $order, User $actor): Order
    {
        $order = $order->fresh();
        Gate::forUser($actor)->authorize('revokeReport', $order);

        return DB::transaction(function () use ($order, $actor): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            Gate::forUser($actor)->authorize('revokeReport', $lockedOrder);
            $accessToken = $lockedOrder->reportAccessTokens()
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if (! $accessToken) {
                throw ValidationException::withMessages([
                    'report' => 'No hay un enlace público activo para revocar.',
                ]);
            }

            $accessToken->update([
                'revoked_by' => $actor->getKey(),
                'revoked_at' => now(),
            ]);
            $lockedOrder->update(['status' => Order::STATUS_VALIDATED]);

            AuditLog::create([
                'user_id' => $actor->getKey(),
                'action' => 'report_access_revoked',
                'entity_type' => $lockedOrder->getMorphClass(),
                'entity_id' => $lockedOrder->getKey(),
                'metadata' => ['version' => $accessToken->validation_version, 'access_id' => $accessToken->getKey()],
            ]);

            return $lockedOrder->fresh();
        }, attempts: 3);
    }
}
