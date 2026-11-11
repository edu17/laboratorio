<?php

use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function phaseOneUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('redirects the home page to the admin panel', function () {
    $this->get('/')->assertRedirect(route('filament.admin.pages.dashboard'));
});

it('redirects guests to login and does not expose templates or patient records', function () {
    $this->get('/admin/login')->assertOk();
    $this->get('/admin/pacientes')->assertRedirect('/admin/login');
});

it('seeds roles idempotently without creating users or duplicate permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->assertDatabaseCount('roles', 4);
    $this->assertDatabaseCount('permissions', 39);
    $this->assertDatabaseCount('users', 0);
    expect(Role::findByName('Técnico')->hasPermissionTo('Manage:User'))->toBeFalse()
        ->and(Role::findByName('Recepción')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(Role::findByName('Técnico')->permissions->pluck('name')->sort()->values()->all());
});

it('keeps role permissions configured in the panel when the seeder runs again', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Role::findByName('Técnico')->revokePermissionTo('Capture:Result');
    Role::findByName('Recepción')->givePermissionTo('Cancel:Order');

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::findByName('Técnico')->hasPermissionTo('Capture:Result'))->toBeFalse()
        ->and(Role::findByName('Técnico')->hasPermissionTo('Create:Order'))->toBeTrue()
        ->and(Role::findByName('Recepción')->hasPermissionTo('Cancel:Order'))->toBeTrue();
});

it('lists every permission the panel uses in the role editor', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $resourcePermissions = ['Patient', 'ReferringDoctor', 'Exam', 'ExamSection', 'ExamParameter', 'Order'];
    $configurable = collect(config('filament-shield.custom_permissions'))->keys();

    Permission::query()->pluck('name')
        ->reject(fn (string $permission): bool => in_array(str($permission)->after(':')->toString(), $resourcePermissions, true)
            && in_array(str($permission)->before(':')->toString(), ['ViewAny', 'View', 'Create', 'Update'], true))
        ->each(fn (string $permission) => expect($configurable)->toContain($permission));
});

it('enforces patient and administration permissions for each initial role', function (string $role, bool $canWrite, bool $canManage) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = phaseOneUser($role);
    $patient = Patient::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', Patient::class))->toBeTrue();
    expect(Gate::forUser($user)->allows('view', $patient))->toBeTrue();
    expect(Gate::forUser($user)->allows('create', Patient::class))->toBe($canWrite);
    expect(Gate::forUser($user)->allows('update', $patient))->toBe($canWrite);
    expect(Gate::forUser($user)->allows('delete', $patient))->toBeFalse();
    expect(Gate::forUser($user)->allows('viewAny', User::class))->toBe($canManage);
    expect(Gate::forUser($user)->allows('viewAny', Role::class))->toBe($canManage);
    $this->actingAs($user)->get('/admin/pacientes')->assertOk();
})->with([
    ['Administrador', true, true],
    ['Técnico', true, false],
    ['Validador', false, false],
]);

it('blocks users without panel access and inactive users even with assigned permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();
    $user->assignRole('Administrador');
    $user->update(['is_active' => false]);

    $this->actingAs($user->fresh())->get('/admin/pacientes')->assertForbidden();
});

it('authenticates an authorized user and rejects an incorrect password', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = phaseOneUser('Técnico');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'incorrecta'])
        ->call('authenticate')->assertHasFormErrors(['email']);
    $this->assertGuest();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')->assertHasNoFormErrors();
    $this->assertAuthenticatedAs($user);
});

it('rejects a valid password for an inactive account', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = phaseOneUser('Técnico');
    $user->update(['is_active' => false]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')->assertHasFormErrors(['email']);
    $this->assertGuest();
});

