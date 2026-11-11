<?php

use App\Actions\Exams\BuildExamResultFormDefinition;
use App\Actions\Exams\PublishExamCatalogVersion;
use App\Filament\Resources\ExamParameters\Pages\CreateExamParameter;
use App\Filament\Resources\ExamParameters\Pages\EditExamParameter;
use App\Filament\Resources\ExamSections\Pages\CreateExamSection;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamCatalogVersion;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use App\Models\User;
use Database\Seeders\HecesCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function phaseTwoUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('distinguishes identically named sections by exam when creating searching and editing parameters', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $this->actingAs(phaseTwoUser('Administrador'));
    $hecesSection = Exam::query()->where('code', 'HECES')->sole()->sections()->where('code', 'macroscopico')->sole();
    $orina = Exam::factory()->create(['code' => 'ORINA', 'name' => 'Examen de orina de prueba']);
    $orinaSection = ExamSection::factory()->create(['exam_id' => $orina->id, 'name' => $hecesSection->name]);

    Livewire::test(CreateExamParameter::class)
        ->assertFormFieldExists('exam_section_id', function (Select $field) use ($hecesSection, $orinaSection): bool {
            expect($field->getOptions())
                ->toHaveKey($hecesSection->id, 'HECES — '.$hecesSection->name)
                ->toHaveKey($orinaSection->id, 'ORINA — '.$orinaSection->name);
            expect($field->getSearchResults('ORINA'))->toBe([$orinaSection->id => 'ORINA — '.$orinaSection->name]);
            expect($field->getSearchResults('macrosc'))->toHaveKeys([$hecesSection->id, $orinaSection->id]);

            return true;
        })
        ->fillForm([
            'exam_section_id' => $orinaSection->id,
            'code' => 'parametro_ficticio',
            'name' => 'Parámetro ficticio de prueba',
            'result_type' => 'text',
            'is_active' => true,
            'sort_order' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $parameter = ExamParameter::query()->where('code', 'parametro_ficticio')->sole();
    expect($parameter->exam_section_id)->toBe($orinaSection->id);
    Livewire::test(EditExamParameter::class, ['record' => $parameter->id])
        ->assertSet('data.exam_section_id', $orinaSection->id)
        ->assertFormFieldExists('exam_section_id', function (Select $field) use ($orinaSection): bool {
            expect($field->getOptionLabel())->toBe('ORINA — '.$orinaSection->name);

            return true;
        });
});

it('seeds the HECES catalog from its source structure without inventing clinical units or references', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $this->seed(HecesCatalogSeeder::class);

    $exam = Exam::where('code', 'HECES')->sole();

    expect($exam->name)->toBe('EXÁMEN DE HECES');
    expect($exam->clinical_validation_status)->toBe('pending');
    expect($exam->sections()->count())->toBe(3);
    expect(ExamParameter::query()->count())->toBe(26);
    expect(ExamParameter::query()->whereNull('unit')->count())->toBe(26);
    expect(ExamParameter::query()->whereNull('reference_text')->count())->toBe(26);
    expect($exam->sections()->where('structure', 'parasitological_matrix')->firstOrFail()->settings['columns'])
        ->toBe(['Organismo', 'Quiste', 'Trofozoíto', 'Huevo', 'Larva']);
});

it('allows administrators to manage the catalog and validators to consult it while technicians remain limited to patients', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $exam = Exam::where('code', 'HECES')->sole();
    $section = $exam->sections()->firstOrFail();
    $parameter = $section->parameters()->firstOrFail();

    $administrator = phaseTwoUser('Administrador');
    expect(Gate::forUser($administrator)->allows('viewAny', Exam::class))->toBeTrue();
    expect(Gate::forUser($administrator)->allows('create', Exam::class))->toBeTrue();
    expect(Gate::forUser($administrator)->allows('update', $exam))->toBeTrue();
    expect(Gate::forUser($administrator)->allows('delete', $exam))->toBeFalse();
    expect(Gate::forUser($administrator)->allows('create', ExamSection::class))->toBeTrue();
    expect(Gate::forUser($administrator)->allows('create', ExamParameter::class))->toBeTrue();

    $this->actingAs(phaseTwoUser('Validador'))
        ->get('/admin/catalogo/examenes')
        ->assertOk();
    expect(Gate::forUser(auth()->user())->allows('view', $exam))->toBeTrue();
    expect(Gate::forUser(auth()->user())->allows('viewAny', ExamSection::class))->toBeTrue();
    expect(Gate::forUser(auth()->user())->allows('viewAny', ExamParameter::class))->toBeTrue();
    expect(Gate::forUser(auth()->user())->allows('update', $exam))->toBeFalse();
    expect(Gate::forUser(auth()->user())->allows('update', $section))->toBeFalse();
    expect(Gate::forUser(auth()->user())->allows('update', $parameter))->toBeFalse();
    $this->get('/admin/catalogo/examenes/create')->assertForbidden();
    $this->get('/admin/catalogo/secciones')->assertOk();
    $this->get('/admin/catalogo/parametros')->assertOk();

    $this->actingAs(phaseTwoUser('Técnico'))
        ->get('/admin/pacientes')
        ->assertOk();
    $this->get('/admin/catalogo/examenes')->assertForbidden();
});

