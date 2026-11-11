<?php

namespace App\Actions\Orders;

use App\Models\Exam;
use App\Models\OrderExam;
use Illuminate\Validation\ValidationException;

class ResolveRequestedParameterCodes
{
    /**
     * Valida los análisis solicitados de un examen y devuelve null cuando se pidió el examen completo.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>|null  $codes
     * @return list<string>|null
     */
    public function handle(Exam $exam, array $snapshot, ?array $codes): ?array
    {
        if ($codes === null) {
            return null;
        }

        $requestable = array_keys(OrderExam::requestableParameters($snapshot));
        $codes = array_values(array_unique(array_filter($codes, fn (mixed $code): bool => is_string($code) && $code !== '')));
        $errorKey = "requested_parameter_codes.{$exam->getKey()}";

        if (array_diff($codes, $requestable) !== []) {
            throw ValidationException::withMessages([
                $errorKey => "La selección de {$exam->name} contiene análisis que no pertenecen al examen.",
            ]);
        }

        $isComplete = array_diff($requestable, $codes) === [];

        if ($isComplete) {
            return null;
        }

        if (! $exam->allows_partial_request) {
            throw ValidationException::withMessages([
                $errorKey => "{$exam->name} se informa completo y no permite solicitar análisis por separado.",
            ]);
        }

        if ($codes === []) {
            throw ValidationException::withMessages([
                $errorKey => "Selecciona al menos un análisis de {$exam->name}.",
            ]);
        }

        return array_values(array_intersect($requestable, $codes));
    }
}
