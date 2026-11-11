<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderExam;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OpenOrdersTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Order::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Órdenes en curso')
            ->description('Pendientes y en proceso, la más antigua primero.')
            ->query(fn (): Builder => Order::query()
                ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_IN_PROGRESS])
                ->with(['patient', 'orderExams.exam', 'orderExams.resultValues']))
            ->defaultSort('ordered_at')
            ->columns([
                TextColumn::make('number')->label('Orden'),
                TextColumn::make('patient.full_name')->label('Paciente'),
                TextColumn::make('exams')
                    ->label('Exámenes')
                    ->state(fn (Order $record): string => $record->orderExams
                        ->map(fn (OrderExam $orderExam): string => $orderExam->catalog_snapshot['exam']['name'] ?? $orderExam->exam->name)
                        ->implode(', '))
                    ->wrap(),
                TextColumn::make('progress')
                    ->label('Avance')
                    ->badge()
                    ->state(function (Order $record): string {
                        $progress = $record->resultProgress();

                        return $progress['captured'].' de '.$progress['required'];
                    })
                    ->color(fn (Order $record): string => $record->resultProgress()['missing'] === 0 ? 'success' : 'warning'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => Order::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('ordered_at')
                    ->label('Ingreso')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i'),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No hay órdenes en curso')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25]);
    }
}
