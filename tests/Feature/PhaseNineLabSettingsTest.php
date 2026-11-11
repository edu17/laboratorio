<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Actions\Settings\UpdateLabProfile;
use App\Filament\Pages\LabSettings;
use App\Models\Exam;
use App\Models\LabProfile;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\HecesCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
});

function phaseNineUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function phaseNineProfileValues(LabProfile $profile, array $overrides = []): array
{
    return array_merge($profile->only([
        'institution_name',
        'subtitle',
        'address',
        'phone',
        'email',
        'sanitary_registration',
        'emergency_phone',
        'opening_hours',
        'logo_path',
        'technical_signature_path',
        'biologist_signature_path',
        'institutional_seal_path',
        'order_prefix',
        'order_number_padding',
    ]), $overrides);
}

function phaseNineValidatedOrder(User $administrator, User $validator): Order
{
    $order = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(['first_names' => 'Paciente Ficticio', 'last_names' => 'Prueba']),
        [Exam::query()->where('code', 'HECES')->value('id')],
        $administrator,
    );

    foreach ($order->orderExams as $orderExam) {
        $values = [];
        $sectionStatuses = [];

        foreach ($orderExam->catalog_snapshot['sections'] ?? [] as $section) {
            $isMatrix = ($section['structure'] ?? null) === 'parasitological_matrix';

            foreach ($section['parameters'] ?? [] as $parameter) {
                if ($isMatrix) {
                    $cells = [];

                    foreach (array_slice($section['settings']['columns'] ?? [], 1) as $column) {
                        $cells[Str::slug($column, '_')] = null;
                    }

                    $values[$parameter['code']] = ['cells' => $cells];

                    continue;
                }

                $values[$parameter['code']] = ['value' => 'Resultado ficticio'];
            }

            if ($isMatrix && is_string($section['code'] ?? null)) {
                $sectionStatuses[$section['code']] = OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS;
            }
        }

        app(SaveOrderExamResultDraft::class)->handle($orderExam, [
            'values' => $values,
            'section_statuses' => $sectionStatuses,
        ], $administrator);
    }

    return app(ValidateLabOrder::class)->handle($order->fresh(), $validator);
}

