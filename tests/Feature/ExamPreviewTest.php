<?php

use App\Actions\Exams\BuildExamPreviewReportData;
use App\Actions\Exams\PublishExamCatalogVersion;
use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Filament\Resources\ExamParameters\Pages\CreateExamParameter;
use App\Filament\Resources\ExamParameters\Pages\EditExamParameter;
use App\Filament\Resources\Exams\Pages\ViewExam;
use App\Filament\Resources\ExamSections\Pages\CreateExamSection;
use App\Filament\Resources\ExamSections\Pages\EditExamSection;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('lets ORINA use the same paired layout as HECES and preserves that choice in validated reports', function (): void {
    $administrator = User::factory()->create();
    $administrator->assignRole('Administrador');
    $validator = User::factory()->create();
    $validator->assignRole('Validador');
    $this->actingAs($administrator);
    $exam = Exam::factory()->create(['code' => 'ORINA', 'name' => 'ORINA DE PRUEBA']);
    Livewire::test(CreateExamSection::class)
        ->fillForm([
            'exam_id' => $exam->id,
            'code' => 'macroscopico',
            'name' => 'MACROSCÓPICO',
            'structure' => 'table',
            'settings' => ['report_layout' => 'paired'],
            'is_active' => true,
            'sort_order' => 10,
        ])->call('create')->assertHasNoFormErrors();
    $section = $exam->sections()->sole();
    $parameters = ExamParameter::factory()->count(3)->create(['exam_section_id' => $section->id]);
    $preview = app(BuildExamPreviewReportData::class)->handle($exam->fresh(), $administrator);
    expect($preview['exams'][0]['sections'][0]['layout'])->toBe('paired');
    expect(view('reports.order', ['report' => $preview])->render())->toContain('class="results paired-results"');
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $administrator);
    app(SaveOrderExamResultDraft::class)->handle($order->orderExams->sole(), [
        'values' => $parameters->mapWithKeys(fn (ExamParameter $parameter): array => [$parameter->code => ['value' => 'Dato ficticio']])->all(),
    ], $administrator);
    $order = app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    Livewire::test(EditExamSection::class, ['record' => $section->id])
        ->fillForm(['settings' => ['report_layout' => 'standard']])
        ->call('save')->assertHasNoFormErrors();
    $updatedPreview = app(BuildExamPreviewReportData::class)->handle($exam->fresh(), $administrator);
    $validated = app(BuildValidatedOrderReportData::class)->handle($order, $validator);

    expect($updatedPreview['exams'][0]['sections'][0]['layout'])->toBe('standard');
    expect($validated['exams'][0]['sections'][0]['layout'])->toBe('paired');
    expect($validated['exams'][0]['sections'][0]['rows'])->toHaveCount(3);
    expect(view('reports.order', ['report' => $validated])->render())->toContain('class="results paired-results"');
});

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('allows only users with exam viewing permission to generate a draft PDF', function (string $role, bool $allowed): void {
    $user = User::factory()->create();
    $user->assignRole($role);
    $exam = Exam::factory()->create();

    $response = $this->actingAs($user)->get(route('exams.preview', $exam));

    if ($allowed) {
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-', false);
    } else {
        $response->assertForbidden();
    }
})->with(['administrator' => ['Administrador', true], 'validator' => ['Validador', true], 'technician' => ['Técnico', false]]);

it('requires login before viewing a catalog preview', function (): void {
    $exam = Exam::factory()->create();

    $this->get(route('exams.preview', $exam))->assertRedirect('/admin/login');
});

it('previews the editable catalog in order without touching clinical orders or published versions', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Administrador');
    $exam = Exam::factory()->create(['code' => 'ORINA', 'name' => 'ORINA DE PRUEBA']);
    $section = ExamSection::factory()->create(['exam_id' => $exam->id, 'sort_order' => 10]);
    $first = ExamParameter::factory()->create(['exam_section_id' => $section->id, 'name' => 'Campo original', 'sort_order' => 10, 'unit' => 'unidad ficticia', 'reference_text' => 'Referencia de prueba']);
    ExamParameter::factory()->create(['exam_section_id' => $section->id, 'name' => 'Segundo campo', 'sort_order' => 20, 'result_type' => 'number']);
    ExamParameter::factory()->create(['exam_section_id' => $section->id, 'is_active' => false, 'name' => 'Oculto']);
    $version = app(PublishExamCatalogVersion::class)->handle($exam, $user);
    $first->update(['name' => 'Campo actualizado <script>prueba</script>']);
    $matrix = ExamSection::factory()->create(['exam_id' => $exam->id, 'sort_order' => 20, 'structure' => 'parasitological_matrix', 'settings' => ['columns' => ['Fila ficticia', 'Forma A', 'Forma B']]]);
    ExamParameter::factory()->create(['exam_section_id' => $matrix->id, 'result_type' => 'matrix_row']);

    $report = app(BuildExamPreviewReportData::class)->handle($exam->fresh(), $user);
    $html = view('reports.order', ['report' => $report])->render();

    expect($report['is_preview'])->toBeTrue();
    expect(array_column($report['exams'][0]['sections'][0]['rows'], 'name'))->toBe(['Campo actualizado <script>prueba</script>', 'Segundo campo']);
    expect($report['exams'][0]['sections'][0]['rows'][0]['reference'])->toBe('Referencia de prueba (pendiente de validación)');
    expect($report['exams'][0]['sections'][1]['rows'][0]['cells'])->toHaveCount(2);
    expect($html)->toContain('BORRADOR', 'DATOS FICTICIOS', '&lt;script&gt;');
    expect($html)->not->toContain('class="validated-state">Validado', 'alt="QR para consultar el reporte"', '<script>prueba</script>', 'alt="Firma');
    expect(collect($version->fresh()->snapshot['sections'][0]['parameters'])->firstWhere('code', $first->code)['name'])->toBe('Campo original');
    foreach (['orders', 'result_values', 'report_access_tokens', 'order_validation_events'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

it('offers the preview while adding and editing fields and supports an empty inactive draft exam', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Administrador');
    $this->actingAs($user);
    $exam = Exam::factory()->create(['is_active' => false]);
    $report = app(BuildExamPreviewReportData::class)->handle($exam, $user);
    expect(view('reports.order', ['report' => $report])->render())->toContain('todavía no tiene secciones activas');
    $section = ExamSection::factory()->create(['exam_id' => $exam->id]);
    $parameter = ExamParameter::factory()->create(['exam_section_id' => $section->id]);

    Livewire::test(CreateExamParameter::class)
        ->assertActionHidden('previewExam')
        ->fillForm(['exam_section_id' => $section->id])
        ->assertActionVisible('previewExam');
    Livewire::test(EditExamParameter::class, ['record' => $parameter->id])->assertActionVisible('previewExam');
    Livewire::test(ViewExam::class, ['record' => $exam->id])->assertActionVisible('preview')->assertActionDoesNotExist('publishCatalogVersion');
    $this->get(route('exams.preview', $exam))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});
