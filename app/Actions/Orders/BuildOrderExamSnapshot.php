<?php

namespace App\Actions\Orders;

use App\Actions\Exams\BuildExamSnapshot;
use App\Models\Exam;
use Illuminate\Validation\ValidationException;

class BuildOrderExamSnapshot
{
    public function __construct(private BuildExamSnapshot $buildSnapshot) {}

    /**
     * Estructura del examen que se congela en la orden: la última versión publicada o, si no hay, el catálogo vigente.
     *
     * @return array<string, mixed>
     */
    public function handle(Exam $exam): array
    {
        if (! $exam->is_active) {
            throw ValidationException::withMessages([
                'exam' => 'No se puede solicitar un examen inactivo.',
            ]);
        }

        return $exam->latestCatalogVersion?->snapshot
            ?? $this->buildSnapshot->handle($exam);
    }
}
