<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['exam_id', 'version_number', 'snapshot', 'created_by', 'published_at'])]
class ExamCatalogVersion extends Model
{
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Published exam catalog versions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Published exam catalog versions cannot be deleted.');
        });
    }
}
