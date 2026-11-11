<?php

use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\ReopenLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\OrderValidationEvent;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\HecesCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
});

function phaseFiveUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @return array<string, mixed> */
function phaseFiveValues(OrderExam $orderExam, bool $matrixHasFindings = true): array
{
    $values = [];
    $sectionStatuses = [];

    foreach ($orderExam->catalog_snapshot['sections'] ?? [] as $section) {
        $isMatrix = ($section['structure'] ?? null) === 'parasitological_matrix';
        $columns = $isMatrix ? array_slice($section['settings']['columns'] ?? [], 1) : [];

        foreach ($section['parameters'] ?? [] as $parameter) {
            $code = $parameter['code'];

            if ($isMatrix) {
                $cells = [];

                foreach ($columns as $column) {
                    $cells[Str::slug($column, '_')] = $matrixHasFindings ? 'Dato de prueba ficticio' : null;
                }

                $values[$code] = ['cells' => $cells];

                continue;
            }

            $type = $parameter['type'] ?? $parameter['result_type'] ?? 'text';
            $value = match ($type) {
                'number' => '12.5',
                'select', 'positive_negative' => $parameter['options'][0] ?? 'Opción ficticia',
                default => 'Resultado ficticio',
            };
            $values[$code] = ['value' => $value];
        }

        if ($isMatrix && is_string($section['code'] ?? null)) {
            $sectionStatuses[$section['code']] = $matrixHasFindings
                ? OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS
                : OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS;
        }
    }

    return ['values' => $values, 'section_statuses' => $sectionStatuses];
}

function phaseFiveOrder(User $administrator, bool $withSecondExam = false): Order
{
    $examIds = [Exam::query()->where('code', 'HECES')->value('id')];

    if ($withSecondExam) {
        $secondExam = Exam::factory()->create(['name' => 'Examen ficticio adicional']);
        $section = ExamSection::factory()->create([
            'exam_id' => $secondExam->getKey(),
            'code' => 'seccion_prueba',
            'name' => 'Sección ficticia',
        ]);
        ExamParameter::factory()->create([
            'exam_section_id' => $section->getKey(),
            'code' => 'parametro_ficticio',
            'name' => 'Parámetro ficticio',
            'result_type' => 'text',
            'is_active' => true,
        ]);
        $examIds[] = $secondExam->getKey();
    }

    return app(CreateLabOrder::class)->handle(
        Patient::factory()->create(['first_names' => 'Paciente ficticio']),
        $examIds,
        $administrator,
    );
}

function phaseFiveSaveResults(Order $order, User $administrator, ?string $changedColor = null): void
{
    foreach ($order->orderExams as $orderExam) {
        $data = phaseFiveValues($orderExam);

        if ($changedColor !== null && isset($data['values']['color'])) {
            $data['values']['color']['value'] = $changedColor;
        }

        app(SaveOrderExamResultDraft::class)->handle($orderExam, $data, $administrator);
    }
}

it('validates every active exam in one order-level operation and snapshots the approved results', function () {
    $administrator = phaseFiveUser('Administrador');
    $validator = phaseFiveUser('Validador');
    $order = phaseFiveOrder($administrator, withSecondExam: true);

    $heces = $order->orderExams->firstWhere('exam_id', Exam::query()->where('code', 'HECES')->value('id'));
    app(SaveOrderExamResultDraft::class)->handle($heces, phaseFiveValues($heces), $administrator);

    expect(fn () => app(ValidateLabOrder::class)->handle($order->fresh(), $validator))
        ->toThrow(ValidationException::class);
    expect($order->fresh()->status)->toBe(Order::STATUS_IN_PROGRESS)
        ->and($order->validationEvents()->count())->toBe(0);

    phaseFiveSaveResults($order->fresh(['orderExams']), $administrator);
    $validated = app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    expect($validated->status)->toBe(Order::STATUS_VALIDATED)
        ->and($validated->validated_by)->toBe($validator->getKey())
        ->and($validated->validated_at)->not->toBeNull()
        ->and($validated->validation_version)->toBe(1)
        ->and($validated->orderExams->every(fn (OrderExam $exam): bool => $exam->status === OrderExam::STATUS_VALIDATED))->toBeTrue()
        ->and($validated->validationEvents()->sole()->results_snapshot['exams'])->toHaveCount(2)
        ->and($validated->validationEvents()->sole()->results_snapshot['patient']['first_names'])->toBe('Paciente ficticio')
        ->and($validated->validationEvents()->sole()->results_snapshot['validated_by']['name'])->toBe($validator->name);

    expect(AuditLog::query()->where('action', 'order_validated')->sole()->metadata)
        ->toBe(['version' => 1, 'exam_count' => 2]);
    expect(Gate::forUser($administrator)->allows('validateResults', $validated))->toBeFalse();
});

