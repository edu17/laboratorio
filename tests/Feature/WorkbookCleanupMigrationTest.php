<?php

use App\Models\Exam;
use App\Models\ExamCatalogVersion;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function workbookCleanupMigration(): object
{
    return require database_path('migrations/2026_11_12_180949_retire_exams_missing_from_current_workbook.php');
}

/**
 * @param  array<string, array<string, mixed>>  $parameters  Código => atributos
 */
function workbookExam(string $code, array $parameters): Exam
{
    $exam = Exam::factory()->create(['code' => $code, 'is_active' => true]);
    $section = ExamSection::factory()->for($exam)->create(['code' => 'resultado']);

    foreach ($parameters as $parameterCode => $attributes) {
        ExamParameter::factory()->for($section, 'section')->create(['code' => $parameterCode, ...$attributes]);
    }

    return $exam;
}

function workbookParameter(string $examCode, string $parameterCode): ExamParameter
{
    return ExamParameter::query()
        ->where('code', $parameterCode)
        ->whereHas('section.exam', fn ($query) => $query->where('code', $examCode))
        ->sole();
}

$ironBothSexes = [
    'texto_masculino' => 'Hombres: 61 a 157 µg/dL', 'min_masculino' => '61', 'max_masculino' => '157',
    'texto_femenino' => 'Mujeres: 37 a 145 µg/dL', 'min_femenino' => '37', 'max_femenino' => '145',
];

it('retires what the current workbook no longer has and validates vitamin D', function () use ($ironBothSexes) {
    $measles = workbookExam('SARAMPION_IGM', ['sarampion_igm' => []]);
    $hormones = workbookExam('HORMONAS', ['prolactina' => [], 'indice_homa' => [], 'testosterona_libre' => []]);
    $thyroid = workbookExam('TIROIDEAS', ['t3' => [], 'tiroglobulina' => []]);
    workbookExam('FERROCINETICAS', ['hierro_serico' => ['reference_config' => $ironBothSexes], 'captacion_hierro' => []]);
    workbookExam('VITAMINA_D', ['vitamina_d' => ['clinical_validation_status' => 'pending']]);

    workbookCleanupMigration()->up();

    expect($measles->fresh()->is_active)->toBeFalse()
        ->and($hormones->fresh()->is_active)->toBeTrue()
        ->and(workbookParameter('HORMONAS', 'prolactina')->is_active)->toBeFalse()
        ->and(workbookParameter('HORMONAS', 'indice_homa')->is_active)->toBeFalse()
        ->and(workbookParameter('HORMONAS', 'testosterona_libre')->is_active)->toBeTrue()
        ->and($thyroid->fresh()->is_active)->toBeTrue()
        ->and(workbookParameter('TIROIDEAS', 't3')->is_active)->toBeTrue()
        ->and(workbookParameter('TIROIDEAS', 'tiroglobulina')->is_active)->toBeFalse()
        ->and(workbookParameter('FERROCINETICAS', 'captacion_hierro')->is_active)->toBeFalse()
        ->and(workbookParameter('FERROCINETICAS', 'hierro_serico')->reference_config)
        ->toEqual(['texto_masculino' => 'Hombres: 61 a 157 µg/dL', 'min_masculino' => '61', 'max_masculino' => '157'])
        ->and(workbookParameter('VITAMINA_D', 'vitamina_d')->clinical_validation_status)->toBe('validated')
        ->and(ExamCatalogVersion::count())->toBe(0);
});

it('publishes a new version without the retired parameters and keeps the previous one', function () {
    $thyroid = workbookExam('TIROIDEAS', ['t3' => [], 'tiroglobulina' => []]);
    $snapshot = ['exam' => ['code' => 'TIROIDEAS'], 'sections' => [['code' => 'resultado', 'parameters' => [['code' => 't3'], ['code' => 'tiroglobulina']]]]];
    $previous = ExamCatalogVersion::create(['exam_id' => $thyroid->id, 'version_number' => 1, 'snapshot' => $snapshot, 'published_at' => now()]);

    workbookCleanupMigration()->up();

    $latest = $thyroid->fresh()->latestCatalogVersion;

    expect($latest->version_number)->toBe(2)
        ->and(array_column($latest->snapshot['sections'][0]['parameters'], 'code'))->toBe(['t3'])
        ->and($previous->fresh()->snapshot)->toBe($snapshot);
});

it('leaves an iron reference edited by hand untouched', function () {
    $edited = ['texto_masculino' => 'Hombres: 65 a 175 µg/dL', 'min_masculino' => '65', 'max_masculino' => '175'];
    workbookExam('FERROCINETICAS', ['hierro_serico' => ['reference_config' => $edited]]);

    workbookCleanupMigration()->up();

    expect(workbookParameter('FERROCINETICAS', 'hierro_serico')->reference_config)->toEqual($edited);
});
