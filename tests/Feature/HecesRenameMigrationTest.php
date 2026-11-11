<?php

use App\Models\Exam;
use App\Models\ExamCatalogVersion;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function hecesRenameMigration(): object
{
    return require database_path('migrations/2026_11_11_141117_rename_heces_exam_to_sentence_case.php');
}

it('renames the heces exam and publishes a new catalog version without touching the previous ones', function () {
    $exam = Exam::factory()->create(['code' => 'HECES', 'name' => 'EXÁMEN DE HECES']);
    $previous = ExamCatalogVersion::create([
        'exam_id' => $exam->id,
        'version_number' => 2,
        'snapshot' => ['exam' => ['code' => 'HECES', 'name' => 'EXÁMEN DE HECES'], 'sections' => [['code' => 'macroscopico']]],
        'published_at' => now(),
    ]);

    hecesRenameMigration()->up();

    $latest = $exam->fresh()->latestCatalogVersion;

    expect($exam->fresh()->name)->toBe('Examen de heces')
        ->and($latest->version_number)->toBe(3)
        ->and($latest->snapshot)->toBe(['exam' => ['code' => 'HECES', 'name' => 'Examen de heces'], 'sections' => [['code' => 'macroscopico']]])
        ->and($previous->fresh()->snapshot['exam']['name'])->toBe('EXÁMEN DE HECES');
});

it('leaves an exam renamed by hand untouched', function () {
    $exam = Exam::factory()->create(['code' => 'HECES', 'name' => 'Coproparasitológico']);

    hecesRenameMigration()->up();

    expect($exam->fresh()->name)->toBe('Coproparasitológico')
        ->and(ExamCatalogVersion::count())->toBe(0);
});
