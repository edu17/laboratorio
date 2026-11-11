<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['result_value_id', 'previous_value', 'changed_by', 'changed_at'])]
class ResultValueRevision extends Model
{
    public function resultValue(): BelongsTo
    {
        return $this->belongsTo(ResultValue::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'previous_value' => 'array',
            'changed_at' => 'datetime',
        ];
    }
}
