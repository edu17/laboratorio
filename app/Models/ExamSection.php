<?php

namespace App\Models;

use Database\Factories\ExamSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['exam_id', 'code', 'name', 'structure', 'settings', 'source', 'clinical_validation_status', 'is_active', 'sort_order'])]
class ExamSection extends Model
{
    /** @use HasFactory<ExamSectionFactory> */
    use HasFactory;

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ExamParameter::class)->orderBy('sort_order');
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