it('creates sections and typed parameters through the protected catalog forms', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $this->actingAs(phaseTwoUser('Administrador'));
    $exam = Exam::where('code', 'HECES')->sole();

    Livewire::test(CreateExamSection::class)
        ->fillForm([
            'exam_id' => $exam->id,
            'code' => 'seccion_prueba',
            'name' => 'Sección de prueba',
            'structure' => 'parasitological_matrix',
            'settings' => ['columns' => ['Organismo', 'Forma']],
            'is_active' => true,
            'sort_order' => 90,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $section = ExamSection::where('code', 'seccion_prueba')->sole();
    $this->assertModelExists($section);

    Livewire::test(CreateExamParameter::class)
        ->fillForm([
            'exam_section_id' => $section->id,
            'code' => 'resultado_textual',
            'name' => 'Resultado de prueba',
            'result_type' => 'select',
            'unit' => null,
            'reference_text' => null,
            'reference_config' => null,
            'options' => ['Opción A', 'Opción B'],
            'is_active' => true,
            'sort_order' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('exam_parameters', [
        'exam_section_id' => $section->id,
        'code' => 'resultado_textual',
        'result_type' => 'select',
        'unit' => null,
        'reference_text' => null,
    ]);
    expect($section->fresh()->settings['columns'])->toBe(['Organismo', 'Forma']);
    expect(ExamParameter::where('code', 'resultado_textual')->sole()->options)->toBe(['Opción A', 'Opción B']);
});

it('builds a result form definition from active HECES sections and parameters', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $exam = Exam::where('code', 'HECES')->sole();

    $definition = app(BuildExamResultFormDefinition::class)->handle($exam);

    expect($definition['exam'])->toBe(['code' => 'HECES', 'name' => 'EXÁMEN DE HECES']);
    expect(array_column($definition['sections'], 'code'))->toBe(['macroscopico', 'microscopico', 'parasitologico']);
    expect(array_map(fn (array $section): int => count($section['parameters']), $definition['sections']))->toBe([6, 9, 11]);
    expect($definition['sections'][0]['parameters'][0])
        ->toMatchArray([
            'code' => 'color',
            'name' => 'Color',
            'type' => 'text',
            'unit' => null,
            'reference' => null,
            'clinical_validation_status' => 'pending',
            'order' => 10,
        ]);
    expect($definition['sections'][2]['settings']['columns'])
        ->toBe(['Organismo', 'Quiste', 'Trofozoíto', 'Huevo', 'Larva']);
});

it('does not build a result form definition for an inactive exam', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $exam = Exam::where('code', 'HECES')->sole();
    $exam->update(['is_active' => false]);

    expect(fn () => app(BuildExamResultFormDefinition::class)->handle($exam->fresh()))
        ->toThrow(ValidationException::class);
});

it('preserves previously published catalog snapshots when the editable catalog changes', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $administrator = phaseTwoUser('Administrador');
    $exam = Exam::where('code', 'HECES')->sole();
    $parameter = $exam->sections()->where('code', 'macroscopico')->firstOrFail()->parameters()->where('code', 'color')->firstOrFail();

    $firstVersion = app(PublishExamCatalogVersion::class)->handle($exam, $administrator);
    $parameter->update(['name' => 'Color modificado para prueba']);
    $secondVersion = app(PublishExamCatalogVersion::class)->handle($exam->fresh(), $administrator);

    expect($firstVersion->version_number)->toBe(1);
    expect($secondVersion->version_number)->toBe(2);
    expect($firstVersion->fresh()->snapshot['sections'][0]['parameters'][0]['name'])->toBe('Color');
    expect($secondVersion->snapshot['sections'][0]['parameters'][0]['name'])->toBe('Color modificado para prueba');
    expect(AuditLog::where('entity_type', ExamCatalogVersion::class)->where('action', 'created')->count())->toBe(2);
    expect(fn () => $firstVersion->update(['version_number' => 3]))
        ->toThrow(LogicException::class, 'Published exam catalog versions are immutable.');
});

it('rejects catalog publication without update permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
    $exam = Exam::where('code', 'HECES')->sole();
    $validator = phaseTwoUser('Validador');

    expect(fn () => app(PublishExamCatalogVersion::class)->handle($exam, $validator))
        ->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('exam_catalog_versions', 0);
});
