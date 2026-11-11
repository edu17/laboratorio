<?php

use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\Patient;
use App\Models\ResultValueRevision;
use App\Models\User;
use Database\Seeders\CuantitativosCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CuantitativosCatalogSeeder::class);
    $this->technician = User::factory()->create();
    $this->technician->assignRole('Administrador');
});

/**
 * @param  list<string>  $codes
 */
function derivedOrderExam(User $technician, string $examCode, array $codes): OrderExam
{
    $exam = Exam::where('code', $examCode)->sole();

    return app(CreateLabOrder::class)
        ->handle(Patient::factory()->create(), [$exam->id], $technician, requestedParameterCodes: [$exam->id => $codes])
        ->orderExams->sole();
}

/**
 * @param  array<string, string|null>  $values
 * @return array<string, mixed>
 */
function derivedSave(User $technician, OrderExam $orderExam, array $values): array
{
    return app(SaveOrderExamResultDraft::class)
        ->handle($orderExam->fresh(), ['values' => collect($values)->map(fn (?string $value): array => ['value' => $value])->all()], $technician)
        ->resultValues
        ->mapWithKeys(fn ($result): array => [$result->parameter_code => $result->value])
        ->all();
}

it('calculates indirect bilirubin and recalculates it while it keeps the calculated value', function () {
    $orderExam = derivedOrderExam($this->technician, 'HEPATICAS', ['bilirrubina_total', 'bilirrubina_directa', 'bilirrubina_indirecta']);

    $first = derivedSave($this->technician, $orderExam, ['bilirrubina_total' => '1.9', 'bilirrubina_directa' => '1.2', 'bilirrubina_indirecta' => null]);
    $second = derivedSave($this->technician, $orderExam, ['bilirrubina_total' => '2.0', 'bilirrubina_directa' => '1.2', 'bilirrubina_indirecta' => '0.7']);

    expect($first['bilirrubina_indirecta'])->toBe(['value' => '0.7', 'calculated' => true])
        ->and($second['bilirrubina_indirecta'])->toBe(['value' => '0.8', 'calculated' => true])
        ->and(AuditLog::where('action', 'results_draft_saved')->latest('id')->first()->metadata['calculated_parameters'])->toBe(['bilirrubina_indirecta']);
});

it('keeps a manual correction of a calculated value and records the revision', function () {
    $orderExam = derivedOrderExam($this->technician, 'HEPATICAS', ['bilirrubina_total', 'bilirrubina_directa', 'bilirrubina_indirecta']);

    derivedSave($this->technician, $orderExam, ['bilirrubina_total' => '1.9', 'bilirrubina_directa' => '1.2', 'bilirrubina_indirecta' => null]);
    derivedSave($this->technician, $orderExam, ['bilirrubina_total' => '1.9', 'bilirrubina_directa' => '1.2', 'bilirrubina_indirecta' => '0.65']);
    $values = derivedSave($this->technician, $orderExam, ['bilirrubina_total' => '2.5', 'bilirrubina_directa' => '1.2', 'bilirrubina_indirecta' => '0.65']);

    expect($values['bilirrubina_indirecta'])->toBe(['value' => '0.65'])
        ->and(ResultValueRevision::all()->pluck('previous_value')->contains(fn (array $previous): bool => $previous == ['value' => '0.7', 'calculated' => true]))->toBeTrue();
});

it('does not record a revision when the stored result only differs in key order', function () {
    $orderExam = derivedOrderExam($this->technician, 'HEPATICAS', ['bilirrubina_total', 'bilirrubina_directa', 'bilirrubina_indirecta']);
    $values = ['bilirrubina_total' => '1.9', 'bilirrubina_directa' => '1.2', 'bilirrubina_indirecta' => null];

    derivedSave($this->technician, $orderExam, $values);
    DB::table('result_values')
        ->where('order_exam_id', $orderExam->id)
        ->where('parameter_code', 'bilirrubina_indirecta')
        ->update(['value' => '{"calculated":true,"value":"0.7"}']);

    derivedSave($this->technician, $orderExam, [...$values, 'bilirrubina_indirecta' => '0.7']);

    expect(ResultValueRevision::count())->toBe(0);
});

