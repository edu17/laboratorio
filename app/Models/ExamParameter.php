<?php

namespace App\Models;

use Database\Factories\ExamParameterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_section_id', 'code', 'name', 'result_type', 'unit', 'reference_text', 'reference_config', 'options', 'source', 'clinical_validation_status', 'is_active', 'sort_order'])]
class ExamParameter extends Model
{
    /** @use HasFactory<ExamParameterFactory> */
    use HasFactory;

    public function section(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }

    protected function casts(): array
    {
        return [
            'reference_config' => 'array',
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
