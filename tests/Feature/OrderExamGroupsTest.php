<?php

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use App\Models\Order;
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
    $reception = User::factory()->create();
    $reception->assignRole('Recepción');
    $this->actingAs($reception);
});

function groupedExam(string $code, ?string $category, bool $partial = false): Exam
{
    $exam = Exam::factory()->create(['code' => $code, 'name' => "Examen ficticio {$code}", 'category' => $category, 'allows_partial_request' => $partial]);
    $section = ExamSection::factory()->create(['exam_id' => $exam->id, 'code' => strtolower($code), 'name' => $code]);
    ExamParameter::factory()->create(['exam_section_id' => $section->id, 'code' => strtolower($code).'_a', 'name' => 'A', 'result_type' => 'text']);
    ExamParameter::factory()->create(['exam_section_id' => $section->id, 'code' => strtolower($code).'_b', 'name' => 'B', 'result_type' => 'text']);

    return $exam;
}

it('groups the exams by category and orders what is marked in any group', function () {
    $qualitative = groupedExam('CUALI', 'cualitativas');
    $quantitative = groupedExam('CUANTI', 'cuantitativas', partial: true);
    $other = groupedExam('OTRO', null);
    $patient = Patient::factory()->create();

    Livewire::test(CreateOrder::class)
        ->assertSeeInOrder(['Pruebas cualitativas', 'Examen ficticio CUALI', 'Pruebas cuantitativas', 'Examen ficticio CUANTI', 'Otros exámenes', 'Examen ficticio OTRO'])
        ->set('data.patient_id', $patient->id)
        ->set('data.exam_selection.cualitativas', [(string) $qualitative->id])
        ->set('data.exam_selection.cuantitativas', [(string) $quantitative->id])
        ->assertSet('data.exam_ids', [(string) $qualitative->id, (string) $quantitative->id])
        ->assertSet("data.requested_parameter_codes.{$quantitative->id}", ['cuanti_a', 'cuanti_b'])
        ->assertSee('2 exámenes seleccionados')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Order::where('patient_id', $patient->id)->sole()->orderExams()->pluck('exam_id')->sort()->values()->all())
        ->toBe([$qualitative->id, $quantitative->id])
        ->and($other->id)->not->toBeIn(Order::sole()->orderExams()->pluck('exam_id')->all());
});

it('asks for at least one exam next to the exam lists', function () {
    groupedExam('CUALI', 'cualitativas');

    Livewire::test(CreateOrder::class)
        ->set('data.patient_id', Patient::factory()->create()->id)
        ->call('create')
        ->assertHasFormErrors(['exam_selection.cualitativas']);

    expect(Order::count())->toBe(0);
});
