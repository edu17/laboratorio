<?php

use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\UpdateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Filament\Resources\Orders\Pages\CaptureOrderExamResults;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\Patient;
use App\Models\ResultValue;
use App\Models\User;
use Database\Seeders\HecesCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function phaseFourUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function phaseFourOrderExam(User $administrator): OrderExam
{
    $patient = Patient::factory()->create(['first_names' => 'Paciente ficticio']);
    $exam = Exam::query()->where('code', 'HECES')->sole();

    return app(CreateLabOrder::class)
        ->handle($patient, [$exam->getKey()], $administrator)
        ->orderExams
        ->sole();
}

it('captures a draft from the order snapshot, supports matrix cells, and tracks edits without values in generic audit logs', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseFourUser('Administrador');
    $orderExam = phaseFourOrderExam($administrator);

    $input = [
        'values' => [
            'color' => ['value' => 'Marrón ficticio'],
            'entamoeba_histolytica' => ['cells' => ['quiste' => 'Dato ficticio']],
        ],
    ];

    app(SaveOrderExamResultDraft::class)->handle($orderExam, $input, $administrator);

    $color = ResultValue::query()->where('order_exam_id', $orderExam->getKey())->where('parameter_code', 'color')->sole();
    $matrixRow = ResultValue::query()->where('order_exam_id', $orderExam->getKey())->where('parameter_code', 'entamoeba_histolytica')->sole();

    expect($color->value)->toBe(['value' => 'Marrón ficticio'])
        ->and($matrixRow->value['cells']['quiste'])->toBe('Dato ficticio')
        ->and($orderExam->fresh()->status)->toBe(OrderExam::STATUS_IN_PROGRESS)
        ->and($orderExam->order->fresh()->status)->toBe(Order::STATUS_IN_PROGRESS);

    app(SaveOrderExamResultDraft::class)->handle($orderExam->fresh(), [
        'values' => ['color' => ['value' => 'Color ficticio actualizado']],
    ], $administrator);

    expect($color->fresh()->revisions()->sole()->previous_value)->toBe(['value' => 'Marrón ficticio']);
    expect(AuditLog::query()->where('action', 'results_draft_saved')->latest('id')->first()->metadata)
        ->toBe(['parameter_count' => 26]);
    expect(AuditLog::query()->where('action', 'results_draft_saved')->get()->pluck('metadata')->flatten()->all())
        ->not->toContain('Marrón ficticio', 'Dato ficticio', 'Color ficticio actualizado');
});

it('rejects values for parameters outside the stored exam snapshot and keeps incomplete drafts saveable', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseFourUser('Administrador');
    $orderExam = phaseFourOrderExam($administrator);

    expect(fn () => app(SaveOrderExamResultDraft::class)->handle($orderExam, [
        'values' => ['invented_parameter' => ['value' => 'Ficticio']],
    ], $administrator))->toThrow(ValidationException::class);

    app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => []], $administrator);

    expect($orderExam->fresh()->status)->toBe(OrderExam::STATUS_IN_PROGRESS)
        ->and($orderExam->resultValues()->count())->toBe(26);
});

it('validates select values against catalog options and accepts numeric and observation drafts', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseFourUser('Administrador');
    $orderExam = phaseFourOrderExam($administrator);
    $snapshot = $orderExam->catalog_snapshot;
    $snapshot['sections'] = [[
        'code' => 'prueba',
        'name' => 'Sección ficticia',
        'structure' => 'table',
        'parameters' => [
            ['code' => 'choice', 'name' => 'Opción', 'result_type' => 'select', 'options' => ['Uno', 'Dos']],
            ['code' => 'quantity', 'name' => 'Cantidad', 'result_type' => 'number', 'options' => []],
            ['code' => 'note', 'name' => 'Observación', 'result_type' => 'observation', 'options' => []],
        ],
    ]];
    $orderExam->update(['catalog_snapshot' => $snapshot]);

    $this->actingAs($administrator);

    Livewire::test(CaptureOrderExamResults::class, [
        'record' => $orderExam->order_id,
        'orderExam' => $orderExam->getKey(),
    ])
        ->assertSee('Opción')
        ->assertSee('Cantidad')
        ->assertSee('Observación');

    expect(fn () => app(SaveOrderExamResultDraft::class)->handle($orderExam->fresh(), [
        'values' => ['choice' => ['value' => 'No permitido']],
    ], $administrator))->toThrow(ValidationException::class);

    app(SaveOrderExamResultDraft::class)->handle($orderExam->fresh(), [
        'values' => [
            'choice' => ['value' => 'Dos'],
            'quantity' => ['value' => '12.5'],
            'note' => ['value' => 'Observación ficticia'],
        ],
    ], $administrator);

    expect($orderExam->resultValues()->count())->toBe(3)
        ->and($orderExam->resultValues()->where('parameter_code', 'choice')->sole()->value)->toBe(['value' => 'Dos'])
        ->and($orderExam->resultValues()->where('parameter_code', 'quantity')->sole()->value)->toBe(['value' => '12.5']);
});

