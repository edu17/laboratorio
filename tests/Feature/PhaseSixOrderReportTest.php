<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\PublishOrderReport;
use App\Actions\Orders\RenderValidatedOrderPdf;
use App\Actions\Orders\ReopenLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('fits a complete HECES report with QR signatures and contact details on one letter page', function (bool $withFindings): void {
    Storage::fake('local');
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    $profile = LabProfile::query()->where('is_active', true)->firstOrFail();
    $profile->update([
        'address' => 'Dirección ficticia de prueba, zona 3, frente al centro de salud.',
        'sanitary_registration' => 'DRACES-FICTICIO',
        'emergency_phone' => '555-0100',
        'opening_hours' => 'Lunes a domingo, 24 horas (prueba)',
        'logo_path' => UploadedFile::fake()->image('logo.png', 400, 400)->store('lab-profile-logos', 'local'),
        'technical_signature_path' => UploadedFile::fake()->image('technical.png', 800, 250)->store('lab-report-assets', 'local'),
        'biologist_signature_path' => UploadedFile::fake()->image('biologist.png', 800, 250)->store('lab-report-assets', 'local'),
        'institutional_seal_path' => UploadedFile::fake()->image('seal.png', 400, 400)->store('lab-report-assets', 'local'),
    ]);
    $order = phaseSixValidatedOrder($administrator, $validator, matrixHasFindings: $withFindings);
    app(PublishOrderReport::class)->handle($order, $validator);
    $report = app(BuildValidatedOrderReportData::class)->handle($order->fresh(), $validator);

    $pdf = app(RenderValidatedOrderPdf::class)->handle($report);
    $footerBoxes = [];
    $bodyBoxes = [];
    $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$footerBoxes, &$bodyBoxes): void {
        $node = $frame->get_node();
        if ($node instanceof DOMElement && in_array('report-footer', explode(' ', $node->getAttribute('class')), true)) {
            $footerBoxes[] = $frame->get_border_box();
        }
        if ($node instanceof DOMElement && $node->getAttribute('class') === 'report-body') {
            $bodyBoxes[] = $frame->get_border_box();
        }
    }]]);
    $bytes = $pdf->output();

    expect($bytes)->toStartWith('%PDF-');
    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
    expect($footerBoxes)->toHaveCount(1);
    expect($bodyBoxes)->toHaveCount(1);
    expect($footerBoxes[0]['y'] + $footerBoxes[0]['h'])->toBeBetween(748, 751);
    expect($bodyBoxes[0]['y'] + $bodyBoxes[0]['h'])->toBeLessThan($footerBoxes[0]['y']);
})->with(['matrix with findings' => true, 'matrix without findings' => false]);

it('anchors the footer on every page and reserves its measured height for longer reports', function (): void {
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    LabProfile::query()->where('is_active', true)->firstOrFail()->update([
        'address' => str_repeat('Dirección ficticia de prueba. ', 16),
        'opening_hours' => 'Horario ficticio de atención',
        'emergency_phone' => '555-0100',
    ]);
    $order = phaseSixValidatedOrder($administrator, $validator, matrixHasFindings: false);
    $report = app(BuildValidatedOrderReportData::class)->handle($order, $validator);
    $report['exams'] = [[
        'code' => 'DEMO_PAGINATION',
        'name' => 'Examen ficticio de paginación',
        'sections' => [[
            'name' => 'Observaciones de prueba',
            'is_matrix' => false,
            'rows' => array_map(fn (int $index): array => [
                'name' => 'Campo ficticio '.$index,
                'result' => str_repeat('Observación ficticia de prueba. ', 8),
                'unit' => '',
                'reference' => '',
            ], range(1, 8)),
        ]],
    ]];
    $pdf = app(RenderValidatedOrderPdf::class)->handle($report);
    $footers = [];
    $rows = [];
    $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$footers, &$rows): void {
        $node = $frame->get_node();
        if (! $node instanceof DOMElement) {
            return;
        }
        $page = $canvas->get_page_number();
        if (in_array('report-footer', explode(' ', $node->getAttribute('class')), true)) {
            $footers[$page] = $frame->get_border_box();
        }
        if ($node->tagName === 'tr') {
            for ($parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
                if ($parent->tagName === 'table' && in_array('results', explode(' ', $parent->getAttribute('class')), true)) {
                    $rows[$page][] = $frame->get_border_box();
                    break;
                }
            }
        }
    }]]);

    $pdf->output();

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBeGreaterThan(1);
    expect($footers)->toHaveCount($pdf->getDomPDF()->getCanvas()->get_page_count());
    foreach ($footers as $page => $footer) {
        expect($footer['y'] + $footer['h'])->toBeBetween(748, 751);
        foreach ($rows[$page] ?? [] as $row) {
            expect($row['y'] + $row['h'])->toBeLessThan($footer['y']);
        }
    }
});

