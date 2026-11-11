<?php

namespace App\Actions\Orders;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\ReportAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublishOrderReport
{
    public function handle(Order $order, User $actor): ReportAccessToken
    {
        Gate::forUser($actor)->authorize('publishReport', $order);

        return DB::transaction(function () use ($order, $actor): ReportAccessToken {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            Gate::forUser($actor)->authorize('publishReport', $lockedOrder);

            if ($lockedOrder->reportAccessTokens()->whereNull('revoked_at')->exists()) {
                throw ValidationException::withMessages([
                    'report' => 'La orden ya tiene un enlace público activo.',
                ]);
            }

            $token = Str::random(64);
            $accessToken = $lockedOrder->reportAccessTokens()->create([
                'created_by' => $actor->getKey(),
                'validation_version' => $lockedOrder->validation_version,
                'token_hash' => hash('sha256', $token),
                'token_encrypted' => $token,
            ]);

            $lockedOrder->update(['status' => Order::STATUS_PUBLISHED]);

            AuditLog::create([
                'user_id' => $actor->getKey(),
                'action' => 'report_published',
                'entity_type' => $lockedOrder->getMorphClass(),
                'entity_id' => $lockedOrder->getKey(),
                'metadata' => ['version' => $lockedOrder->validation_version, 'access_id' => $accessToken->getKey()],
            ]);

            return $accessToken;
        }, attempts: 3);
    }
}