it('lets only administrators view and save versioned laboratory settings', function (): void {
    $administrator = phaseNineUser('Administrador');
    $technician = phaseNineUser('Técnico');
    $validator = phaseNineUser('Validador');
    $currentProfile = LabProfile::query()->where('is_active', true)->firstOrFail();

    $this->actingAs($technician)->get(LabSettings::getUrl())->assertForbidden();
    $this->actingAs($validator)->get(LabSettings::getUrl())->assertForbidden();

    Livewire::actingAs($administrator)
        ->test(LabSettings::class)
        ->assertSee('Firma del técnico laboratorista')
        ->assertSee('Firma de la química bióloga')
        ->assertSee('Sello institucional')
        ->assertSee('Correlativo de órdenes')
        ->fillForm([
            'institution_name' => 'Laboratorio Ficticio Norte',
            'subtitle' => 'Reporte de prueba',
            'address' => 'Dirección ficticia 123',
            'phone' => '555-0101',
            'email' => 'contacto@example.test',
            'logo_path' => null,
            'technical_signature_path' => null,
            'biologist_signature_path' => null,
            'institutional_seal_path' => null,
            'order_prefix' => 'NORTE',
            'order_number_padding' => 4,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Configuración guardada');

    $activeProfile = LabProfile::query()->where('is_active', true)->firstOrFail();

    expect($activeProfile->version)->toBe($currentProfile->version + 1)
        ->and($activeProfile->institution_name)->toBe('Laboratorio Ficticio Norte')
        ->and($activeProfile->order_prefix)->toBe('NORTE')
        ->and($activeProfile->order_number_padding)->toBe(4)
        ->and($currentProfile->fresh()->is_active)->toBeFalse();
});

it('uses the configured order prefix and padding only for new orders', function (): void {
    $administrator = phaseNineUser('Administrador');
    $exam = Exam::query()->where('code', 'HECES')->firstOrFail();
    $patient = Patient::factory()->create();
    $firstOrder = app(CreateLabOrder::class)->handle($patient, [$exam->getKey()], $administrator);
    $profile = LabProfile::query()->where('is_active', true)->firstOrFail();
    app(UpdateLabProfile::class)->handle(
        phaseNineProfileValues($profile, ['order_prefix' => 'CLINICA', 'order_number_padding' => 4]),
        $administrator,
    );

    $secondOrder = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(),
        [$exam->getKey()],
        $administrator,
    );

    expect($firstOrder->number)->toBe('LAB-'.now()->format('Y').'-000001')
        ->and($secondOrder->number)->toBe('CLINICA-'.now()->format('Y').'-0002');
});

it('rejects report image paths outside the private upload folders', function (string $field, string $path): void {
    $administrator = phaseNineUser('Administrador');
    $profile = LabProfile::query()->where('is_active', true)->firstOrFail();

    expect(fn () => app(UpdateLabProfile::class)->handle(
        phaseNineProfileValues($profile, [$field => $path]),
        $administrator,
    ))->toThrow(ValidationException::class);
})->with([
    'logo path' => ['logo_path', '../users/private.png'],
    'signature path' => ['technical_signature_path', 'public/signature.png'],
    'seal extension' => ['institutional_seal_path', 'lab-report-assets/seal.svg'],
]);

it('keeps the validated report on its original laboratory profile after settings change', function (): void {
    Storage::fake('local');
    $administrator = phaseNineUser('Administrador');
    $validator = phaseNineUser('Validador');
    $logoPath = UploadedFile::fake()->image('laboratory-logo.png')->store('lab-profile-logos', 'local');
    $technicalSignaturePath = UploadedFile::fake()->image('technical-signature.png')->store('lab-report-assets', 'local');
    $biologistSignaturePath = UploadedFile::fake()->image('biologist-signature.png')->store('lab-report-assets', 'local');
    $institutionalSealPath = UploadedFile::fake()->image('institutional-seal.png')->store('lab-report-assets', 'local');
    $profile = LabProfile::query()->where('is_active', true)->firstOrFail();
    app(UpdateLabProfile::class)->handle(
        phaseNineProfileValues($profile, [
            'institution_name' => 'Perfil Histórico Ficticio',
            'address' => 'Domicilio histórico ficticio',
            'phone' => '555-0102',
            'email' => 'historico@example.test',
            'sanitary_registration' => 'DRACES-FICTICIO',
            'emergency_phone' => '555-0199',
            'opening_hours' => 'Horario ficticio 08:00 a 17:00',
            'logo_path' => $logoPath,
            'technical_signature_path' => $technicalSignaturePath,
            'biologist_signature_path' => $biologistSignaturePath,
            'institutional_seal_path' => $institutionalSealPath,
        ]),
        $administrator,
    );
    $order = phaseNineValidatedOrder($administrator, $validator);
    $profileAtValidation = LabProfile::query()->where('is_active', true)->firstOrFail();
    app(UpdateLabProfile::class)->handle(
        phaseNineProfileValues($profileAtValidation, [
            'institution_name' => 'Perfil Nuevo Ficticio',
            'address' => 'Domicilio nuevo ficticio',
            'phone' => '555-0103',
            'email' => 'nuevo@example.test',
            'sanitary_registration' => 'DRACES-NUEVO',
            'emergency_phone' => '555-0200',
            'opening_hours' => 'Horario posterior',
            'logo_path' => null,
            'technical_signature_path' => null,
            'biologist_signature_path' => null,
            'institutional_seal_path' => null,
        ]),
        $administrator,
    );

    $report = app(BuildValidatedOrderReportData::class)->handle($order->fresh(), $validator);
    $html = view('reports.order', ['report' => $report])->render();

    expect($report['institution']['name'])->toBe('Perfil Histórico Ficticio')
        ->and($report['institution']['address'])->toBe('Domicilio histórico ficticio')
        ->and($report['institution']['phone'])->toBe('555-0102')
        ->and($report['institution']['sanitary_registration'])->toBe('DRACES-FICTICIO')
        ->and($report['institution']['emergency_phone'])->toBe('555-0199')
        ->and($report['institution']['opening_hours'])->toBe('Horario ficticio 08:00 a 17:00')
        ->and($html)->toContain('DRACES-FICTICIO', '555-0199', 'Horario ficticio 08:00 a 17:00')
        ->and($report['institution']['logo_data_uri'])->toStartWith('data:image/png;base64,')
        ->and($report['institution']['technical_signature_data_uri'])->toStartWith('data:image/png;base64,')
        ->and($report['institution']['biologist_signature_data_uri'])->toStartWith('data:image/png;base64,')
        ->and($report['institution']['institutional_seal_data_uri'])->toStartWith('data:image/png;base64,')
        ->and($html)->toContain('Perfil Histórico Ficticio')
        ->and($html)->toContain('alt="Firma del técnico laboratorista"')
        ->and($html)->toContain('alt="Firma de la química bióloga"')
        ->and($html)->toContain('alt="Sello institucional"')
        ->and($html)->not->toContain('Perfil Nuevo Ficticio');

    $this->actingAs($validator)
        ->get(route('orders.report', $order))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertSee('%PDF-', false);
});
