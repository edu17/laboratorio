<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\UpdateLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\HecesCatalogSeeder;
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

function partialRequestUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Examen ficticio con dos secciones: pruebas renales (creatinina, BUN) y química clínica (glucosa).
 */
function partialRequestChemistryExam(bool $allowsPartialRequest = true): Exam
{
    $exam = Exam::factory()->create([
        'code' => 'QUIMICA',
        'name' => 'Química sanguínea',
        'allows_partial_request' => $allowsPartialRequest,
    ]);
    $renal = ExamSection::factory()->create(['exam_id' => $exam->id, 'code' => 'renales', 'name' => 'Pruebas renales', 'sort_order' => 10]);
    $chemistry = ExamSection::factory()->create(['exam_id' => $exam->id, 'code' => 'quimica_clinica', 'name' => 'Química clínica', 'sort_order' => 20]);

    ExamParameter::factory()->create(['exam_section_id' => $renal->id, 'code' => 'creatinina', 'name' => 'Creatinina', 'sort_order' => 10]);
    ExamParameter::factory()->create(['exam_section_id' => $renal->id, 'code' => 'bun', 'name' => 'BUN', 'sort_order' => 20]);
    ExamParameter::factory()->create(['exam_section_id' => $chemistry->id, 'code' => 'glucosa_pre', 'name' => 'Glucosa pre', 'sort_order' => 10]);

    return $exam;
}

function partialRequestOrderExam(Order $order): OrderExam
{
    return $order->orderExams()->where('exam_id', Exam::where('code', 'QUIMICA')->value('id'))->sole();
}

it('stores only the requested analyses and treats a full selection as the complete exam', function () {
    $administrator = partialRequestUser('Administrador');
    $patient = Patient::factory()->create();
    $exam = partialRequestChemistryExam();

    $partialOrder = app(CreateLabOrder::class)->handle($patient, [$exam->id], $administrator, requestedParameterCodes: [$exam->id => ['creatinina']]);
    $fullOrder = app(CreateLabOrder::class)->handle($patient, [$exam->id], $administrator, requestedParameterCodes: [$exam->id => ['glucosa_pre', 'bun', 'creatinina']]);
    $defaultOrder = app(CreateLabOrder::class)->handle($patient, [$exam->id], $administrator);

    $partialOrderExam = partialRequestOrderExam($partialOrder);

    expect($partialOrderExam->requested_parameter_codes)->toBe(['creatinina'])
        ->and(collect($partialOrderExam->requestedCatalogSnapshot()['sections'])->pluck('name')->all())->toBe(['Pruebas renales'])
        ->and($partialOrderExam->requestedParameterCodes())->toBe(['creatinina'])
        ->and(partialRequestOrderExam($fullOrder)->requested_parameter_codes)->toBeNull()
        ->and(partialRequestOrderExam($defaultOrder)->requested_parameter_codes)->toBeNull();
});

it('rejects partial selections that are empty, unknown, or for exams reported complete', function (bool $allowsPartialRequest, array $codes, string $message) {
    $administrator = partialRequestUser('Administrador');
    $exam = partialRequestChemistryExam($allowsPartialRequest);

    expect(fn () => app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $administrator, requestedParameterCodes: [$exam->id => $codes]))
        ->toThrow(ValidationException::class, $message);

    expect(Order::count())->toBe(0);
})->with([
    'empty selection' => [true, [], 'Selecciona al menos un análisis'],
    'unknown analysis' => [true, ['creatinina', 'hemoglobina'], 'no pertenecen al examen'],
    'exam reported complete' => [false, ['creatinina'], 'no permite solicitar análisis por separado'],
]);

it('captures, validates, and reports only the requested analyses', function () {
    $administrator = partialRequestUser('Administrador');
    $validator = partialRequestUser('Validador');
    $exam = partialRequestChemistryExam();
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $administrator, requestedParameterCodes: [$exam->id => ['creatinina']]);
    $orderExam = partialRequestOrderExam($order);

    expect(fn () => app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => ['glucosa_pre' => ['value' => '95']]], $administrator))
        ->toThrow(ValidationException::class, 'no pertenecen al examen solicitado');

    app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => ['creatinina' => ['value' => '1.02']]], $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    $report = app(BuildValidatedOrderReportData::class)->handle($order->fresh(), $validator);
    $sections = $report['exams'][0]['sections'];

    expect($order->fresh()->status)->toBe(Order::STATUS_VALIDATED)
        ->and(array_column($sections, 'name'))->toBe(['Pruebas renales'])
        ->and(array_column($sections[0]['rows'], 'name'))->toBe(['Creatinina'])
        ->and($sections[0]['rows'][0]['result'])->toBe('1.02');
});

it('changes requested analyses while editing but keeps analyses that already have results', function () {
    $administrator = partialRequestUser('Administrador');
    $exam = partialRequestChemistryExam();
    $patient = Patient::factory()->create();
    $order = app(CreateLabOrder::class)->handle($patient, [$exam->id], $administrator, requestedParameterCodes: [$exam->id => ['creatinina']]);

    app(UpdateLabOrder::class)->handle($order, $patient, [$exam->id], null, $administrator, requestedParameterCodes: [$exam->id => ['creatinina', 'glucosa_pre']]);

    expect(partialRequestOrderExam($order)->requested_parameter_codes)->toBe(['creatinina', 'glucosa_pre']);

    app(SaveOrderExamResultDraft::class)->handle(partialRequestOrderExam($order), ['values' => ['creatinina' => ['value' => '1.02']]], $administrator);

    expect(fn () => app(UpdateLabOrder::class)->handle($order->fresh(), $patient, [$exam->id], null, $administrator, requestedParameterCodes: [$exam->id => ['glucosa_pre']]))
        ->toThrow(ValidationException::class, 'Pruebas renales · Creatinina');

    expect(partialRequestOrderExam($order)->requested_parameter_codes)->toBe(['creatinina', 'glucosa_pre']);
});

it('selects requested analyses through the order form', function () {
    $this->seed(HecesCatalogSeeder::class);
    $this->actingAs(partialRequestUser('Administrador'));
    $patient = Patient::factory()->create();
    $exam = partialRequestChemistryExam();
    $heces = Exam::where('code', 'HECES')->sole();

    Livewire::test(CreateOrder::class)
        ->fillForm(['patient_id' => $patient->id, 'exam_ids' => [$exam->id, $heces->id]])
        ->assertFormFieldExists("requested_parameter_codes.{$exam->id}")
        ->assertFormFieldDoesNotExist("requested_parameter_codes.{$heces->id}")
        ->fillForm(["requested_parameter_codes.{$exam->id}" => ['creatinina']])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::where('patient_id', $patient->id)->sole();

    expect(partialRequestOrderExam($order)->requested_parameter_codes)->toBe(['creatinina'])
        ->and($order->orderExams()->where('exam_id', $heces->id)->value('requested_parameter_codes'))->toBeNull();

    Livewire::test(EditOrder::class, ['record' => $order->id])
        ->assertSet("data.requested_parameter_codes.{$exam->id}", ['creatinina'])
        ->fillForm(["requested_parameter_codes.{$exam->id}" => ['creatinina', 'bun']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(partialRequestOrderExam($order)->requested_parameter_codes)->toBe(['creatinina', 'bun']);
});
