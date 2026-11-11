<?php

use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\UpdateLabOrder;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\ReferringDoctors\Pages\CreateReferringDoctor;
use App\Filament\Resources\ReferringDoctors\Pages\EditReferringDoctor;
use App\Filament\Resources\ReferringDoctors\Pages\ListReferringDoctors;
use App\Models\Exam;
use App\Models\Order;
use App\Models\Patient;
use App\Models\ReferringDoctor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
});

function referringDoctorUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets administrators and technicians register referring doctors with a unique name', function (string $role) {
    $this->actingAs(referringDoctorUser($role));
    ReferringDoctor::factory()->create(['name' => 'Dr. Juan Ejemplo']);

    Livewire::test(CreateReferringDoctor::class)
        ->fillForm(['name' => 'Farmacia Ficticia Central', 'kind' => ReferringDoctor::KIND_INSTITUTION, 'phone' => '55550000', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateReferringDoctor::class)
        ->fillForm(['name' => 'Dr. Juan Ejemplo', 'kind' => ReferringDoctor::KIND_DOCTOR])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    $this->assertDatabaseHas('referring_doctors', ['name' => 'Farmacia Ficticia Central', 'kind' => ReferringDoctor::KIND_INSTITUTION]);
    expect(ReferringDoctor::count())->toBe(2);
})->with(['Administrador', 'Técnico']);

it('lets validators read the doctor catalog but not change it', function () {
    $this->actingAs(referringDoctorUser('Validador'));
    $doctor = ReferringDoctor::factory()->create();

    $this->get('/admin/medicos-remitentes')->assertOk();
    $this->get('/admin/medicos-remitentes/create')->assertForbidden();
    $this->get("/admin/medicos-remitentes/{$doctor->id}/edit")->assertForbidden();
});

it('lists doctors with their order count and hides inactive ones by default', function () {
    $administrator = referringDoctorUser('Administrador');
    $this->actingAs($administrator);
    $active = ReferringDoctor::factory()->create();
    $inactive = ReferringDoctor::factory()->inactive()->create();
    app(CreateLabOrder::class)->handle(Patient::factory()->create(), [Exam::factory()->create()->id], $administrator, referringDoctorId: $active->id);

    Livewire::test(ListReferringDoctors::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive])
        ->assertTableColumnStateSet('orders_count', 1, $active);
});

it('stores the doctor and a copy of the name on the order, and keeps that copy if the doctor is renamed', function () {
    $administrator = referringDoctorUser('Administrador');
    $doctor = ReferringDoctor::factory()->create(['name' => 'Dr. Nombre Original']);
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [Exam::factory()->create()->id], $administrator, referringDoctorId: $doctor->id);

    $this->actingAs($administrator);
    Livewire::test(EditReferringDoctor::class, ['record' => $doctor->id])
        ->fillForm(['name' => 'Dr. Nombre Corregido'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($order->fresh()->referring_doctor_id)->toBe($doctor->id)
        ->and($order->fresh()->referring_doctor)->toBe('Dr. Nombre Original');
});

it('rejects inactive doctors on new orders but keeps one already assigned when editing', function () {
    $administrator = referringDoctorUser('Administrador');
    $doctor = ReferringDoctor::factory()->create();
    $patient = Patient::factory()->create();
    $exam = Exam::factory()->create();
    $order = app(CreateLabOrder::class)->handle($patient, [$exam->id], $administrator, referringDoctorId: $doctor->id);
    $doctor->update(['is_active' => false]);

    expect(fn () => app(CreateLabOrder::class)->handle($patient, [$exam->id], $administrator, referringDoctorId: $doctor->id))
        ->toThrow(ValidationException::class, 'inactivo');

    app(UpdateLabOrder::class)->handle($order, $patient, [$exam->id], 'Nota editada', $administrator, referringDoctorId: $doctor->id);

    expect($order->fresh()->referring_doctor_id)->toBe($doctor->id)
        ->and(Order::count())->toBe(1);
});

it('offers only active doctors in the order form and registers a new one from it', function () {
    $this->actingAs(referringDoctorUser('Administrador'));
    $active = ReferringDoctor::factory()->create(['name' => 'Dra. Activa Ficticia']);
    $inactive = ReferringDoctor::factory()->inactive()->create(['name' => 'Dr. Inactivo Ficticio']);

    Livewire::test(CreateOrder::class)
        ->assertFormFieldExists('referring_doctor_id', function (Select $field) use ($active, $inactive): bool {
            expect($field->getOptions())->toHaveKey($active->id)->not->toHaveKey($inactive->id);

            return true;
        })
        ->callFormComponentAction('referring_doctor_id', 'createOption', data: ['name' => 'Dr. Nuevo Desde Orden', 'kind' => ReferringDoctor::KIND_DOCTOR, 'is_active' => true])
        ->assertHasNoFormComponentActionErrors();

    $this->assertDatabaseHas('referring_doctors', ['name' => 'Dr. Nuevo Desde Orden', 'is_active' => true]);
});

it('filters the order list by referring doctor', function () {
    $administrator = referringDoctorUser('Administrador');
    $this->actingAs($administrator);
    $doctor = ReferringDoctor::factory()->create();
    $exam = Exam::factory()->create();
    $referred = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $administrator, referringDoctorId: $doctor->id);
    $withoutReferral = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $administrator);

    Livewire::test(ListOrders::class)
        ->filterTable('referring_doctor_id', $doctor->id)
        ->assertCanSeeTableRecords([$referred])
        ->assertCanNotSeeTableRecords([$withoutReferral]);
});

it('moves free text referrals of existing orders into the catalog', function () {
    $administrator = referringDoctorUser('Administrador');
    $exam = Exam::factory()->create();
    $existing = ReferringDoctor::factory()->create(['name' => 'Dra. Ya Registrada']);
    $first = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $administrator, null, 'Dr. Texto  Libre');
    $second = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $administrator, null, 'Dr. Texto  Libre');
    $third = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $administrator, null, 'dra. ya registrada');
    $migration = require database_path('migrations/2026_11_11_144250_add_referring_doctor_id_to_orders_table.php');

    $migration->linkExistingReferrals();

    $doctor = ReferringDoctor::where('name', 'Dr. Texto Libre')->sole();

    expect(DB::table('orders')->whereIn('id', [$first->id, $second->id])->pluck('referring_doctor_id')->unique()->values()->all())->toBe([$doctor->id])
        ->and($third->fresh()->referring_doctor_id)->toBe($existing->id)
        ->and(ReferringDoctor::count())->toBe(2)
        ->and($first->fresh()->referring_doctor)->toBe('Dr. Texto  Libre');
});
