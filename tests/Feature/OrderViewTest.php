<?php

use App\Actions\Orders\CreateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\CaptureOrderExamResults;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->technician = User::factory()->create();
    $this->technician->assignRole('Técnico');
});

function orderViewExam(string $code, string $name, int $parameterCount): Exam
{
    $exam = Exam::factory()->create(['code' => $code, 'name' => $name]);
    $section = ExamSection::factory()->create(['exam_id' => $exam->id, 'code' => strtolower($code), 'name' => $name]);

    foreach (range(1, $parameterCount) as $position) {
        ExamParameter::factory()->create([
            'exam_section_id' => $section->id,
            'code' => strtolower($code).'_'.$position,
            'name' => "Parámetro ficticio {$code} {$position}",
            'result_type' => 'number',
            'unit' => 'mg/dL',
            'reference_text' => '0.60 - 1.10 mg/dL',
            'reference_config' => ['min' => '0.60', 'max' => '1.10'],
            'clinical_validation_status' => 'validated',
            'sort_order' => $position,
        ]);
    }

    return $exam;
}

it('shows each exam as a results table with pending counts and out of range flags', function () {
    $order = app(CreateLabOrder::class)->handle(
        Patient::factory()->create(['sex' => 'masculino']),
        [orderViewExam('RENAL', 'Renal ficticio', 2)->id],
        $this->technician,
    );
    app(SaveOrderExamResultDraft::class)->handle($order->orderExams->sole(), ['values' => ['renal_1' => ['value' => '1.5']]], $this->technician);

    $this->actingAs($this->technician)
        ->get("/admin/ordenes/{$order->id}")
        ->assertOk()
        ->assertSee('Orden '.$order->number)
        ->assertSee('Renal ficticio')
        ->assertSee('1 resultado pendiente')
        ->assertSeeHtml('<span class="result out-of-range">1.5</span>')
        ->assertSee('▲ Alto');
});

it('sends the capture button to the first exam that still has pending results', function () {
    $complete = orderViewExam('COMPLETO', 'Completo ficticio', 1);
    $pending = orderViewExam('PENDIENTE', 'Pendiente ficticio', 1);
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$complete->id, $pending->id], $this->technician);
    $completeOrderExam = $order->orderExams->firstWhere('exam_id', $complete->id);
    $pendingOrderExam = $order->orderExams->firstWhere('exam_id', $pending->id);
    app(SaveOrderExamResultDraft::class)->handle($completeOrderExam, ['values' => ['completo_1' => ['value' => '0.8']]], $this->technician);

    Livewire::actingAs($this->technician)
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->assertActionHasUrl('captureResults', OrderResource::getUrl('capture-results', ['record' => $order, 'orderExam' => $pendingOrderExam]));
});

it('shows the capture progress and saves before moving on to the next exam with pending results', function () {
    $first = orderViewExam('PRIMERO', 'Primero ficticio', 2);
    $second = orderViewExam('SEGUNDO', 'Segundo ficticio', 1);
    $order = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$first->id, $second->id], $this->technician);
    $firstOrderExam = $order->orderExams->firstWhere('exam_id', $first->id);
    $secondOrderExam = $order->orderExams->firstWhere('exam_id', $second->id);

    Livewire::actingAs($this->technician)
        ->test(CaptureOrderExamResults::class, ['record' => $order->id, 'orderExam' => $firstOrderExam->id])
        ->assertSee('0 de 2 resultados capturados')
        ->assertSee('Segundo ficticio')
        ->fillForm(['values' => ['primero_1' => ['value' => '0.7'], 'primero_2' => ['value' => '0.9']]])
        ->call('saveAndNext')
        ->assertHasNoFormErrors()
        ->assertRedirect(OrderResource::getUrl('capture-results', ['record' => $order, 'orderExam' => $secondOrderExam]));

    expect($firstOrderExam->fresh()->load('resultValues')->missingResults())->toBe([]);

    Livewire::actingAs($this->technician)
        ->test(CaptureOrderExamResults::class, ['record' => $order->id, 'orderExam' => $secondOrderExam->id])
        ->fillForm(['values' => ['segundo_1' => ['value' => '0.8']]])
        ->call('saveAndNext')
        ->assertRedirect(OrderResource::getUrl('view', ['record' => $order]));
});
