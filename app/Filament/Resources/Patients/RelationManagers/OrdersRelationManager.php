<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderExam;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Historial del paciente: todas sus órdenes, de la más reciente a la más antigua.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Historial de órdenes';

    protected static ?string $modelLabel = 'orden';

    protected static ?string $pluralModelLabel = 'órdenes';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['orderExams' => fn ($query) => $query->whereNotIn('status', [OrderExam::STATUS_WITHDRAWN])]))
            ->defaultSort('ordered_at', 'desc')
            ->paginated([10, 25])
            ->emptyStateHeading('Este paciente todavía no tiene órdenes')
            ->columns([
                TextColumn::make('number')->label('Orden')->weight('bold'),
                TextColumn::make('ordered_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('exams')
                    ->label('Exámenes')
                    ->state(fn (Order $record): string => $record->orderExams
                        ->map(fn (OrderExam $orderExam): string => $orderExam->catalog_snapshot['exam']['name'] ?? '')
                        ->filter()
                        ->implode(', '))
                    ->wrap(),
                TextColumn::make('referring_doctor')->label('Médico remitente')->placeholder('Sin referencia')->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => Order::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->headerActions([
                Action::make('newOrder')
                    ->label('Nueva orden para este paciente')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => Gate::allows('create', Order::class))
                    ->url(fn (): string => OrderResource::getUrl('create', ['patient' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                Action::make('report')
                    ->label('Reporte PDF')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (Order $record): bool => Gate::allows('generateReport', $record))
                    ->url(fn (Order $record): string => route('orders.report', $record))
                    ->openUrlInNewTab(),
                Action::make('open')
                    ->label('Ver orden')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]));
    }
}
