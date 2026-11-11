<?php

use App\Actions\Orders\CreateLabOrder;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\OrdersRelationManager;
use App\Models\Exam;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->reception = User::factory()->create();
    $this->reception->assignRole('Recepción');
});

it('stores names, document and phone in one consistent format however they are typed', function () {
    $patient = Patient::create([
        'first_names' => '  maría   JOSÉ ',
        'last_names' => 'de LA cruz  y   pérez',
        'identifier' => ' 2547 85412 0101 ',
        'phone' => '5628-2580',
    ]);

    expect($patient->first_names)->toBe('María José')
        ->and($patient->last_names)->toBe('De la Cruz y Pérez')
        ->and($patient->identifier)->toBe('2547 85412 0101')
        ->and($patient->phone)->toBe('56282580');
});

it('finds possible duplicates by name regardless of accents and case, by document, or by surnames and birth date', function () {
    $existing = Patient::factory()->create(['first_names' => 'José Luis', 'last_names' => 'Pérez Gómez', 'birth_date' => '1980-03-02', 'identifier' => 'DPI-1']);
    Patient::factory()->create(['first_names' => 'Ana', 'last_names' => 'Pérez Ruiz', 'birth_date' => '1990-01-01']);

    expect(Patient::possibleDuplicates('jose luis', 'perez gomez')->pluck('id')->all())->toBe([$existing->id])
        ->and(Patient::possibleDuplicates('Otro', 'Nombre', null, 'dpi-1')->pluck('id')->all())->toBe([$existing->id])
        ->and(Patient::possibleDuplicates('José', 'Pérez Gómez', '1980-03-02')->pluck('id')->all())->toBe([$existing->id])
        ->and(Patient::possibleDuplicates('José Luis', 'Pérez Gómez', null, null, $existing->id))->toBeEmpty();
});

it('requires birth date and sex, blocks an exact duplicate and warns about a similar patient', function () {
    $this->actingAs($this->reception);
    Patient::factory()->create(['first_names' => 'Rosario', 'last_names' => 'Esquivel', 'birth_date' => '1987-10-19']);

    Livewire::test(CreatePatient::class)
        ->fillForm(['first_names' => 'Nuevo', 'last_names' => 'Paciente'])
        ->call('create')
        ->assertHasFormErrors(['birth_date' => 'required', 'sex' => 'required']);

    Livewire::test(CreatePatient::class)
        ->fillForm(['first_names' => 'rosario', 'last_names' => 'ESQUIVEL', 'birth_date' => '1987-10-19', 'sex' => 'femenino'])
        ->call('create')
        ->assertHasFormErrors(['first_names']);

    Livewire::test(CreatePatient::class)
        ->fillForm(['first_names' => 'Rosario', 'last_names' => 'Esquivel', 'birth_date' => '1990-01-01', 'sex' => 'femenino'])
        ->assertSee('Posible paciente ya registrado')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Patient::where('last_names', 'Esquivel')->count())->toBe(2);
});

it('searches patients by first and last name together', function () {
    $this->actingAs($this->reception);
    $wanted = Patient::factory()->create(['first_names' => 'Rosario Nerea', 'last_names' => 'Esquivel Valladares']);
    $other = Patient::factory()->create(['first_names' => 'Rosario', 'last_names' => 'Gómez', 'phone' => '55551234']);
    $accented = Patient::factory()->create(['first_names' => 'José', 'last_names' => 'Pérez Núñez']);

    Livewire::test(ListPatients::class)
        ->searchTable('rosario esquivel')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other, $accented]);

    Livewire::test(ListPatients::class)
        ->searchTable('jose nunez')
        ->assertCanSeeTableRecords([$accented])
        ->assertCanNotSeeTableRecords([$wanted, $other]);

    Livewire::test(CreateOrder::class)
        ->assertFormFieldExists('patient_id', function (Select $field) use ($wanted, $other): bool {
            expect($field->getSearchResults('rosario esquivel'))->toHaveKey($wanted->id)->not->toHaveKey($other->id);

            return true;
        });
});

it('shows the patient history and starts a new order with the patient already selected', function () {
    $this->actingAs($this->reception);
    $patient = Patient::factory()->create(['birth_date' => now()->subYears(40)->toDateString(), 'sex' => 'masculino']);
    $exam = Exam::factory()->create(['name' => 'Examen ficticio de historial']);
    $orders = collect(range(1, 3))->map(fn () => app(CreateLabOrder::class)->handle($patient, [$exam->id], $this->reception));
    $otherOrder = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $this->reception);

    $this->get("/admin/pacientes/{$patient->id}")->assertOk()->assertSee('40 años');

    Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $patient, 'pageClass' => ViewPatient::class])
        ->assertCanSeeTableRecords($orders)
        ->assertCanNotSeeTableRecords([$otherOrder])
        ->assertSee('Examen ficticio de historial');

    $this->get('/admin/ordenes/create?patient='.$patient->id)->assertOk();
    Livewire::withQueryParams(['patient' => $patient->id])
        ->test(CreateOrder::class)
        ->assertSet('data.patient_id', $patient->id);
});
