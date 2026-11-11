<?php

namespace App\Models;

use Database\Factories\OrderExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_id', 'exam_id', 'exam_catalog_version_id', 'catalog_snapshot', 'requested_parameter_codes', 'section_statuses', 'status'])]
class OrderExam extends Model
{
    /** @use HasFactory<OrderExamFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_IN_PROGRESS = 'en_proceso';

    public const STATUS_VALIDATED = 'validada';

    public const STATUS_CANCELED = 'anulada';

    public const STATUS_WITHDRAWN = 'retirado';

    public const SECTION_STATUS_PENDING = 'pending';

    public const SECTION_STATUS_COMPLETED_WITH_FINDINGS = 'completed_with_findings';

    public const SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS = 'completed_without_findings';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function catalogVersion(): BelongsTo
    {
        return $this->belongsTo(ExamCatalogVersion::class, 'exam_catalog_version_id');
    }

    public function resultValues(): HasMany
    {
        return $this->hasMany(ResultValue::class);
    }

    /**
     * Parámetros que se pueden solicitar por separado. Las matrices se informan siempre completas.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, string> Código del parámetro => "Sección · Parámetro"
     */
    public static function requestableParameters(array $snapshot): array
    {
        $parameters = [];

        foreach ($snapshot['sections'] ?? [] as $section) {
            if (($section['structure'] ?? null) === 'parasitological_matrix') {
                continue;
            }

            foreach ($section['parameters'] ?? [] as $parameter) {
                $code = $parameter['code'] ?? null;

                if (is_string($code) && $code !== '') {
                    $parameters[$code] = ($section['name'] ?? 'Sección').' · '.($parameter['name'] ?? $code);
                }
            }
        }

        return $parameters;
    }

    /**
     * Snapshot del examen limitado a los análisis solicitados en la orden.
     *
     * @return array<string, mixed>
     */
    public function requestedCatalogSnapshot(): array
    {
        $snapshot = $this->catalog_snapshot ?? [];

        if ($this->requested_parameter_codes === null) {
            return $snapshot;
        }

        $requestedCodes = array_flip($this->requested_parameter_codes);
        $sections = [];

        foreach ($snapshot['sections'] ?? [] as $section) {
            if (($section['structure'] ?? null) !== 'parasitological_matrix') {
                $section['parameters'] = array_values(array_filter(
                    $section['parameters'] ?? [],
                    fn (array $parameter): bool => isset($requestedCodes[$parameter['code'] ?? '']),
                ));

                if ($section['parameters'] === []) {
                    continue;
                }
            }

            $sections[] = $section;
        }

        $snapshot['sections'] = $sections;

        return $snapshot;
    }

    /**
     * @return list<string>
     */
    public function requestedParameterCodes(): array
    {
        return collect($this->requestedCatalogSnapshot()['sections'] ?? [])
            ->flatMap(fn (array $section): array => array_column($section['parameters'] ?? [], 'code'))
            ->filter(fn (mixed $code): bool => is_string($code) && $code !== '')
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return [
            'catalog_snapshot' => 'array',
            'requested_parameter_codes' => 'array',
            'section_statuses' => 'array',
        ];
    }
}
