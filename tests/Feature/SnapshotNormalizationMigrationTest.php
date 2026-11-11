<?php

use App\Models\Exam;
use App\Models\ExamCatalogVersion;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('renames old snapshot keys to the catalog column names without losing data', function () {
    $exam = Exam::factory()->create();
    $version = ExamCatalogVersion::create([
        'exam_id' => $exam->id,
        'version_number' => 1,
        'snapshot' => ['exam' => ['code' => 'X'], 'sections' => [['code' => 's', 'parameters' => [
            ['code' => 'p', 'type' => 'number', 'reference' => '1 - 2', 'order' => 10],
            ['code' => 'q', 'result_type' => 'text', 'reference_text' => null],
        ]]]],
        'published_at' => now(),
    ]);

    (require database_path('migrations/2026_11_11_174628_normalize_exam_snapshot_keys.php'))->up();

    expect($version->fresh()->snapshot['sections'][0]['parameters'])->toBe([
        ['code' => 'p', 'result_type' => 'number', 'reference_text' => '1 - 2', 'sort_order' => 10],
        ['code' => 'q', 'result_type' => 'text', 'reference_text' => null],
    ]);
});
