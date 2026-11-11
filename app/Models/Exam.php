<?php

namespace App\Models;

use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'name', 'description', 'source', 'clinical_validation_status', 'is_active', 'allows_partial_request', 'sort_order'])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class)->orderBy('sort_order');
    }

    public function catalogVersions(): HasMany
    {
        return $this->hasMany(ExamCatalogVersion::class)->orderByDesc('version_number');
    }

    public function latestCatalogVersion(): HasOne
    {
        return $this->hasOne(ExamCatalogVersion::class)->latestOfMany('version_number');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allows_partial_request' => 'boolean',
        ];
    }
}
