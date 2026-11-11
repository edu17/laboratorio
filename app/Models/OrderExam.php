<?php

namespace App\Models;

use Database\Factories\OrderExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_id', 'exam_id', 'exam_catalog_version_id', 'catalog_snapshot', 'section_statuses', 'status'])]
class OrderExam extends Model
{
    /** @use HasFactory<OrderExamFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_IN_PROGRESS = 'en_proceso';

    public const STATUS_VALIDATED = 'validada';

    public const STATUS_CANCELED = 'anulada';

    public const STATUS_WITHDRAWN = 'retirado';

    public const SECTION_STATUS_PENDING = 'pending';

    public const SECTION_STATUS_COMPLETED_WITH_FINDINGS = 'completed_with_findings';

    public const SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS = 'completed_without_findings';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function catalogVersion(): BelongsTo
    {
        return $this->belongsTo(ExamCatalogVersion::class, 'exam_catalog_version_id');
    }

    public function resultValues(): HasMany
    {
        return $this->hasMany(ResultValue::class);
    }

    protected function casts(): array
    {
        return [
            'catalog_snapshot' => 'array',
            'section_statuses' => 'array',
        ];
    }
}
