<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\ExportOrdersSpreadsheet;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportSpreadsheet')
                ->label('Exportar a Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('export', Order::class) ?? false)
                ->tooltip('Descarga las órdenes con los filtros y la búsqueda actuales.')
                ->action(fn (): BinaryFileResponse => response()
                    ->download(
                        app(ExportOrdersSpreadsheet::class)->handle($this->getFilteredTableQuery(), auth()->user()),
                        'ordenes-'.now()->format('Y-m-d-His').'.xlsx',
                    )
                    ->deleteFileAfterSend()),
            CreateAction::make(),
        ];
    }
}
