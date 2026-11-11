<?php

namespace App\Models;

use Database\Factories\OrderValidationEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'version', 'action', 'actor_id', 'reason', 'results_snapshot', 'acted_at'])]
class OrderValidationEvent extends Model
{
    public const ACTION_VALIDATED = 'validated';

    public const ACTION_REOPENED = 'reopened';

    /** @use HasFactory<OrderValidationEventFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'results_snapshot' => 'array',
            'acted_at' => 'datetime',
        ];
    }
}
