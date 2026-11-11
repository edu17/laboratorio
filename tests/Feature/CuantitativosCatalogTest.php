<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\ResolveParameterReference;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\Order;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\CuantitativosCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CuantitativosCatalogSeeder::class);
});

it('seeds the quantitative panels idempotently with valid structured references', function () {
    $examCount = Exam::count();
    $parameterCount = ExamParameter::count();

    $this->seed(CuantitativosCatalogSeeder::class);

    expect(Exam::count())->toBe($examCount)
        ->and(ExamParameter::count())->toBe($parameterCount)
        ->and(Exam::where('code', 'QUIMICA')->value('allows_partial_request'))->toBeTrue()
        ->and(Exam::where('code', 'VITAMINA_D')->sole()->sections()->sole()->parameters()->sole()->clinical_validation_status)->toBe('pending');

    ExamParameter::all()->each(function (ExamParameter $parameter): void {
        $config = $parameter->reference_config ?? [];

        expect(array_diff(array_keys($config), ResolveParameterReference::ALLOWED_CONFIG_KEYS))->toBe([], $parameter->code);

        collect($config)
            ->filter(fn (mixed $value, string $key): bool => preg_match('/\A(min|max)(_|\z)/', $key) === 1)
            ->each(fn (mixed $value) => expect(is_numeric($value))->toBeTrue($parameter->code));

        expect(filled($parameter->reference_text) || filled($config['texto_femenino'] ?? null) || filled($config['texto_masculino'] ?? null))
            ->toBeTrue("{$parameter->code} no tiene referencia candidata");
    });
});

it('reports only creatinine from the chemistry panel with the printed male range and flag', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('Administrador');
    $validator = User::factory()->create();
    $validator->assignRole('Validador');
    $chemistry = Exam::where('code', 'QUIMICA')->sole();
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(['sex' => 'masculino']), [$chemistry->id], $administrator, requestedParameterCodes: [$chemistry->id => ['creatinina', 'glucosa_pre']]);

    app(SaveOrderExamResultDraft::class)->handle($order->orderExams->sole(), ['values' => [
        'creatinina' => ['value' => '1.3'],
        'glucosa_pre' => ['value' => '150'],
    ]], $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    $rows = collect(app(BuildValidatedOrderReportData::class)->handle(Order::findOrFail($order->id), $validator)['exams'][0]['sections'])
        ->flatMap(fn (array $section): array => $section['rows'])
        ->keyBy('name');

    expect($rows->keys()->all())->toBe(['Glucosa pre', 'Creatinina'])
        ->and($rows['Creatinina']['reference'])->toBe('Hombres: 0.60 - 1.10 mg/dL')
        ->and($rows['Creatinina']['flag'])->toBe(ResolveParameterReference::FLAG_HIGH)
        ->and($rows['Glucosa pre']['reference'])->toBe('')
        ->and($rows['Glucosa pre']['flag'])->toBeNull();
});
