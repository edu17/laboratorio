<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'patient_id', 'ordered_at', 'status', 'created_by', 'notes', 'referring_doctor', 'referring_doctor_id', 'cancellation_reason', 'canceled_at', 'canceled_by', 'validation_version', 'validated_by', 'validated_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_IN_PROGRESS = 'en_proceso';

    public const STATUS_VALIDATED = 'validada';

    public const STATUS_PUBLISHED = 'publicada';

    public const STATUS_CANCELED = 'anulada';

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function referringDoctor(): BelongsTo
    {
        return $this->belongsTo(ReferringDoctor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function validationEvents(): HasMany
    {
        return $this->hasMany(OrderValidationEvent::class)->orderBy('acted_at');
    }

    public function reportAccessTokens(): HasMany
    {
        return $this->hasMany(ReportAccessToken::class);
    }

    public function orderExams(): HasMany
    {
        return $this->hasMany(OrderExam::class);
    }

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'canceled_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }
}
