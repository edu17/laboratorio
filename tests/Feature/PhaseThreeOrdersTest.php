<?php

use App\Actions\Exams\PublishExamCatalogVersion;
use App\Actions\Orders\CancelLabOrder;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\UpdateLabOrder;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Exam;
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
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function phaseThreeUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('creates one order with multiple active exams and a unique yearly correlativo', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseThreeUser('Administrador');
    $patient = Patient::factory()->create(['first_names' => 'Paciente ficticio']);
    $heces = Exam::where('code', 'HECES')->sole();
    $otherExam = Exam::factory()->create(['name' => 'Examen ficticio']);

    $firstOrder = app(CreateLabOrder::class)->handle($patient, [$heces->id, $otherExam->id], $administrator, 'Nota de prueba');
    $secondOrder = app(CreateLabOrder::class)->handle($patient, [$heces->id], $administrator);

    expect($firstOrder->number)->toBe('LAB-'.now()->format('Y').'-000001');
    expect($secondOrder->number)->toBe('LAB-'.now()->format('Y').'-000002');
    expect($firstOrder->status)->toBe(Order::STATUS_PENDING);
    expect($firstOrder->orderExams)->toHaveCount(2);
    expect($firstOrder->notes)->toBe('Nota de prueba');
    expect($firstOrder->orderExams->firstWhere('exam_id', $heces->id)->catalog_snapshot['exam']['code'])->toBe('HECES');
    $this->assertModelExists($firstOrder);
    $this->assertModelExists($secondOrder);
});

it('captures the published exam catalog version on the order', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseThreeUser('Administrador');
    $patient = Patient::factory()->create();
    $exam = Exam::where('code', 'HECES')->sole();
    $version = app(PublishExamCatalogVersion::class)->handle($exam, $administrator);
    $exam->update(['name' => 'HECES actualizado después de publicar']);

    $order = app(CreateLabOrder::class)->handle($patient, [$exam->id], $administrator);
    $orderExam = $order->orderExams->sole();

    expect($orderExam->exam_catalog_version_id)->toBe($version->id);
    expect($orderExam->catalog_snapshot['exam']['name'])->toBe('Examen de heces');
    expect($orderExam->catalog_snapshot['sections'][0]['parameters'][0]['name'])->toBe('Color');
});

it('rejects inactive or missing exams without creating an order', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $patient = Patient::factory()->create();
    $administrator = phaseThreeUser('Administrador');
    $inactiveExam = Exam::factory()->create(['is_active' => false]);

    expect(fn () => app(CreateLabOrder::class)->handle($patient, [$inactiveExam->id], $administrator))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreateLabOrder::class)->handle($patient, [999999], $administrator))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseCount('orders', 0);
});

it('edits patient, notes, and exam selection while preserving removed exam history', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseThreeUser('Administrador');
    $patient = Patient::factory()->create();
    $replacementPatient = Patient::factory()->create();
    $heces = Exam::where('code', 'HECES')->sole();
    $otherExam = Exam::factory()->create();
    $order = app(CreateLabOrder::class)->handle($patient, [$heces->id, $otherExam->id], $administrator);
    $originalSnapshot = $order->orderExams->firstWhere('exam_id', $heces->id)->catalog_snapshot;

    $updated = app(UpdateLabOrder::class)->handle($order, $replacementPatient, [$otherExam->id], 'Actualizada', $administrator);

    expect($updated->patient_id)->toBe($replacementPatient->id);
    expect($updated->notes)->toBe('Actualizada');
    expect($updated->orderExams->firstWhere('exam_id', $heces->id)->status)->toBe(OrderExam::STATUS_WITHDRAWN);
    expect($updated->orderExams->firstWhere('exam_id', $heces->id)->catalog_snapshot)->toBe($originalSnapshot);

    $restored = app(UpdateLabOrder::class)->handle($updated, $replacementPatient, [$heces->id, $otherExam->id], 'Reintegrada', $administrator);
    expect($restored->orderExams->firstWhere('exam_id', $heces->id)->status)->toBe(OrderExam::STATUS_PENDING);
    expect($restored->orderExams->firstWhere('exam_id', $heces->id)->catalog_snapshot)->toBe($originalSnapshot);
});

