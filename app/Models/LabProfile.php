<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'version',
    'institution_name',
    'subtitle',
    'address',
    'phone',
    'email',
    'sanitary_registration',
    'emergency_phone',
    'opening_hours',
    'logo_path',
    'technical_signature_path',
    'biologist_signature_path',
    'institutional_seal_path',
    'order_prefix',
    'order_number_padding',
    'is_active',
])]
class LabProfile extends Model
{
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'order_number_padding' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
