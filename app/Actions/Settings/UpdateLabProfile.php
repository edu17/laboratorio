<?php

namespace App\Actions\Settings;

use App\Models\AuditLog;
use App\Models\LabProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateLabProfile
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, User $actor): LabProfile
    {
        Gate::forUser($actor)->authorize('Manage:LabSettings');

        $data = Validator::make($attributes, [
            'institution_name' => ['required', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'sanitary_registration' => ['nullable', 'string', 'max:100'],
            'emergency_phone' => ['nullable', 'string', 'max:40'],
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255', 'regex:/\Alab-profile-logos\/[A-Za-z0-9._-]+\.(?:png|jpe?g)\z/i'],
            'technical_signature_path' => ['nullable', 'string', 'max:255', 'regex:/\Alab-report-assets\/[A-Za-z0-9._-]+\.(?:png|jpe?g)\z/i'],
            'biologist_signature_path' => ['nullable', 'string', 'max:255', 'regex:/\Alab-report-assets\/[A-Za-z0-9._-]+\.(?:png|jpe?g)\z/i'],
            'institutional_seal_path' => ['nullable', 'string', 'max:255', 'regex:/\Alab-report-assets\/[A-Za-z0-9._-]+\.(?:png|jpe?g)\z/i'],
            'order_prefix' => ['required', 'string', 'max:12', 'regex:/\A[A-Z][A-Z0-9-]{0,11}\z/i'],
            'order_number_padding' => ['required', 'integer', 'between:3,10'],
        ])->validate();

        $data['institution_name'] = trim($data['institution_name']);
        $data['order_prefix'] = strtoupper($data['order_prefix']);
        $data['subtitle'] = filled($data['subtitle'] ?? null) ? trim($data['subtitle']) : null;
        $data['address'] = filled($data['address'] ?? null) ? trim($data['address']) : null;
        $data['phone'] = filled($data['phone'] ?? null) ? trim($data['phone']) : null;
        $data['email'] = filled($data['email'] ?? null) ? trim($data['email']) : null;
        foreach (['sanitary_registration', 'emergency_phone', 'opening_hours'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }

        $assetPaths = [
            'logo_path' => 'El logotipo seleccionado',
            'technical_signature_path' => 'La firma del técnico laboratorista',
            'biologist_signature_path' => 'La firma de la química bióloga',
            'institutional_seal_path' => 'El sello institucional',
        ];
        $disk = Storage::disk('local');

        foreach ($assetPaths as $field => $label) {
            $path = $data[$field] ?? null;

            if (! filled($path)) {
                continue;
            }

            $isAvailableImage = $disk->exists($path)
                && in_array($disk->mimeType($path), ['image/jpeg', 'image/png'], true)
                && $disk->size($path) <= 2 * 1024 * 1024;

            if (! $isAvailableImage) {
                throw ValidationException::withMessages([
                    $field => $label.' no está disponible o no es PNG/JPEG de hasta 2 MB. Vuelve a cargarlo.',
                ]);
            }
        }

        return DB::transaction(function () use ($data, $actor): LabProfile {
            $currentProfile = LabProfile::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $currentProfile->update(['is_active' => false]);
            $profile = LabProfile::query()->create([
                ...$data,
                'version' => $currentProfile->version + 1,
                'is_active' => true,
            ]);

            AuditLog::query()->create([
                'user_id' => $actor->getKey(),
                'action' => 'lab_profile_version_created',
                'entity_type' => $profile->getMorphClass(),
                'entity_id' => $profile->getKey(),
                'metadata' => ['version' => $profile->version],
            ]);

            return $profile;
        }, attempts: 3);
    }
}
