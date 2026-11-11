<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Models\Exam;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\OrinaCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrinaCatalogSeeder::class);
});

it('reports the three urine sections in the paired layout of the current format', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('Administrador');
    $validator = User::factory()->create();
    $validator->assignRole('Validador');
    $orina = Exam::where('code', 'ORINA')->sole();
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$orina->id], $administrator);
    $orderExam = $order->orderExams->sole();
    $values = collect($orderExam->requestedParameterCodes())
        ->mapWithKeys(fn (string $code): array => [$code => ['value' => 'Negativo']])
        ->put('sangre', ['value' => '200 Ery/μl Hemolysis'])
        ->all();

    app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => $values], $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    $sections = collect(app(BuildValidatedOrderReportData::class)->handle($order->fresh(), $validator)['exams'][0]['sections']);

    expect($sections->pluck('name')->all())->toBe(['Macroscópico', 'Químico', 'Microscópico'])
        ->and($sections->pluck('layout')->unique()->all())->toBe(['paired'])
        ->and($sections->sum(fn (array $section): int => count($section['rows'])))->toBe(19)
        ->and(collect($sections[1]['rows'])->firstWhere('name', 'Sangre')['result'])->toBe('200 Ery/μl Hemolysis');
});

it('reports a urine culture with its long antibiogram lists and the validated reference', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('Administrador');
    $validator = User::factory()->create();
    $validator->assignRole('Validador');
    $urocultivo = Exam::where('code', 'UROCULTIVO')->sole();
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$urocultivo->id], $administrator);
    $resistant = 'Ceftazidima, Amoxicilina/ácido clavulánico, Cefotaxima, Cefoxitin, Trimetoprim/sulfametoxazol, Ciprofloxacina, '
        .'AmpC (+): Cepa productora de Betalactamasas de tipo AmpC inducible, no se recomienda el uso de Ampicilina ni cefalosporinas '
        .'de 1ra, 2da y 3ra generación para su tratamiento.';

    app(SaveOrderExamResultDraft::class)->handle($order->orderExams->sole(), ['values' => [
        'cultivo_orina' => ['value' => 'Se aislaron más de 100,000 UFC/mL de Escherichia coli'],
        'susceptible' => ['value' => 'Cefepime, Imipenem, Fosfomicina'],
        'resistente' => ['value' => $resistant],
    ]], $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    $rows = collect(app(BuildValidatedOrderReportData::class)->handle($order->fresh(), $validator)['exams'][0]['sections'][0]['rows'])->keyBy('name');

    expect(mb_strlen($resistant))->toBeGreaterThan(255)
        ->and($rows->keys()->all())->toBe(['Cultivo de orina', 'Susceptible', 'Resistente'])
        ->and($rows['Cultivo de orina']['reference'])->toBe('Reporte microbiológico según hallazgo. Correlacionar con clínica del paciente.')
        ->and($rows['Resistente']['result'])->toBe($resistant);
});
