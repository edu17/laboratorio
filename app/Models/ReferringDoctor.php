<?php

namespace App\Models;

use Database\Factories\ReferringDoctorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'kind', 'specialty', 'license_number', 'phone', 'notes', 'is_active'])]
class ReferringDoctor extends Model
{
    /** @use HasFactory<ReferringDoctorFactory> */
    use HasFactory;

    public const KIND_DOCTOR = 'medico';

    public const KIND_INSTITUTION = 'institucion';

    /** @var array<string, string> */
    public const KINDS = [
        self::KIND_DOCTOR => 'Médico',
        self::KIND_INSTITUTION => 'Institución o farmacia',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
