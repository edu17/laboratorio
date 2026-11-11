<?php

use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\ExportOrdersSpreadsheet;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Order;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('Administrador');
});

/**
 * @return list<list<mixed>>
 */
function spreadsheetRows(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();

    return $rows;
}

it('exports the filtered orders with real dates and logs the export', function () {
    $exam = Exam::factory()->create(['name' => 'Examen ficticio exportado']);
    $patient = Patient::factory()->create(['first_names' => 'Ana', 'last_names' => 'Ficticia Uno', 'birth_date' => '1990-05-17', 'sex' => 'femenino']);
    $wanted = app(CreateLabOrder::class)->handle($patient, [$exam->id], $this->administrator);
    $other = app(CreateLabOrder::class)->handle(Patient::factory()->create(), [$exam->id], $this->administrator);
    $other->forceFill(['status' => Order::STATUS_CANCELED])->save();

    $path = app(ExportOrdersSpreadsheet::class)->handle(Order::query()->where('status', Order::STATUS_PENDING), $this->administrator);
    $rows = spreadsheetRows($path);
    unlink($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('Orden')
        ->and($rows[1][0])->toBe($wanted->number)
        ->and($rows[1][2])->toBe('Pendiente')
        ->and([$rows[1][3], $rows[1][4], $rows[1][7], $rows[1][11]])->toBe(['Ana', 'Ficticia Uno', 'Femenino', 'Examen ficticio exportado'])
        ->and($rows[1][5])->toBeInstanceOf(DateTimeInterface::class)
        ->and($rows[1][5]->format('Y-m-d'))->toBe('1990-05-17')
        ->and(AuditLog::where('action', 'orders_exported')->sole()->metadata)->toBe(['orders' => 1]);
});

it('offers the export only to roles with the export permission', function () {
    $technician = User::factory()->create();
    $technician->assignRole('Técnico');

    $this->freezeTime();

    Livewire::actingAs($this->administrator)->test(ListOrders::class)
        ->assertActionVisible('exportSpreadsheet')
        ->callAction('exportSpreadsheet')
        ->assertFileDownloaded('ordenes-'.now()->format('Y-m-d-His').'.xlsx');
    Livewire::actingAs($technician)->test(ListOrders::class)->assertActionHidden('exportSpreadsheet');

    expect(fn () => app(ExportOrdersSpreadsheet::class)->handle(Order::query(), $technician))
        ->toThrow(AuthorizationException::class);
});
