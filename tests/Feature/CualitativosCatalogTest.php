<?php

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\CualitativosCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CualitativosCatalogSeeder::class);
});

function cualitativosUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('seeds the qualitative exams idempotently with traceable validated parameters', function () {
    $this->seed(CualitativosCatalogSeeder::class);

    $codes = ['VIH', 'VDRL', 'HCG_EMBARAZO', 'GRUPO_RH', 'ROTA_ADENO', 'DENGUE', 'HEPATITIS', 'HPYLORI_SANGRE', 'SANGRE_OCULTA', 'TORCH_IGM', 'TOXICOLOGICO', 'FANA'];

    expect(Exam::pluck('code')->sort()->values()->all())->toBe(collect($codes)->sort()->values()->all())
        ->and(ExamParameter::count())->toBe(29)
        ->and(ExamParameter::where('clinical_validation_status', '!=', 'validated')->count())->toBe(0)
        ->and(Exam::where('code', 'TOXICOLOGICO')->value('source'))->toBe('2025.xlsx · hoja TOXICOLOGICO1')
        ->and(Exam::where('allows_partial_request', true)->pluck('code')->sort()->values()->all())
        ->toBe(['DENGUE', 'HEPATITIS', 'ROTA_ADENO', 'TORCH_IGM', 'TOXICOLOGICO']);

    ExamParameter::whereIn('result_type', ['select', 'positive_negative'])->get()
        ->each(fn (ExamParameter $parameter) => expect($parameter->options)->not->toBeEmpty());
});

it('reports a partial dengue request with the selected result only', function () {
    $administrator = cualitativosUser('Administrador');
    $validator = cualitativosUser('Validador');
    $dengue = Exam::where('code', 'DENGUE')->sole();
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$dengue->id], $administrator, requestedParameterCodes: [$dengue->id => ['dengue_igm']]);
    $orderExam = $order->orderExams->sole();

    expect(fn () => app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => ['dengue_igm' => ['value' => 'Reactivo']]], $administrator))
        ->toThrow(ValidationException::class);

    app(SaveOrderExamResultDraft::class)->handle($orderExam, ['values' => ['dengue_igm' => ['value' => 'Positivo']]], $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    $rows = app(BuildValidatedOrderReportData::class)->handle($order->fresh(), $validator)['exams'][0]['sections'][0]['rows'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('IgM')
        ->and($rows[0]['result'])->toBe('Positivo');
});

it('prints the validated FANA reference next to a free text result', function () {
    $administrator = cualitativosUser('Administrador');
    $validator = cualitativosUser('Validador');
    $fana = Exam::where('code', 'FANA')->sole();
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$fana->id], $administrator);

    app(SaveOrderExamResultDraft::class)->handle($order->orderExams->sole(), ['values' => ['fana' => ['value' => 'Positivo Patrón centromérico Dilución 1:80']]], $administrator);
    app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

    $row = app(BuildValidatedOrderReportData::class)->handle($order->fresh(), $validator)['exams'][0]['sections'][0]['rows'][0];

    expect($row['result'])->toBe('Positivo Patrón centromérico Dilución 1:80')
        ->and($row['reference'])->toBe('Negativo: Menor a Dilución 1:80 Sustrato HEP-2000');
});