it('allows an explicitly completed empty parasitological matrix and preserves its state in the snapshot', function () {
    $administrator = phaseFiveUser('Administrador');
    $validator = phaseFiveUser('Validador');
    $order = phaseFiveOrder($administrator);
    $orderExam = $order->orderExams->sole();

    app(SaveOrderExamResultDraft::class)->handle(
        $orderExam,
        phaseFiveValues($orderExam, matrixHasFindings: false),
        $administrator,
    );

    $validated = app(ValidateLabOrder::class)->handle($order->fresh(), $validator);
    $examSnapshot = $validated->validationEvents()->sole()->results_snapshot['exams'][0];
    $matrixSection = collect($orderExam->catalog_snapshot['sections'])
        ->firstWhere('structure', 'parasitological_matrix');
    $matrixCodes = collect($matrixSection['parameters'])->pluck('code')->all();
    $matrixResults = collect($examSnapshot['results'])
        ->filter(fn (array $result): bool => in_array($result['parameter_code'], $matrixCodes, true));

    expect($examSnapshot['section_statuses']['parasitologico'])
        ->toBe(OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS)
        ->and($matrixResults)->toHaveCount(count($matrixCodes))
        ->and($matrixResults->every(fn (array $result): bool => collect($result['value']['cells'] ?? [])->every(fn ($cell): bool => blank($cell))))
        ->toBeTrue();
});

it('allows only the validator to reopen, requires a reason, and preserves prior validation versions', function () {
    $administrator = phaseFiveUser('Administrador');
    $validator = phaseFiveUser('Validador');
    $order = phaseFiveOrder($administrator);
    phaseFiveSaveResults($order, $administrator);
    $validated = app(ValidateLabOrder::class)->handle($order->fresh(), $validator);
    $firstSnapshot = $validated->validationEvents()->sole()->results_snapshot;

    expect(Gate::forUser($administrator)->allows('reopenResults', $validated))->toBeFalse()
        ->and(Gate::forUser($validator)->allows('reopenResults', $validated))->toBeTrue();

    expect(fn () => app(ReopenLabOrder::class)->handle($validated, '   ', $validator))
        ->toThrow(ValidationException::class);
    expect(fn () => app(ReopenLabOrder::class)->handle($validated, 'Motivo ficticio', $administrator))
        ->toThrow(AuthorizationException::class);

    $reopened = app(ReopenLabOrder::class)->handle($validated, 'Corrección solicitada para la prueba', $validator);

    expect($reopened->status)->toBe(Order::STATUS_IN_PROGRESS)
        ->and($reopened->validated_by)->toBeNull()
        ->and($reopened->validated_at)->toBeNull()
        ->and($reopened->validation_version)->toBe(1)
        ->and($reopened->orderExams->sole()->status)->toBe(OrderExam::STATUS_IN_PROGRESS)
        ->and($reopened->validationEvents()->where('action', OrderValidationEvent::ACTION_REOPENED)->sole()->reason)
        ->toBe('Corrección solicitada para la prueba')
        ->and($reopened->validationEvents()->where('action', OrderValidationEvent::ACTION_VALIDATED)->sole()->results_snapshot)
        ->toBe($firstSnapshot);

    phaseFiveSaveResults($reopened->fresh(['orderExams']), $administrator, changedColor: 'Resultado ficticio corregido');
    $revalidated = app(ValidateLabOrder::class)->handle($reopened->fresh(), $validator);

    expect($revalidated->validation_version)->toBe(2)
        ->and($revalidated->validationEvents()->where('action', OrderValidationEvent::ACTION_VALIDATED)->count())->toBe(2)
        ->and($revalidated->validationEvents()->where('action', OrderValidationEvent::ACTION_REOPENED)->count())->toBe(1)
        ->and(collect($revalidated->validationEvents()->where('version', 2)->where('action', OrderValidationEvent::ACTION_VALIDATED)->sole()->results_snapshot['exams'][0]['results'])
            ->firstWhere('parameter_code', 'color')['value'])
        ->toBe(['value' => 'Resultado ficticio corregido']);
});

it('allows the validator to reopen a published order while blocking the other state changes', function () {
    $administrator = phaseFiveUser('Administrador');
    $validator = phaseFiveUser('Validador');
    $order = phaseFiveOrder($administrator);
    phaseFiveSaveResults($order, $administrator);
    $validated = app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    expect(Gate::forUser($administrator)->allows('update', $validated))->toBeFalse()
        ->and(Gate::forUser($administrator)->allows('cancel', $validated))->toBeFalse()
        ->and(Gate::forUser($validator)->allows('validateResults', $validated))->toBeFalse();

    $published = $validated->fresh()->forceFill(['status' => Order::STATUS_PUBLISHED]);
    expect(Gate::forUser($validator)->allows('reopenResults', $published))->toBeTrue();
});

it('shows validation and reopening actions only to the validator role', function () {
    $administrator = phaseFiveUser('Administrador');
    $validator = phaseFiveUser('Validador');
    $order = phaseFiveOrder($administrator);

    $this->actingAs($validator)
        ->get("/admin/ordenes/{$order->getKey()}")
        ->assertOk()
        ->assertSee('Validar orden completa')
        ->assertDontSee('Reabrir orden');

    $this->actingAs($administrator)
        ->get("/admin/ordenes/{$order->getKey()}")
        ->assertOk()
        ->assertDontSee('Validar orden completa')
        ->assertDontSee('Reabrir orden');

    phaseFiveSaveResults($order, $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    $this->actingAs($validator)
        ->get("/admin/ordenes/{$order->getKey()}")
        ->assertOk()
        ->assertSee('Reabrir orden');

    $this->actingAs($administrator)
        ->get("/admin/ordenes/{$order->getKey()}")
        ->assertOk()
        ->assertDontSee('Reabrir orden');
});
