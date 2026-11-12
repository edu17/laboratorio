<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * El laboratorio depuró 2025.xlsx y dejó solo los formatos que usa.
 *
 * Lo que ya no aparece en el libro se desactiva en lugar de borrarse, para que las órdenes que lo usaron
 * conserven su historial. La vitamina D queda validada porque su única escala es la de la hoja VITAMINA D.
 * Si un examen afectado tiene versiones publicadas, se publica una nueva con el cambio para que las órdenes
 * nuevas lo usen; las versiones anteriores no se modifican.
 */
return new class extends Migration
{
    /** @var list<string> Exámenes cuya hoja (INFCC) ya no está en el libro. */
    private const RETIRED_EXAMS = ['SARAMPION_IGM'];

    /**
     * Código del examen => parámetros que ya no aparecen en el libro. De Hormonas (hojas TIROGLOBULINAS y PTH,
     * eliminadas) solo siguen en el borrador la testosterona libre, el cortisol, la ACTH y la insulina preprandial.
     *
     * @var array<string, list<string>>
     */
    private const RETIRED_PARAMETERS = [
        'HORMONAS' => ['prolactina', 'testosterona', 'hidroxiprogesterona_17', 'insulina_postprandial', 'indice_homa'],
        'TIROIDEAS' => ['tiroglobulina'],
        'FERROCINETICAS' => ['captacion_hierro'],
        'MARCADORES_TUMORALES' => ['ca_125', 'ca_19_9'],
        'HERPES_SIMPLE' => ['herpes_igm_tipo_1'],
    ];

    private const IRON_MALE_RANGE = ['texto_masculino' => 'Hombres: 61 a 157 µg/dL', 'min_masculino' => '61', 'max_masculino' => '157'];

    private const IRON_FEMALE_RANGE = ['texto_femenino' => 'Mujeres: 37 a 145 µg/dL', 'min_femenino' => '37', 'max_femenino' => '145'];

    private const HORMONES_OLD_SOURCE = '2025.xlsx · hoja TIROGLOBULINAS';

    private const HORMONES_NEW_SOURCE = '2025.xlsx · hoja INMUNOLOGIA';

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('exams')->whereIn('code', self::RETIRED_EXAMS)->update(['is_active' => false, 'updated_at' => now()]);

            foreach (self::RETIRED_PARAMETERS as $examCode => $parameterCodes) {
                $this->parameterQuery($examCode, $parameterCodes)->update(['is_active' => false, 'updated_at' => now()]);
            }

            $this->parameterQuery('FERROCINETICAS', ['hierro_serico'])->get(['exam_parameters.id', 'exam_parameters.reference_config'])
                ->filter(fn (object $parameter): bool => json_decode((string) $parameter->reference_config, true) == self::IRON_MALE_RANGE + self::IRON_FEMALE_RANGE)
                ->each(fn (object $parameter) => DB::table('exam_parameters')->where('id', $parameter->id)->update([
                    'reference_config' => json_encode(self::IRON_MALE_RANGE, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]));

            $this->parameterQuery('VITAMINA_D', ['vitamina_d'])
                ->update(['clinical_validation_status' => 'validated', 'updated_at' => now()]);

            $this->replaceHormonesSource(self::HORMONES_OLD_SOURCE, self::HORMONES_NEW_SOURCE);

            foreach ([...array_keys(self::RETIRED_PARAMETERS), 'VITAMINA_D'] as $examCode) {
                $this->publishUpdatedVersion($examCode);
            }
        });
    }

    /**
     * Reactiva el catálogo vivo; las versiones publicadas son historial y no se retiran.
     */
    public function down(): void
    {
        DB::table('exams')->whereIn('code', self::RETIRED_EXAMS)->update(['is_active' => true, 'updated_at' => now()]);

        foreach (self::RETIRED_PARAMETERS as $examCode => $parameterCodes) {
            $this->parameterQuery($examCode, $parameterCodes)->update(['is_active' => true, 'updated_at' => now()]);
        }

        $this->parameterQuery('FERROCINETICAS', ['hierro_serico'])->get(['exam_parameters.id', 'exam_parameters.reference_config'])
            ->filter(fn (object $parameter): bool => json_decode((string) $parameter->reference_config, true) == self::IRON_MALE_RANGE)
            ->each(fn (object $parameter) => DB::table('exam_parameters')->where('id', $parameter->id)->update([
                'reference_config' => json_encode(self::IRON_MALE_RANGE + self::IRON_FEMALE_RANGE, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]));

        $this->parameterQuery('VITAMINA_D', ['vitamina_d'])
            ->update(['clinical_validation_status' => 'pending', 'updated_at' => now()]);

        $this->replaceHormonesSource(self::HORMONES_NEW_SOURCE, self::HORMONES_OLD_SOURCE);
    }

    /**
     * La hoja de origen de Hormonas solo se cambia si sigue siendo la que cargó el catálogo.
     */
    private function replaceHormonesSource(string $from, string $to): void
    {
        $examId = DB::table('exams')->where('code', 'HORMONAS')->value('id');

        if (! $examId) {
            return;
        }

        $sectionIds = DB::table('exam_sections')->where('exam_id', $examId)->pluck('id');

        DB::table('exams')->where('id', $examId)->where('source', $from)->update(['source' => $to, 'updated_at' => now()]);
        DB::table('exam_sections')->whereIn('id', $sectionIds)->where('source', $from)->update(['source' => $to, 'updated_at' => now()]);
        DB::table('exam_parameters')->whereIn('exam_section_id', $sectionIds)->where('source', $from)->update(['source' => $to, 'updated_at' => now()]);
    }

    private function publishUpdatedVersion(string $examCode): void
    {
        $examId = DB::table('exams')->where('code', $examCode)->value('id');
        $latestVersion = $examId
            ? DB::table('exam_catalog_versions')->where('exam_id', $examId)->orderByDesc('version_number')->first()
            : null;

        if (! $latestVersion) {
            return;
        }

        $snapshot = json_decode($latestVersion->snapshot, true);
        $updated = $this->updateSnapshot($snapshot, self::RETIRED_PARAMETERS[$examCode] ?? []);

        if ($updated === $snapshot) {
            return;
        }

        DB::table('exam_catalog_versions')->insert([
            'exam_id' => $examId,
            'version_number' => $latestVersion->version_number + 1,
            'snapshot' => json_encode($updated, JSON_UNESCAPED_UNICODE),
            'created_by' => null,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>  $retiredCodes
     * @return array<string, mixed>
     */
    private function updateSnapshot(array $snapshot, array $retiredCodes): array
    {
        foreach ($snapshot['sections'] ?? [] as $sectionIndex => $section) {
            $parameters = [];

            foreach ($section['parameters'] ?? [] as $parameter) {
                if (in_array($parameter['code'] ?? null, $retiredCodes, true)) {
                    continue;
                }

                if (($parameter['code'] ?? null) === 'hierro_serico' && ($parameter['reference_config'] ?? null) == self::IRON_MALE_RANGE + self::IRON_FEMALE_RANGE) {
                    $parameter['reference_config'] = self::IRON_MALE_RANGE;
                }

                if (($parameter['code'] ?? null) === 'vitamina_d') {
                    $parameter['clinical_validation_status'] = 'validated';
                }

                $parameters[] = $parameter;
            }

            $snapshot['sections'][$sectionIndex]['parameters'] = $parameters;
        }

        return $snapshot;
    }

    /**
     * @param  list<string>  $parameterCodes
     */
    private function parameterQuery(string $examCode, array $parameterCodes): Builder
    {
        return DB::table('exam_parameters')
            ->whereIn('exam_parameters.code', $parameterCodes)
            ->whereIn('exam_parameters.exam_section_id', DB::table('exam_sections')
                ->select('exam_sections.id')
                ->join('exams', 'exams.id', '=', 'exam_sections.exam_id')
                ->where('exams.code', $examCode));
    }
};