it('allows a technician to create and edit a patient and records metadata without personal values', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = phaseOneUser('Técnico');
    $this->actingAs($user);

    Livewire::test(CreatePatient::class)
        ->fillForm(['first_names' => 'Paciente ficticio', 'last_names' => 'De Prueba', 'birth_date' => '1990-05-04', 'sex' => 'femenino', 'identifier' => 'prueba-001'])
        ->call('create')->assertHasNoFormErrors();
    $patient = Patient::where('identifier', 'PRUEBA-001')->sole();
    $this->assertDatabaseHas('patients', ['id' => $patient->id, 'first_names' => 'Paciente Ficticio', 'last_names' => 'De Prueba']);

    Livewire::test(EditPatient::class, ['record' => $patient->id])
        ->fillForm(['first_names' => 'Paciente ficticio corregido'])
        ->call('save')->assertHasNoFormErrors();
    $this->assertDatabaseHas('patients', ['id' => $patient->id, 'first_names' => 'Paciente Ficticio Corregido']);
    $event = AuditLog::where('entity_type', Patient::class)->where('action', 'updated')->sole();
    expect($event->user_id)->toBe($user->id);
    expect(json_encode($event->metadata))->not->toContain('Paciente ficticio');
});

it('validates patient required names future birth dates and duplicate identifiers', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(phaseOneUser('Técnico'));
    Patient::factory()->create(['identifier' => 'PRUEBA-001']);

    Livewire::test(CreatePatient::class)
        ->fillForm(['first_names' => '', 'birth_date' => today()->addDay()->toDateString(), 'identifier' => 'PRUEBA-001'])
        ->call('create')->assertHasFormErrors(['first_names' => 'required', 'birth_date', 'identifier' => 'unique']);
    $this->assertDatabaseCount('patients', 1);
});

it('searches and paginates patients without exposing another page of records', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(phaseOneUser('Técnico'));
    $patients = Patient::factory()->count(12)->create();
    $wanted = $patients->last();

    Livewire::test(ListPatients::class)
        ->set('tableRecordsPerPage', 10)
        ->assertCanSeeTableRecords($patients->slice(2))
        ->assertCanNotSeeTableRecords($patients->take(2))
        ->searchTable($wanted->first_names)
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords($patients->take(11));
});

it('blocks a validator from editing a patient or opening administrative routes', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(phaseOneUser('Validador'));
    $patient = Patient::factory()->create();

    $this->get('/admin/pacientes/'.$patient->id)->assertOk();
    $this->get('/admin/pacientes/'.$patient->id.'/edit')->assertForbidden();
    $this->get('/admin/pacientes/create')->assertForbidden();
    $this->get('/admin/usuarios')->assertForbidden();
    $this->get('/admin/shield/roles')->assertForbidden();
    $this->get('/admin/auditoria')->assertForbidden();
});

it('lets an administrator create an active user with a hashed password and assigned role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(phaseOneUser('Administrador'));
    $role = Role::findByName('Técnico');

    Livewire::test(CreateUser::class)->fillForm([
        'name' => 'Técnico ficticio', 'email' => 'tecnico@example.test',
        'password' => 'Prueba-Segura123!', 'password_confirmation' => 'Prueba-Segura123!',
        'is_active' => true, 'roles' => [$role->id],
    ])->call('create')->assertHasNoFormErrors();

    $created = User::where('email', 'tecnico@example.test')->sole();
    expect(Hash::check('Prueba-Segura123!', $created->password))->toBeTrue();
    expect($created->hasRole('Técnico'))->toBeTrue();
    expect($created->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('updates role permissions through Shield and audits the change', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(phaseOneUser('Administrador'));
    $role = Role::findByName('Validador');
    $this->get('/admin/shield/roles')->assertOk();
    $this->get('/admin/auditoria')->assertOk();

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm(['custom_permissions_tab' => ['Access:Panel', 'View:Audit']])
        ->call('save')->assertHasNoFormErrors();

    expect($role->fresh()->hasPermissionTo('View:Audit'))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['entity_type' => Role::class, 'entity_id' => $role->id, 'action' => 'PermissionAttachedEvent']);
});

it('prevents an administrator from deactivating their own account', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = phaseOneUser('Administrador');
    $this->actingAs($user);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['is_active' => false])->call('save')->assertHasFormErrors(['is_active']);

    expect($user->fresh()->is_active)->toBeTrue();
});

it('denies a forged patient write after permission is revoked', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = phaseOneUser('Técnico');
    $this->actingAs($user);
    $component = Livewire::test(CreatePatient::class)->fillForm(['first_names' => 'No guardar']);
    $user->removeRole('Técnico');

    $component->call('create')->assertForbidden();
    $this->assertDatabaseMissing('patients', ['first_names' => 'No guardar']);
});
