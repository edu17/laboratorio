<?php

use App\Actions\Orders\CreateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Filament\Widgets\LabOverviewStats;
use App\Filament\Widgets\OpenOrdersTable;
use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use App\Models\Order;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->technician = User::factory()->create();
    $this->technician->assignRole('Técnico');
});

function dashboardExam(): Exam
{
    $exam = Exam::factory()->create(['code' => 'TABLERO', 'name' => 'Examen ficticio del tablero']);
    $section = ExamSection::factory()->create(['exam_id' => $exam->id, 'code' => 'tablero', 'name' => 'Tablero']);
    ExamParameter::factory()->create(['exam_section_id' => $section->id, 'code' => 'dato', 'name' => 'Dato', 'result_type' => 'text']);

    return $exam;
}

it('counts the work in progress and lists only open orders oldest first', function () {
    $exam = dashboardExam();
    $create = fn (): Order => app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $this->technician);
    $toCapture = $create();
    $readyToValidate = $create();
    app(SaveOrderExamResultDraft::class)->handle($readyToValidate->orderExams->sole(), ['values' => ['dato' => ['value' => 'Listo']]], $this->technician);
    $validated = $create();
    $validated->forceFill(['status' => Order::STATUS_VALIDATED])->save();
    $canceled = $create();
    $canceled->forceFill(['status' => Order::STATUS_CANCELED])->save();
    $toCapture->forceFill(['ordered_at' => now()->subDay()])->save();

    $this->actingAs($this->technician);

    $stats = collect((fn (): array => $this->getStats())->call(new LabOverviewStats))
        ->mapWithKeys(fn (Stat $stat): array => [$stat->getLabel() => [$stat->getValue(), $stat->getDescription()]]);

    expect($stats->all())->toBe([
        'Órdenes de hoy' => [2, '2 exámenes solicitados'],
        'Por capturar' => [1, '1 resultado pendiente'],
        'Listas para validar' => [1, 'Todos los resultados capturados'],
        'Validadas sin publicar' => [1, 'Reporte listo para entregar'],
    ]);

    Livewire::test(OpenOrdersTable::class)
        ->assertCanSeeTableRecords([$toCapture, $readyToValidate], inOrder: true)
        ->assertCanNotSeeTableRecords([$validated, $canceled])
        ->assertSee('0 de 1')
        ->assertSee('1 de 1');
});

it('hides the dashboard widgets from users who cannot list orders', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    expect(LabOverviewStats::canView())->toBeFalse()
        ->and(OpenOrdersTable::canView())->toBeFalse();
});
