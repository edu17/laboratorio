<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\PublishOrderReport;
use App\Actions\Orders\ReopenLabOrder;
use App\Actions\Orders\RevokePublishedReport;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\HecesCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
});

function phaseSevenUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function phaseSevenValidatedOrder(User $administrator, User $validator): Order
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
                        $cells[Str::slug($column, '_')] = 'Ficticio';
                    }

                    $values[$parameter['code']] = ['cells' => $cells];

                    continue;
                }

                $values[$parameter['code']] = ['value' => 'Resultado ficticio'];
            }

            if ($isMatrix && is_string($section['code'] ?? null)) {
                $sectionStatuses[$section['code']] = OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS;
            }
        }

        app(SaveOrderExamResultDraft::class)->handle($orderExam, [
            'values' => $values,
            'section_statuses' => $sectionStatuses,
        ], $administrator);
    }

    return app(ValidateLabOrder::class)->handle($order->fresh(), $validator);
}

it('publishes a validated report with a random bearer QR link and no token in audit metadata', function (): void {
    $administrator = phaseSevenUser('Administrador');
    $validator = phaseSevenUser('Validador');
    $order = phaseSevenValidatedOrder($administrator, $validator);

    $accessToken = app(PublishOrderReport::class)->handle($order, $validator);

    expect($accessToken->token_encrypted)->toMatch('/\A[A-Za-z0-9]{64}\z/')
        ->and($accessToken->token_hash)->toBe(hash('sha256', $accessToken->token_encrypted))
        ->and($accessToken->validation_version)->toBe(1)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PUBLISHED)
        ->and($accessToken->toArray())->not->toHaveKey('token_encrypted')
        ->and($accessToken->toArray())->not->toHaveKey('token_hash');

    $this->assertDatabaseHas('report_access_tokens', [
        'id' => $accessToken->getKey(),
        'order_id' => $order->getKey(),
        'token_hash' => hash('sha256', $accessToken->token_encrypted),
    ]);

    $audit = AuditLog::query()->where('action', 'report_published')->sole();
    expect(json_encode($audit->metadata))->not->toContain($accessToken->token_encrypted);

    $report = app(BuildValidatedOrderReportData::class)->handle($order->fresh(), $validator);
    $html = view('reports.order', ['report' => $report])->render();

    expect($report['public_access']['qr_code'])->toStartWith('data:image/svg+xml;base64,');
    expect($html)
        ->toContain('class="qr-corner"')
        ->toContain('alt="QR para consultar el reporte"')
        ->toContain('Escanea para consultar')
        ->toContain('class="results paired-results"');
});

it('lets anyone with the QR bearer link view and download the published PDF', function (): void {
    $administrator = phaseSevenUser('Administrador');
    $validator = phaseSevenUser('Validador');
    $order = phaseSevenValidatedOrder($administrator, $validator);
    $accessToken = app(PublishOrderReport::class)->handle($order, $validator);
    $url = route('reports.public', ['token' => $accessToken->token_encrypted]);

    auth()->logout();

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=reporte-'.$order->number.'-v1.pdf')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertSee('%PDF-', false);

    $this->get($url.'?download=1')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=reporte-'.$order->number.'-v1.pdf')
        ->assertSee('%PDF-', false);
});

it('hides malformed, unknown, revoked, and expired public links with the same 404 response', function (): void {
    $administrator = phaseSevenUser('Administrador');
    $validator = phaseSevenUser('Validador');
    $order = phaseSevenValidatedOrder($administrator, $validator);
    $accessToken = app(PublishOrderReport::class)->handle($order, $validator);
    $validUrl = route('reports.public', ['token' => $accessToken->token_encrypted]);

    $this->get('/consulta/invalid-token')->assertNotFound();
    $this->get(route('reports.public', ['token' => str_repeat('a', 64)]))->assertNotFound();

    $accessToken->update(['expires_at' => now()->subMinute()]);
    $this->get($validUrl)->assertNotFound();

    $accessToken->update(['expires_at' => null, 'revoked_at' => now(), 'revoked_by' => $validator->getKey()]);
    $this->get($validUrl)->assertNotFound();
});

it('revokes a published link when a validator explicitly revokes publication', function (): void {
    $administrator = phaseSevenUser('Administrador');
    $validator = phaseSevenUser('Validador');
    $order = phaseSevenValidatedOrder($administrator, $validator);
    $accessToken = app(PublishOrderReport::class)->handle($order, $validator);

    app(RevokePublishedReport::class)->handle($order, $validator);

    expect($order->fresh()->status)->toBe(Order::STATUS_VALIDATED)
        ->and($accessToken->fresh()->revoked_by)->toBe($validator->getKey())
        ->and($accessToken->fresh()->revoked_at)->not->toBeNull();

    $this->get(route('reports.public', ['token' => $accessToken->token_encrypted]))->assertNotFound();
});

it('automatically revokes a published link when the validator reopens the order', function (): void {
    $administrator = phaseSevenUser('Administrador');
    $validator = phaseSevenUser('Validador');
    $order = phaseSevenValidatedOrder($administrator, $validator);
    $accessToken = app(PublishOrderReport::class)->handle($order, $validator);

    app(ReopenLabOrder::class)->handle($order, 'Corrección ficticia', $validator);

    expect($order->fresh()->status)->toBe(Order::STATUS_IN_PROGRESS)
        ->and($accessToken->fresh()->revoked_by)->toBe($validator->getKey())
        ->and($accessToken->fresh()->revoked_at)->not->toBeNull();

    $this->get(route('reports.public', ['token' => $accessToken->token_encrypted]))->assertNotFound();
});

it('restricts publication actions to validators with the report permissions', function (): void {
    $administrator = phaseSevenUser('Administrador');
    $validator = phaseSevenUser('Validador');
    $order = phaseSevenValidatedOrder($administrator, $validator);

    expect($administrator->can('publishReport', $order))->toBeFalse()
        ->and($administrator->can('revokeReport', $order->forceFill(['status' => Order::STATUS_PUBLISHED])))->toBeFalse()
        ->and(Permission::findByName('Publish:Report', 'web')->roles->pluck('name')->all())->toContain('Validador');

    $this->actingAs($administrator)->get("/admin/ordenes/{$order->getKey()}")
        ->assertOk()
        ->assertDontSee('Publicar y generar QR');

    Livewire::actingAs($validator)
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->assertSee('Publicar y generar QR')
        ->callAction('publishReport')
        ->assertRedirect(route('orders.report', ['order' => $order]));

    expect($order->fresh()->status)->toBe(Order::STATUS_PUBLISHED);

    Livewire::actingAs($validator)
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->assertSee('Revocar acceso QR')
        ->callAction('revokeReport');

    expect($order->fresh()->status)->toBe(Order::STATUS_VALIDATED);
});
