<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\RenderValidatedOrderPdf;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Models\Exam;
use App\Models\Order;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\CualitativosCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

it('reuses the generated PDF while the report is unchanged and regenerates it when it changes', function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CualitativosCatalogSeeder::class);
    $administrator = User::factory()->create();
    $administrator->assignRole('Administrador');
    $validator = User::factory()->create();
    $validator->assignRole('Validador');
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [Exam::where('code', 'VIH')->sole()->id], $administrator);
    app(SaveOrderExamResultDraft::class)->handle($order->orderExams->sole(), ['values' => ['vih' => ['value' => 'No reactivo']]], $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);
    $report = app(BuildValidatedOrderReportData::class)->handle(Order::findOrFail($order->id), $validator);
    $renderer = app(RenderValidatedOrderPdf::class);

    $first = $renderer->cachedOutput($report);
    $second = $renderer->cachedOutput([...$report, 'generated_at' => '01/01/2030 00:00']);

    expect($first)->toStartWith('%PDF-')
        ->and($second)->toBe($first)
        ->and(Storage::disk('local')->files(RenderValidatedOrderPdf::CACHE_DIRECTORY))->toHaveCount(1);

    $changed = $report;
    $changed['patient']['first_names'] = 'Nombre corregido';
    $renderer->cachedOutput($changed);

    expect(Storage::disk('local')->files(RenderValidatedOrderPdf::CACHE_DIRECTORY))->toHaveCount(2);
});
