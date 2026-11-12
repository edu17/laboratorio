<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\RenderValidatedOrderPdf;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Models\Exam;
use App\Models\LabProfile;
use App\Models\Order;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\CualitativosCatalogSeeder;
use Database\Seeders\CuantitativosCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CualitativosCatalogSeeder::class);
    $this->seed(CuantitativosCatalogSeeder::class);
});

/**
 * @param  array<string, array<string, string>>  $valuesByExam  Código de examen => valores por parámetro.
 * @return array<string, mixed>
 */
function layoutReport(array $valuesByExam): array
{
    $administrator = User::factory()->create();
    $administrator->assignRole('Administrador');
    $validator = User::factory()->create();
    $validator->assignRole('Validador');
    $exams = Exam::whereIn('code', array_keys($valuesByExam))->get()->keyBy('code');
    $order = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(['first_names' => 'Paciente', 'last_names' => 'Ficticio de diseño', 'sex' => 'femenino']),
        $exams->pluck('id')->all(),
        $administrator,
    );

    foreach ($order->orderExams as $orderExam) {
        $code = $exams->firstWhere('id', $orderExam->exam_id)->code;
        $values = collect($valuesByExam[$code])->map(fn (string $value): array => ['value' => $value])->all();
        app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => $values], $administrator);
    }

    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    return app(BuildValidatedOrderReportData::class)->handle(Order::findOrFail($order->id), $validator);
}

it('repeats the patient header on every page and registers the page numbering', function () {
    $report = layoutReport(['FACTOR_REUMATOIDEO' => ['factor_reumatoideo' => '11.26']]);
    $report['exams'][0]['sections'][0]['rows'] = array_map(fn (int $index): array => [
        'name' => "Análisis ficticio {$index}",
        'result' => (string) $index,
        'unit' => 'IU/mL',
        'reference' => 'Hasta 15 IU/mL',
        'flag' => null,
    ], range(1, 60));

    $pdf = app(RenderValidatedOrderPdf::class)->handle($report);

    expect($pdf->getDomPDF()->getCallbacks()['end_document'] ?? [])->toHaveCount(1);

    $headerPages = [];
    $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$headerPages): void {
        $node = $frame->get_node();

        if ($node instanceof DOMElement && $node->getAttribute('class') === 'report-header') {
            $headerPages[$canvas->get_page_number()] = true;
        }
    }]]);
    $pdf->output();

    $pageCount = $pdf->getDomPDF()->getCanvas()->get_page_count();

    expect($pageCount)->toBeGreaterThan(1)
        ->and(array_keys($headerPages))->toBe(range(1, $pageCount));
});

it('shows qualitative results as cards and validated ranges as bars with their status', function () {
    $qualitative = view('reports.order', ['report' => layoutReport(['DENGUE' => ['dengue_igg' => 'Negativo', 'dengue_igm' => 'Positivo', 'dengue_ns1' => 'Negativo']])])->render();
    $flagged = view('reports.order', ['report' => layoutReport(['TIROIDEAS' => ['t3' => '1.05', 't4' => '13.4', 'tsh' => '1.98']])])->render();

    expect($qualitative)
        ->toContain('class="qualitative-cards"', '<td class="positive">', '1 positivo', 'Fin del reporte')
        ->not->toContain('Bajo · Normal · Alto', 'fuera de rango')
        ->and($flagged)
        ->toContain('Bajo · Normal · Alto', '<span class="chip chip-alert">▲ Alto</span>', '<span class="chip chip-normal">Normal</span>', '1 fuera de rango')
        ->and(substr_count($flagged, 'class="range-marker"'))->toBe(3);
});

it('places the range marker at the result position inside the normal zone', function () {
    $rows = collect(layoutReport(['TIROIDEAS' => ['t3' => '1.4', 't4' => '13.4', 'tsh' => '< 0.1']])['exams'][0]['sections'][0]['rows'])->keyBy('name');

    expect($rows['T3']['indicator'])->toBe(['zone_start' => 25.0, 'zone_width' => 50.0, 'position' => 50.0])
        ->and($rows['T4']['indicator']['position'])->toBe(86.7)
        ->and($rows['TSH']['indicator'])->toBeNull();
});

it('formats eight digit phone numbers in the footer', function () {
    LabProfile::query()->where('is_active', true)->firstOrFail()->update(['phone' => '56282580', 'emergency_phone' => '56282580']);

    $html = view('reports.order', ['report' => layoutReport(['VIH' => ['vih' => 'No reactivo']])])->render();

    expect($html)->toContain('Tel. 5628-2580')->not->toContain('56282580');
});

it('prints the exams marked for a separate page last, each on its own page', function () {
    $values = [
        'DENGUE' => ['dengue_igg' => 'Negativo', 'dengue_igm' => 'Negativo', 'dengue_ns1' => 'Negativo'],
        'FACTOR_REUMATOIDEO' => ['factor_reumatoideo' => '11.26'],
        'TIROIDEAS' => ['t3' => '1.05', 't4' => '13.4', 'tsh' => '1.98'],
    ];
    $pageCount = function (array $report): int {
        $pdf = app(RenderValidatedOrderPdf::class)->handle($report);
        $pdf->output();

        return $pdf->getDomPDF()->getCanvas()->get_page_count();
    };

    $shared = layoutReport($values);
    Exam::where('code', 'DENGUE')->update(['prints_on_separate_page' => true]);
    $separate = layoutReport($values);

    expect($pageCount($shared))->toBe(1)
        ->and(array_column($separate['exams'], 'code'))->toHaveCount(3)->and($separate['exams'][2]['code'])->toBe('DENGUE')
        ->and($pageCount($separate))->toBe(2);
});