it('renders the shared header body and footer for other exam types and configurable matrices', function (int $columnCount): void {
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    $tabular = Exam::factory()->create(['code' => 'DEMO_TABULAR', 'name' => 'Comparación ficticia distinta de HECES']);
    $section = ExamSection::factory()->create(['exam_id' => $tabular->id]);
    foreach (['number', 'text', 'select', 'positive_negative', 'observation'] as $index => $type) {
        ExamParameter::factory()->create([
            'exam_section_id' => $section->id,
            'code' => 'demo_'.$type,
            'result_type' => $type,
            'options' => in_array($type, ['select', 'positive_negative'], true) ? ['Opción ficticia'] : null,
            'unit' => $type === 'number' ? 'unidad ficticia' : null,
            'reference_text' => $type === 'number' ? 'Referencia de prueba' : null,
            'clinical_validation_status' => 'validated',
            'sort_order' => $index,
        ]);
    }
    $matrixExam = Exam::factory()->create(['code' => 'DEMO_MATRIX']);
    $columns = ['Organismo ficticio', ...array_map(fn (int $index): string => 'Columna '.$index, range(1, $columnCount - 1))];
    $matrixSection = ExamSection::factory()->create([
        'exam_id' => $matrixExam->id,
        'code' => 'demo_matrix',
        'structure' => 'parasitological_matrix',
        'settings' => ['columns' => $columns],
    ]);
    ExamParameter::factory()->create(['exam_section_id' => $matrixSection->id, 'code' => 'demo_organism', 'result_type' => 'matrix_row']);
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$tabular->id, $matrixExam->id], $administrator);
    foreach ($order->orderExams as $orderExam) {
        $isMatrix = $orderExam->exam_id === $matrixExam->id;
        $values = $isMatrix
            ? ['demo_organism' => ['cells' => array_fill_keys(array_map(fn (string $column): string => Str::slug($column, '_'), array_slice($columns, 1)), 'Dato ficticio')]]
            : [
                'demo_number' => ['value' => '12.5'],
                'demo_text' => ['value' => 'Texto ficticio'],
                'demo_select' => ['value' => 'Opción ficticia'],
                'demo_positive_negative' => ['value' => 'Opción ficticia'],
                'demo_observation' => ['value' => 'Observación ficticia <sin interpretar>'],
            ];
        app(SaveOrderExamResultDraft::class)->handle($orderExam, [
            'values' => $values,
            'section_statuses' => $isMatrix ? ['demo_matrix' => OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS] : [],
        ], $administrator);
    }
    $order = app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    $report = app(BuildValidatedOrderReportData::class)->handle($order, $validator);
    $html = view('reports.order', ['report' => $report])->render();
    $dom = new DOMDocument;
    $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    $pdf = app(RenderValidatedOrderPdf::class)->handle($report);
    $bytes = $pdf->output();

    expect($xpath->query('//header[@class="report-header"]//table[@class="details"]')->length)->toBe(1);
    expect($xpath->query('//main[@class="report-body"]//header[@class="exam-title"]')->length)->toBe(2);
    expect($xpath->query('//footer[contains(@class,"report-footer")]')->length)->toBe(1);
    expect($xpath->query('//table[contains(@class,"matrix")]/colgroup/col')->length)->toBe($columnCount);
    expect($report['exams'][0]['sections'][0]['rows'])->toHaveCount(5);
    expect($report['exams'][1]['sections'][0]['rows'][0]['cells'])->toHaveCount($columnCount - 1);
    expect($html)->toContain('12.5', 'unidad ficticia', 'Referencia de prueba', 'Observación ficticia &lt;sin interpretar&gt;');
    expect($html)->not->toContain('class="results paired-results"', '<body class="compact-report">');
    expect($bytes)->toStartWith('%PDF-');
})->with(['two columns' => 2, 'six columns' => 6]);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HecesCatalogSeeder::class);
});

function phaseSixUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function phaseSixOrder(User $administrator, string $patientFirstName = 'Paciente Ficticio'): Order
{
    return app(CreateLabOrder::class)->handle(
        Patient::factory()->create([
            'first_names' => $patientFirstName,
            'last_names' => 'Prueba Clínica',
            'birth_date' => '2000-01-01',
        ]),
        [Exam::query()->where('code', 'HECES')->value('id')],
        $administrator,
        referringDoctor: 'Dra. Ficticia <Referencia>',
    );
}

function phaseSixValidatedOrder(
    User $administrator,
    User $validator,
    string $patientFirstName = 'Paciente Ficticio',
    bool $matrixHasFindings = true,
): Order {
    $order = phaseSixOrder($administrator, $patientFirstName);

    foreach ($order->orderExams as $orderExam) {
        $values = [];
        $sectionStatuses = [];

        foreach ($orderExam->catalog_snapshot['sections'] ?? [] as $section) {
            $isMatrix = ($section['structure'] ?? null) === 'parasitological_matrix';
            $columns = $isMatrix ? array_slice($section['settings']['columns'] ?? [], 1) : [];

            foreach ($section['parameters'] ?? [] as $parameter) {
                if ($isMatrix) {
                    $cells = [];

                    foreach ($columns as $column) {
                        $cells[Str::slug($column, '_')] = $matrixHasFindings ? 'Ficticio' : null;
                    }

                    $values[$parameter['code']] = ['cells' => $cells];

                    continue;
                }

                $values[$parameter['code']] = ['value' => 'Resultado ficticio'];
            }

            if ($isMatrix && is_string($section['code'] ?? null)) {
                $sectionStatuses[$section['code']] = $matrixHasFindings
                    ? OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS
                    : OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS;
            }
        }

        app(SaveOrderExamResultDraft::class)->handle($orderExam, [
            'values' => $values,
            'section_statuses' => $sectionStatuses,
        ], $administrator);
    }

    return app(ValidateLabOrder::class)->handle($order->fresh(), $validator);
}