it('cancels an order with a reason and records the actor and exam statuses', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseThreeUser('Administrador');
    $order = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(),
        [Exam::where('code', 'HECES')->value('id')],
        $administrator,
    );

    $canceled = app(CancelLabOrder::class)->handle($order, 'Orden duplicada por error', $administrator);

    expect($canceled->status)->toBe(Order::STATUS_CANCELED);
    expect($canceled->cancellation_reason)->toBe('Orden duplicada por error');
    expect($canceled->canceled_by)->toBe($administrator->id);
    expect($canceled->orderExams->sole()->status)->toBe(OrderExam::STATUS_CANCELED);
});

it('requires a cancellation reason and rejects unauthorized order changes', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $patient = Patient::factory()->create();
    $administrator = phaseThreeUser('Administrador');
    $validator = phaseThreeUser('Validador');
    $order = app(CreateLabOrder::class)->handle($patient, [Exam::factory()->create()->id], $administrator);

    expect(fn () => app(CancelLabOrder::class)->handle($order, ' ', $administrator))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CancelLabOrder::class)->handle($order, 'Motivo', $validator))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateLabOrder::class)->handle($order, $patient, [$order->orderExams->sole()->exam_id], 'No autorizado', $validator))
        ->toThrow(AuthorizationException::class);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => Order::STATUS_PENDING]);
});

it('creates orders through the authenticated Filament form and allows permitted editing', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $this->actingAs(phaseThreeUser('Administrador'));
    $patient = Patient::factory()->create();
    $exam = Exam::where('code', 'HECES')->sole();
    $otherExam = Exam::factory()->create(['name' => 'Examen ficticio']);

    Livewire::test(CreateOrder::class)
        ->fillForm(['patient_id' => $patient->id, 'exam_ids' => [$exam->id, $otherExam->id], 'notes' => 'Prueba de interfaz', 'referring_doctor' => 'Dra. Ficticia Inicial'])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::where('patient_id', $patient->id)->sole();
    expect($order->orderExams()->count())->toBe(2);
    expect($order->referring_doctor)->toBe('Dra. Ficticia Inicial');

    Livewire::test(EditOrder::class, ['record' => $order->id])
        ->assertSet('data.exam_ids', [$exam->id, $otherExam->id])
        ->fillForm(['patient_id' => $patient->id, 'exam_ids' => [$exam->id], 'notes' => 'Nota actualizada', 'referring_doctor' => 'Dra. Ficticia Actualizada'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'notes' => 'Nota actualizada', 'referring_doctor' => 'Dra. Ficticia Actualizada']);
    $this->assertDatabaseHas('order_exams', [
        'order_id' => $order->id,
        'exam_id' => $otherExam->id,
        'status' => OrderExam::STATUS_WITHDRAWN,
    ]);
});

it('allows validators to read orders but blocks changes and redirects guests to login', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $order = Order::factory()->create();
    $this->get('/admin/ordenes')->assertRedirect('/admin/login');

    $this->actingAs(phaseThreeUser('Validador'))
        ->get('/admin/ordenes')
        ->assertOk();
    expect(Gate::forUser(auth()->user())->allows('view', $order))->toBeTrue();
    expect(Gate::forUser(auth()->user())->allows('update', $order))->toBeFalse();
    expect(Gate::forUser(auth()->user())->allows('cancel', $order))->toBeFalse();
    $this->get('/admin/ordenes/'.$order->id.'/edit')->assertForbidden();
    $this->get('/admin/ordenes/'.$order->id)->assertOk();

    $this->actingAs(phaseThreeUser('Técnico'))
        ->get('/admin/pacientes')
        ->assertOk();
    $this->get('/admin/ordenes')->assertForbidden();
});

