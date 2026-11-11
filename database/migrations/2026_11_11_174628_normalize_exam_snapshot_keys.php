<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lleva los snapshots de examen ya guardados al formato único (nombres de columna del catálogo):
 * type → result_type, reference → reference_text, order → sort_order.
 * Afecta versiones publicadas del catálogo, exámenes de órdenes y eventos de validación.
 */
return new class extends Migration
{
    private const RENAMED_KEYS = ['type' => 'result_type', 'reference' => 'reference_text', 'order' => 'sort_order'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rewrite('exam_catalog_versions', 'snapshot', fn (array $snapshot): array => $this->normalizeExam($snapshot));
        $this->rewrite('order_exams', 'catalog_snapshot', fn (array $snapshot): array => $this->normalizeExam($snapshot));
        $this->rewrite('order_validation_events', 'results_snapshot', function (array $snapshot): array {
            foreach ($snapshot['exams'] ?? [] as $index => $exam) {
                if (is_array($exam['catalog_snapshot'] ?? null)) {
                    $snapshot['exams'][$index]['catalog_snapshot'] = $this->normalizeExam($exam['catalog_snapshot']);
                }
            }

            return $snapshot;
        });
    }

    /**
     * El formato anterior ya no se lee: no hay vuelta atrás útil.
     */
    public function down(): void
    {
        //
    }

    private function rewrite(string $table, string $column, Closure $normalize): void
    {
        DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column, $normalize): void {
            foreach ($rows as $row) {
                $snapshot = json_decode($row->{$column}, true);

                if (! is_array($snapshot)) {
                    continue;
                }

                $normalized = $normalize($snapshot);

                if ($normalized !== $snapshot) {
                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($normalized, JSON_UNESCAPED_UNICODE)]);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function normalizeExam(array $snapshot): array
    {
        foreach ($snapshot['sections'] ?? [] as $sectionIndex => $section) {
            foreach ($section['parameters'] ?? [] as $parameterIndex => $parameter) {
                foreach (self::RENAMED_KEYS as $old => $new) {
                    if (array_key_exists($old, $parameter)) {
                        $parameter[$new] ??= $parameter[$old];
                        unset($parameter[$old]);
                    }
                }

                $snapshot['sections'][$sectionIndex]['parameters'][$parameterIndex] = $parameter;
            }
        }

        return $snapshot;
    }
};
