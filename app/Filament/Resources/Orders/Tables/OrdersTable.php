<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Actions\Orders\CancelLabOrder;
use App\Models\Exam;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder('Buscar orden, paciente, examen o resultado...')
            ->searchUsing(function (Builder $query, string $search): void {
                $terms = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                foreach ($terms as $term) {
                    $pattern = '%'.$term.'%';

                    $query->where(function (Builder $query) use ($pattern): void {
                        $query->where('number', 'like', $pattern)
                            ->orWhere('status', 'like', $pattern)
                            ->orWhereHas('patient', function (Builder $query) use ($pattern): void {
                                $query->where('first_names', 'like', $pattern)
                                    ->orWhere('last_names', 'like', $pattern)
                                    ->orWhere('identifier', 'like', $pattern)
                                    ->orWhere('phone', 'like', $pattern);
                            })
                            ->orWhereHas('orderExams', function (Builder $query) use ($pattern): void {
                                $query->where('catalog_snapshot->exam->name', 'like', $pattern)
                                    ->orWhereHas('exam', fn (Builder $query): Builder => $query->where('name', 'like', $pattern))
                                    ->orWhereHas('resultValues', function (Builder $query) use ($pattern): void {
                                        $query->where('parameter_code', 'like', $pattern)
                                            ->orWhereRaw('CAST(result_values.value AS CHAR) LIKE ?', [$pattern]);
                                    });
                            })
                            ->orWhereHas('validationEvents', fn (Builder $query): Builder => $query
                                ->whereRaw('CAST(order_validation_events.results_snapshot AS CHAR) LIKE ?', [$pattern]));
                    });
                }
            })
            ->columns([
                TextColumn::make('number')->label('Número')->searchable()->sortable(),
                TextColumn::make('patient.first_names')->label('Nombres')->searchable()->sortable(),
                TextColumn::make('patient.last_names')->label('Apellidos')->searchable()->sortable(),
                TextColumn::make('ordered_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('order_exams_count')->counts('orderExams')->label('Exámenes'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'Pendiente',
                        Order::STATUS_IN_PROGRESS => 'En proceso',
                        Order::STATUS_VALIDATED => 'Validada',
                        Order::STATUS_PUBLISHED => 'Publicada',
                        Order::STATUS_CANCELED => 'Anulada',
                        default => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    Order::STATUS_PENDING => 'Pendiente',
                    Order::STATUS_IN_PROGRESS => 'En proceso',
                    Order::STATUS_VALIDATED => 'Validada',
                    Order::STATUS_PUBLISHED => 'Publicada',
                    Order::STATUS_CANCELED => 'Anulada',
                ]),
                SelectFilter::make('exam_id')
                    ->label('Examen')
                    ->options(fn (): array => Exam::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $query, int|string $examId): Builder => $query
                            ->whereHas('orderExams', fn (Builder $query): Builder => $query->where('exam_id', $examId)))),
                SelectFilter::make('referring_doctor_id')
                    ->label('Médico remitente')
                    ->relationship('referringDoctor', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('ordered_at')->label('Fecha de orden')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('ordered_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('ordered_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('cancel')
                    ->label('Anular')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => $record->status !== Order::STATUS_CANCELED
                        && (auth()->user()?->can('cancel', $record) ?? false))
                    ->form([
                        Textarea::make('cancellation_reason')->label('Motivo de anulación')->required()->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Order $record, array $data): void {
                        app(CancelLabOrder::class)->handle($record, $data['cancellation_reason'], auth()->user());

                        Notification::make()->success()->title('Orden anulada')->send();
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('ordered_at', 'desc')
            ->paginationPageOptions([10, 25, 50]);
    }
}