it('searches orders by number patient exam and captured result values', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseThreeUser('Administrador');
    $heces = Exam::query()->where('code', 'HECES')->sole();
    $otherExam = Exam::factory()->create(['name' => 'Examen ficticio alterno']);
    $patient = Patient::factory()->create([
        'first_names' => 'Lucía Ficticia',
        'last_names' => 'Paciente De Prueba',
        'identifier' => 'HISTORIA-205',
    ]);
    $matchingOrder = app(CreateLabOrder::class)->handle($patient, [$heces->getKey()], $administrator);
    $matchingOrderExam = $matchingOrder->orderExams->sole();
    $matchingOrderExam->resultValues()->create([
        'parameter_code' => 'color',
        'value' => ['value' => 'Rosado ficticio'],
        'edited_by' => $administrator->getKey(),
    ]);
    $otherOrder = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(['first_names' => 'Otro Paciente']),
        [$otherExam->getKey()],
        $administrator,
    );
    $historicalOrder = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(['first_names' => 'Paciente Con Historial']),
        [$heces->getKey()],
        $administrator,
    );
    $historicalOrder->update([
        'status' => Order::STATUS_VALIDATED,
        'validation_version' => 1,
    ]);
    $historicalOrder->validationEvents()->create([
        'version' => 1,
        'action' => OrderValidationEvent::ACTION_VALIDATED,
        'actor_id' => $administrator->getKey(),
        'results_snapshot' => [
            'exams' => [[
                'results' => [[
                    'parameter_code' => 'organismo',
                    'value' => ['cells' => ['trofozoito' => 'Trofozoito histórico ficticio']],
                ]],
            ]],
        ],
        'acted_at' => now(),
    ]);
    $this->actingAs($administrator);

    Livewire::test(ListOrders::class)
        ->searchTable('Rosado ficticio')
        ->assertCanSeeTableRecords([$matchingOrder])
        ->assertCanNotSeeTableRecords([$otherOrder]);

    Livewire::test(ListOrders::class)
        ->searchTable('HECES')
        ->assertCanSeeTableRecords([$matchingOrder])
        ->assertCanNotSeeTableRecords([$otherOrder]);

    Livewire::test(ListOrders::class)
        ->searchTable('HISTORIA-205')
        ->assertCanSeeTableRecords([$matchingOrder])
        ->assertCanNotSeeTableRecords([$otherOrder]);

    Livewire::test(ListOrders::class)
        ->searchTable($otherOrder->number)
        ->assertCanSeeTableRecords([$otherOrder])
        ->assertCanNotSeeTableRecords([$matchingOrder]);

    Livewire::test(ListOrders::class)
        ->searchTable('Trofozoito')
        ->assertCanSeeTableRecords([$historicalOrder])
        ->assertCanNotSeeTableRecords([$matchingOrder, $otherOrder]);
});

it('combines the exam status and date filters when listing order history', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseThreeUser('Administrador');
    $heces = Exam::query()->where('code', 'HECES')->sole();
    $otherExam = Exam::factory()->create(['name' => 'Otro examen ficticio']);
    $matchingOrder = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(),
        [$heces->getKey()],
        $administrator,
    );
    $wrongDateOrder = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(),
        [$heces->getKey()],
        $administrator,
    );
    $wrongExamOrder = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(),
        [$otherExam->getKey()],
        $administrator,
    );
    $matchingOrder->update(['ordered_at' => '2026-03-12 10:00:00', 'status' => Order::STATUS_PENDING]);
    $wrongDateOrder->update(['ordered_at' => '2026-04-12 10:00:00', 'status' => Order::STATUS_PENDING]);
    $wrongExamOrder->update(['ordered_at' => '2026-03-14 10:00:00', 'status' => Order::STATUS_PENDING]);
    $this->actingAs($administrator);

    Livewire::test(ListOrders::class)
        ->filterTable('exam_id', $heces->getKey())
        ->filterTable('status', Order::STATUS_PENDING)
        ->filterTable('ordered_at', ['from' => '2026-03-01', 'until' => '2026-03-31'])
        ->assertCanSeeTableRecords([$matchingOrder])
        ->assertCanNotSeeTableRecords([$wrongDateOrder, $wrongExamOrder]);
});