it('calculates VLDL and LDL with Friedewald only up to 400 mg/dL of triglycerides', function (string $triglycerides, ?array $vldl, ?array $ldl) {
    $orderExam = derivedOrderExam($this->technician, 'QUIMICA', ['colesterol_total', 'colesterol_hdl', 'trigliceridos', 'colesterol_vldl', 'colesterol_ldl']);

    $values = derivedSave($this->technician, $orderExam, [
        'colesterol_total' => '200', 'colesterol_hdl' => '40', 'trigliceridos' => $triglycerides,
        'colesterol_vldl' => null, 'colesterol_ldl' => null,
    ]);

    expect($values['colesterol_vldl'])->toBe($vldl)
        ->and($values['colesterol_ldl'])->toBe($ldl);
})->with([
    'triglycerides 150' => ['150', ['value' => '30', 'calculated' => true], ['value' => '130', 'calculated' => true]],
    'triglycerides 400' => ['400', ['value' => '80', 'calculated' => true], ['value' => '80', 'calculated' => true]],
    'triglycerides 450 are not calculable' => ['450', ['value' => 'No calculable', 'calculated' => true], ['value' => 'No calculable', 'calculated' => true]],
    'triglycerides with comparator are not calculable' => ['> 1000', ['value' => 'No calculable', 'calculated' => true], ['value' => 'No calculable', 'calculated' => true]],
]);

it('replaces a stale calculated LDL with "No calculable" when triglycerides go above 400 mg/dL', function () {
    $orderExam = derivedOrderExam($this->technician, 'QUIMICA', ['colesterol_total', 'colesterol_hdl', 'trigliceridos', 'colesterol_ldl']);

    derivedSave($this->technician, $orderExam, ['colesterol_total' => '200', 'colesterol_hdl' => '40', 'trigliceridos' => '150', 'colesterol_ldl' => null]);
    $values = derivedSave($this->technician, $orderExam, ['colesterol_total' => '200', 'colesterol_hdl' => '40', 'trigliceridos' => '450', 'colesterol_ldl' => '130']);

    expect($values['colesterol_ldl'])->toBe(['value' => 'No calculable', 'calculated' => true]);
});

it('validates an order whose derived value is not calculable and lets a measured value replace it', function () {
    $validator = User::factory()->create();
    $validator->assignRole('Validador');
    $orderExam = derivedOrderExam($this->technician, 'HEPATICAS', ['bilirrubina_total', 'bilirrubina_directa', 'bilirrubina_indirecta']);

    $notCalculable = derivedSave($this->technician, $orderExam, ['bilirrubina_total' => '1.0', 'bilirrubina_directa' => '1.4', 'bilirrubina_indirecta' => null]);
    $resubmitted = derivedSave($this->technician, $orderExam, ['bilirrubina_total' => '1.0', 'bilirrubina_directa' => '1.4', 'bilirrubina_indirecta' => 'No calculable']);

    expect($notCalculable['bilirrubina_indirecta'])->toBe(['value' => 'No calculable', 'calculated' => true])
        ->and($resubmitted['bilirrubina_indirecta'])->toBe(['value' => 'No calculable', 'calculated' => true]);

    $order = app(ValidateLabOrder::class)->handle($orderExam->order->fresh(), $validator);

    expect($order->status)->toBe(Order::STATUS_VALIDATED);
});

it('only accepts "No calculable" in derived parameters', function () {
    $orderExam = derivedOrderExam($this->technician, 'HEPATICAS', ['bilirrubina_total', 'bilirrubina_directa', 'bilirrubina_indirecta']);

    expect(fn () => derivedSave($this->technician, $orderExam, ['bilirrubina_total' => 'No calculable', 'bilirrubina_directa' => '1.2', 'bilirrubina_indirecta' => null]))
        ->toThrow(ValidationException::class);
});

it('calculates the free to total PSA ratio as a percentage', function () {
    $orderExam = derivedOrderExam($this->technician, 'MARCADORES_TUMORALES', ['psa_total', 'psa_libre', 'psa_relacion_libre_total']);

    $values = derivedSave($this->technician, $orderExam, ['psa_total' => '12.36', 'psa_libre' => '1.46', 'psa_relacion_libre_total' => null]);

    expect($values['psa_relacion_libre_total'])->toBe(['value' => '11.81', 'calculated' => true]);
});

it('does not calculate when an input analysis was not requested', function () {
    $orderExam = derivedOrderExam($this->technician, 'HEPATICAS', ['bilirrubina_total', 'bilirrubina_indirecta']);

    $values = derivedSave($this->technician, $orderExam, ['bilirrubina_total' => '1.9', 'bilirrubina_indirecta' => null]);

    expect($values)->not->toHaveKey('bilirrubina_indirecta');
});