it('blocks result capture for validator and technician and blocks closed or withdrawn exams', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseFourUser('Administrador');
    $validator = phaseFourUser('Validador');
    $technician = phaseFourUser('Técnico');
    $orderExam = phaseFourOrderExam($administrator);

    expect(Gate::forUser($validator)->allows('captureResults', $orderExam))->toBeFalse()
        ->and(Gate::forUser($technician)->allows('captureResults', $orderExam))->toBeFalse()
        ->and(Gate::forUser($administrator)->allows('captureResults', $orderExam))->toBeTrue();

    expect(fn () => app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => []], $validator))
        ->toThrow(AuthorizationException::class);

    $orderExam->update(['status' => OrderExam::STATUS_WITHDRAWN]);
    expect(Gate::forUser($administrator)->allows('captureResults', $orderExam->fresh()))->toBeFalse();
});

it('renders the capture page for administrators and saves its dynamic form', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseFourUser('Administrador');
    $orderExam = phaseFourOrderExam($administrator);
    $this->actingAs($administrator);

    $this->get("/admin/ordenes/{$orderExam->order_id}/examenes/{$orderExam->getKey()}/resultados")
        ->assertOk()
        ->assertSee('Color');

    Livewire::test(CaptureOrderExamResults::class, [
        'record' => $orderExam->order_id,
        'orderExam' => $orderExam->getKey(),
    ])
        ->assertSee('Color')
        ->assertSee('MATRIZ PARASITOLÓGICA')
        ->fillForm(['values' => ['color' => ['value' => 'Dato ficticio']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ResultValue::query()->where('parameter_code', 'color')->sole()->value)
        ->toBe(['value' => 'Dato ficticio']);

    $validator = phaseFourUser('Validador');
    $this->actingAs($validator);

    $this->get("/admin/ordenes/{$orderExam->order_id}")
        ->assertOk()
        ->assertSee('Dato ficticio');

    $this->get("/admin/ordenes/{$orderExam->order_id}/examenes/{$orderExam->getKey()}/resultados")
        ->assertForbidden();
});

it('allows an empty parasite matrix to be explicitly completed without findings', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseFourUser('Administrador');
    $orderExam = phaseFourOrderExam($administrator);

    $this->actingAs($administrator);

    Livewire::test(CaptureOrderExamResults::class, [
        'record' => $orderExam->order_id,
        'orderExam' => $orderExam->getKey(),
    ])
        ->assertSee('Estado de la matriz')
        ->fillForm([
            'section_statuses' => [
                'parasitologico' => OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS,
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($orderExam->fresh()->section_statuses['parasitologico'])
        ->toBe(OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS);

    $this->get("/admin/ordenes/{$orderExam->order_id}")
        ->assertOk()
        ->assertSee('Completada sin hallazgos');

    expect(Livewire::test(CaptureOrderExamResults::class, [
        'record' => $orderExam->order_id,
        'orderExam' => $orderExam->getKey(),
    ])->instance()->getMissingResultCount())->toBe(15);

    expect(fn () => app(SaveOrderExamResultDraft::class)->handle($orderExam->fresh(), [
        'section_statuses' => [
            'parasitologico' => OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS,
        ],
        'values' => [
            'entamoeba_histolytica' => ['cells' => ['quiste' => 'Hallazgo ficticio']],
        ],
    ], $administrator))->toThrow(ValidationException::class);
});

it('preserves the patient identity and in-progress state after result capture', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseFourUser('Administrador');
    $orderExam = phaseFourOrderExam($administrator);
    $order = $orderExam->order;
    $differentPatient = Patient::factory()->create();

    app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => ['color' => ['value' => 'Ficticio']]], $administrator);

    expect(fn () => app(UpdateLabOrder::class)->handle(
        $order->fresh(),
        $differentPatient,
        [$orderExam->exam_id],
        'Nota administrativa',
        $administrator,
    ))->toThrow(ValidationException::class);

    expect($orderExam->fresh()->status)->toBe(OrderExam::STATUS_IN_PROGRESS)
        ->and($order->fresh()->patient_id)->not->toBe($differentPatient->getKey());
});

it('still allows changing the patient after saving an empty draft', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseFourUser('Administrador');
    $orderExam = phaseFourOrderExam($administrator);
    $correctPatient = Patient::factory()->create();

    app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => []], $administrator);

    expect(ResultValue::where('order_exam_id', $orderExam->id)->count())->toBeGreaterThan(0);

    app(UpdateLabOrder::class)->handle($orderExam->order->fresh(), $correctPatient, [$orderExam->exam_id], null, $administrator);

    expect($orderExam->order->fresh()->patient_id)->toBe($correctPatient->id);
});