it('streams and downloads a PDF only for an authorized user with a current validated snapshot', function (): void {
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    $technician = phaseSixUser('Técnico');
    $order = phaseSixValidatedOrder($administrator, $validator);

    $this->actingAs($validator)
        ->get(route('orders.report', $order))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertHeader('Content-Disposition', 'inline; filename=reporte-'.$order->number.'-v1.pdf')
        ->assertSee('%PDF-', false);

    $this->actingAs($administrator)
        ->get(route('orders.report', ['order' => $order, 'download' => 1]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=reporte-'.$order->number.'-v1.pdf');

    $this->actingAs($technician)->get(route('orders.report', $order))->assertForbidden();
});

it('requires authentication and blocks drafts and reopened orders from report generation', function (): void {
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    $order = phaseSixOrder($administrator);

    $this->get(route('orders.report', $order))->assertRedirect('/admin/login');
    $this->actingAs($validator)->get(route('orders.report', $order))->assertForbidden();

    $validated = phaseSixValidatedOrder($administrator, $validator);
    app(ReopenLabOrder::class)->handle($validated, 'Corrección ficticia', $validator);

    $this->actingAs($validator)->get(route('orders.report', $validated))->assertForbidden();
});

it('builds the report from the immutable validation snapshot and escapes its contents', function (): void {
    $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    $order = phaseSixValidatedOrder($administrator, $validator, '<script>alert(1)</script> Ficticio');
    $snapshot = $order->validationEvents()->where('version', 1)->sole()->results_snapshot;

    $order->patient()->update(['first_names' => 'Cambio posterior']);
    $order->update(['referring_doctor' => 'Médico posterior']);
    $order->orderExams->sole()->resultValues()->where('parameter_code', 'color')->update([
        'value' => ['value' => 'Resultado posterior'],
    ]);

    $report = app(BuildValidatedOrderReportData::class)->handle($order->fresh(), $validator);
    $html = view('reports.order', ['report' => $report])->render();

    expect($report['patient']['first_names'])->toBe($snapshot['patient']['first_names'])
        ->and($report['patient']['age_display'])->toBe('26 años')
        ->and($report['order']['referring_doctor'])->toBe('Dra. Ficticia <Referencia>')
        ->and($html)->toContain('Dra. Ficticia &lt;Referencia&gt;')
        ->and($report['patient']['first_names'])->toContain('<script>alert(1)</script>')
        ->and($report['exams'][0]['sections'][0]['rows'][0]['result'])->toBe('Resultado ficticio')
        ->and($html)->toContain('class="exam-title"')
        ->and($html)->toContain('class="results paired-results"')
        ->and($html)->toContain('class="validated-state">Validado</span>')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('Resultado posterior')
        ->and($report['exams'][0]['sections'][0]['rows'][0]['reference'])->toBe('');
});

it('renders the explicit no-findings state instead of a blank organism table in the PDF', function (): void {
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    $order = phaseSixValidatedOrder($administrator, $validator, matrixHasFindings: false);
    $report = app(BuildValidatedOrderReportData::class)->handle($order, $validator);
    $matrix = collect($report['exams'][0]['sections'])->firstWhere('is_matrix', true);
    $html = view('reports.order', ['report' => $report])->render();

    expect($matrix['status'])->toBe(OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS)
        ->and($matrix['rows'])->toBe([])
        ->and($html)->toContain('Completada sin hallazgos.')
        ->and($html)->not->toContain('Entamoeba histolytica');

    $this->actingAs($validator)
        ->get(route('orders.report', $order))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertSee('%PDF-', false);
});

it('renders the report actions only when the order has a validated version', function (): void {
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    $order = phaseSixOrder($administrator);

    $this->actingAs($validator)
        ->get("/admin/ordenes/{$order->getKey()}")
        ->assertOk()
        ->assertDontSee('Ver reporte PDF')
        ->assertDontSee('Descargar PDF');

    $validated = phaseSixValidatedOrder($administrator, $validator);

    $this->get("/admin/ordenes/{$validated->getKey()}")
        ->assertOk()
        ->assertSee('Ver reporte PDF')
        ->assertSee('Descargar PDF');
});

it('validates the order from the confirmation action and refreshes its view', function (): void {
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    $order = phaseSixOrder($administrator);

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

    Livewire::actingAs($validator)
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('validateResults')
        ->assertRedirect(OrderResource::getUrl('view', ['record' => $order]));

    expect($order->fresh()->status)->toBe(Order::STATUS_VALIDATED)
        ->and($order->fresh()->validation_version)->toBe(1);
});

it('explains which results prevent order validation instead of leaving the confirmation without feedback', function (): void {
    $administrator = phaseSixUser('Administrador');
    $validator = phaseSixUser('Validador');
    $order = phaseSixOrder($administrator);

    Livewire::actingAs($validator)
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('validateResults')
        ->assertNotified('No se pudo validar la orden');

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING)
        ->and($order->validationEvents()->count())->toBe(0);
});
