<?php

namespace App\Actions\Orders;

use App\Models\ReferringDoctor;
use Illuminate\Validation\ValidationException;

class ResolveReferringDoctor
{
    /**
     * Devuelve los dos campos de la orden: el médico del catálogo y una copia de su nombre,
     * que es lo que se imprime y queda congelado al validar.
     *
     * Sin médico del catálogo se conserva el texto libre, como en las órdenes anteriores al catálogo.
     *
     * @return array{referring_doctor_id: int|null, referring_doctor: string|null}
     */
    public function handle(?int $referringDoctorId, ?string $referringDoctorName, ?int $currentReferringDoctorId = null): array
    {
        if ($referringDoctorId === null) {
            return [
                'referring_doctor_id' => null,
                'referring_doctor' => filled($referringDoctorName) ? trim($referringDoctorName) : null,
            ];
        }

        $doctor = ReferringDoctor::query()->find($referringDoctorId);

        if (! $doctor || (! $doctor->is_active && $doctor->getKey() !== $currentReferringDoctorId)) {
            throw ValidationException::withMessages([
                'referring_doctor_id' => 'El médico remitente no existe o está inactivo.',
            ]);
        }

        return [
            'referring_doctor_id' => $doctor->getKey(),
            'referring_doctor' => $doctor->name,
        ];
    }
}
