<?php

namespace App\Models;

use Database\Factories\ResultValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_exam_id', 'parameter_code', 'value', 'edited_by'])]
class ResultValue extends Model
{
    /** @use HasFactory<ResultValueFactory> */
    use HasFactory;

    public function orderExam(): BelongsTo
    {
        return $this->belongsTo(OrderExam::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ResultValueRevision::class);
    }

    /**
     * Un guardado sin datos crea filas vacías ({"value": null} o celdas vacías); solo cuenta lo que se escribió.
     */
    public function hasCapturedValue(): bool
    {
        $value = is_array($this->value) ? $this->value : [];

        return filled($value['value'] ?? null)
            || collect($value['cells'] ?? [])->contains(fn (mixed $cell): bool => filled($cell));
    }

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
