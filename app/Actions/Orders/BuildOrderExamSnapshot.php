<?php

namespace App\Actions\Orders;

use App\Actions\Exams\BuildExamResultFormDefinition;
use App\Models\Exam;

class BuildOrderExamSnapshot
{
    public function __construct(private BuildExamResultFormDefinition $formDefinition) {}

    /**
     * Estructura del examen que se congela en la orden: la última versión publicada o, si no hay, el catálogo vigente.
     *
     * @return array<string, mixed>
     */
    public function handle(Exam $exam): array
    {
        return $exam->latestCatalogVersion?->snapshot
            ?? $this->formDefinition->handle($exam);
    }
}
