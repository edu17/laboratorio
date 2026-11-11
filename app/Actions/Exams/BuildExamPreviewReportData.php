<?php

namespace App\Actions\Exams;

use App\Models\Exam;
use App\Models\LabProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

class BuildExamPreviewReportData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Exam $exam, User $actor): array
    {
        Gate::forUser($actor)->authorize('view', $exam);
        $exam->load(['sections' => fn (HasMany $query): HasMany => $query->where('is_active', true)->orderBy('sort_order')
            ->with(['parameters' => fn (HasMany $query): HasMany => $query->where('is_active', true)->orderBy('sort_order')])]);
        $profile = LabProfile::query()->where('is_active', true)->firstOrFail();
        $sections = [];
        $notes = [];

        foreach ($exam->sections as $section) {
            $matrix = $section->structure === 'parasitological_matrix';
            $columns = $matrix ? array_values($section->settings['columns'] ?? []) : [];
            if ($matrix && count($columns) < 2) {
                $notes[] = $section->name.': configura al menos dos columnas para previsualizar la matriz.';

                continue;
            }
            $rows = [];
            foreach ($section->parameters as $parameter) {
                if ($matrix) {
                    $rows[] = ['name' => $parameter->name, 'cells' => array_fill(0, count($columns) - 1, 'Ejemplo')];

                    continue;
                }
                $result = match ($parameter->result_type) {
                    'number' => '0.00',
                    'select', 'positive_negative' => $parameter->options[0] ?? 'Sin opciones configuradas',
                    'observation' => 'Observación ficticia de ejemplo',
                    default => 'Dato ficticio',
                };
                $reference = $parameter->reference_text ?? '';
                if ($reference !== '' && $parameter->clinical_validation_status !== 'validated') {
                    $reference .= ' (pendiente de validación)';
                }
                $rows[] = ['name' => $parameter->name, 'result' => $result, 'unit' => $parameter->unit ?? '', 'reference' => $reference];
            }
            $layout = match ($section->settings['report_layout'] ?? null) {
                'paired' => 'paired',
                'standard' => 'standard',
                default => $exam->code === 'HECES' ? 'paired' : 'standard',
            };
            $sections[] = ['name' => $section->name, 'is_matrix' => $matrix, 'layout' => $layout, 'status' => null, 'columns' => $columns, 'rows' => $rows];
        }

        return [
            'is_preview' => true,
            'preview_notes' => $notes,
            'template_version' => 'Vista previa',
            'generated_at' => now()->format('d/m/Y H:i'),
            'institution' => [
                'name' => $profile->institution_name,
                'subtitle' => 'VISTA PREVIA DEL CATÁLOGO — DATOS FICTICIOS',
                'address' => $profile->address,
                'phone' => $profile->phone,
                'email' => $profile->email,
                'sanitary_registration' => $profile->sanitary_registration,
                'emergency_phone' => $profile->emergency_phone,
                'opening_hours' => $profile->opening_hours,
                'logo_data_uri' => null,
                'technical_signature_data_uri' => null,
                'biologist_signature_data_uri' => null,
                'institutional_seal_data_uri' => null,
            ],
            'order' => ['number' => 'VISTA PREVIA', 'referring_doctor' => 'Doctor(a) de ejemplo'],
            'patient' => ['first_names' => 'Paciente ficticio', 'last_names' => 'de ejemplo', 'gender_display' => 'Ejemplo', 'age_display' => 'Ejemplo'],
            'validation' => ['version' => 0, 'validated_at_display' => now()->format('d/m/Y H:i'), 'validator_name' => 'Sin validación clínica'],
            'exams' => [['code' => $exam->code, 'name' => $exam->name, 'sections' => $sections]],
        ];
    }
}
