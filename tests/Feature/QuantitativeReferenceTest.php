<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Filament\Resources\ExamParameters\Pages\CreateExamParameter;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use App\Models\Order;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
});

function quantitativeUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Creatinina ficticia con referencia y rango distintos por sexo.
 */
function quantitativeCreatinineExam(string $validationStatus = 'validated'): Exam
{
    $exam = Exam::factory()->create(['code' => 'RENALES', 'name' => 'Pruebas renales']);
    $section = ExamSection::factory()->create(['exam_id' => $exam->id, 'code' => 'renales', 'name' => 'Pruebas renales']);

    ExamParameter::factory()->create([
        'exam_section_id' => $section->id,
        'code' => 'creatinina',
        'name' => 'Creatinina',
        'result_type' => 'number',
        'unit' => 'mg/dL',
        'reference_text' => 'Referencia general ficticia',
        'reference_config' => [
            'min_masculino' => '0.60', 'max_masculino' => '1.10', 'texto_masculino' => 'HOMBRES 0.60 - 1.10 mg/dL',
            'min_femenino' => '0.40', 'max_femenino' => '0.80', 'texto_femenino' => 'MUJERES 0.40 - 0.80 mg/dL',
        ],
        'clinical_validation_status' => $validationStatus,
    ]);

    return $exam;
}

/**
 * @return array{name: string, result: string, unit: string, reference: string, flag: string|null}
 */
function quantitativeReportRow(Exam $exam, ?string $sex, string $value): array
{
    $administrator = quantitativeUser('Administrador');
    $validator = quantitativeUser('Validador');
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(['sex' => $sex]), [$exam->id], $administrator);

    app(SaveOrderExamResultDraft::class)->handle($order->orderExams->sole(), ['values' => ['creatinina' => ['value' => $value]]], $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    return app(BuildValidatedOrderReportData::class)->handle(Order::findOrFail($order->id), $validator)['exams'][0]['sections'][0]['rows'][0];
}

it('prints the reference for the patient sex and flags results outside that range', function (?string $sex, string $value, string $result, string $reference, ?string $flag) {
    $row = quantitativeReportRow(quantitativeCreatinineExam(), $sex, $value);

    expect($row['result'])->toBe($result)
        ->and($row['reference'])->toBe($reference)
        ->and($row['flag'])->toBe($flag);
})->with([
    'man above his range' => ['masculino', '1.5', '1.5', 'HOMBRES 0.60 - 1.10 mg/dL', 'alto'],
    'woman inside her range' => ['femenino', '0.7', '0.7', 'MUJERES 0.40 - 0.80 mg/dL', null],
    'woman below her range' => ['femenino', '0.3', '0.3', 'MUJERES 0.40 - 0.80 mg/dL', 'bajo'],
    'unknown sex uses the general reference' => [null, '1.5', '1.5', 'Referencia general ficticia', null],
    'comparator result is not flagged' => ['masculino', '<0.3', '< 0.3', 'HOMBRES 0.60 - 1.10 mg/dL', null],
]);

it('marks an out of range result with an asterisk in the printed report', function () {
    $administrator = quantitativeUser('Administrador');
    $validator = quantitativeUser('Validador');
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(['sex' => 'masculino']), [quantitativeCreatinineExam()->id], $administrator);

    app(SaveOrderExamResultDraft::class)->handle($order->orderExams->sole(), ['values' => ['creatinina' => ['value' => '1.5']]], $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    $report = app(BuildValidatedOrderReportData::class)->handle(Order::findOrFail($order->id), $validator);

    $this->view('reports.order', ['report' => $report, 'footerReserveMm' => 20])
        ->assertSeeHtml('<strong class="out-of-range">*1.5</strong>')
        ->assertSee('HOMBRES 0.60 - 1.10 mg/dL');
});

it('does not print or flag a reference that is still pending validation', function () {
    $row = quantitativeReportRow(quantitativeCreatinineExam('pending'), 'masculino', '1.5');

    expect($row['reference'])->toBe('')
        ->and($row['flag'])->toBeNull();
});

it('rejects numeric results that are not a number with an optional comparator', function (string $value) {
    $administrator = quantitativeUser('Administrador');
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [quantitativeCreatinineExam()->id], $administrator);

    expect(fn () => app(SaveOrderExamResultDraft::class)->handle($order->orderExams->sole(), ['values' => ['creatinina' => ['value' => $value]]], $administrator))
        ->toThrow(ValidationException::class, 'Escribe un número');
})->with(['texto' => ['alto'], 'decimal con coma' => ['1,02'], 'comparador sin número' => ['<']]);

it('stores references longer than 255 characters', function () {
    $longReference = str_repeat('Hombres 25 a 34 años: 160 a 449 µg/dL. ', 10);
    $parameter = ExamParameter::factory()->create(['reference_text' => $longReference]);

    expect(mb_strlen($longReference))->toBeGreaterThan(255)
        ->and($parameter->fresh()->reference_text)->toBe($longReference);
});

it('validates the structured reference keys in the parameter form', function (array $config, string $message) {
    $this->actingAs(quantitativeUser('Administrador'));
    $section = ExamSection::factory()->create();

    Livewire::test(CreateExamParameter::class)
        ->fillForm([
            'exam_section_id' => $section->id,
            'code' => 'parametro_ficticio',
            'name' => 'Parámetro ficticio',
            'result_type' => 'number',
            'reference_config' => $config,
            'is_active' => true,
            'sort_order' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['reference_config'])
        ->assertSee($message);

    expect(ExamParameter::where('code', 'parametro_ficticio')->exists())->toBeFalse();
})->with([
    'unknown key' => [['minimo' => '1'], 'La clave «minimo» no está permitida.'],
    'non numeric limit' => [['max_femenino' => 'alto'], 'El valor de «max_femenino» debe ser un número.'],
]);
