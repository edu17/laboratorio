<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\CancelLabOrder;
use App\Actions\Orders\PublishOrderReport;
use App\Actions\Orders\ReopenLabOrder;
use App\Actions\Orders\RevokePublishedReport;
use App\Actions\Orders\ValidateLabOrder;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderExam;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'Orden '.$this->getRecord()->number;
    }

    /**
     * El primer examen que el usuario puede capturar y aún tiene resultados pendientes; si todos están
     * completos, el primero que todavía se puede corregir.
     */
    private function nextExamToCapture(): ?OrderExam
    {
        /** @var Order $order */
        $order = $this->getRecord();
        $capturable = $order->loadMissing(['orderExams.exam', 'orderExams.resultValues'])->orderExams
            ->each(fn (OrderExam $orderExam): OrderExam => $orderExam->setRelation('order', $order))
            ->filter(fn (OrderExam $orderExam): bool => auth()->user()?->can('captureResults', $orderExam) ?? false);

        return $capturable->first(fn (OrderExam $orderExam): bool => $orderExam->missingResults() !== []) ?? $capturable->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('captureResults')
                ->label('Capturar resultados')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => $this->nextExamToCapture() !== null)
                ->url(fn (Order $record): ?string => ($orderExam = $this->nextExamToCapture())
                    ? OrderResource::getUrl('capture-results', ['record' => $record, 'orderExam' => $orderExam])
                    : null),
            Action::make('validateResults')
                ->label('Validar orden completa')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Order $record): bool => auth()->user()?->can('validateResults', $record) ?? false)
                ->modalHeading('Confirmar validación')
                ->modalDescription('Se validarán todos los exámenes y resultados capturados en esta orden. Los resultados quedarán bloqueados para edición.')
                ->modalSubmitActionLabel('Validar orden')
                ->requiresConfirmation()
                ->action(function (Order $record): ?RedirectResponse {
                    try {
                        app(ValidateLabOrder::class)->handle($record, auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo validar la orden')
                            ->body($exception->errors()['results'][0] ?? 'Revisa que los resultados estén completos.')
                            ->persistent()
                            ->send();

                        return null;
                    }

                    Notification::make()->success()->title('Orden validada')->send();

                    return redirect(OrderResource::getUrl('view', ['record' => $record]));
                }),
            Action::make('viewReport')
                ->label('Ver reporte PDF')
                ->icon('heroicon-o-document-text')
                ->visible(fn (Order $record): bool => auth()->user()?->can('generateReport', $record) ?? false)
                ->url(fn (Order $record): string => route('orders.report', ['order' => $record]))
                ->openUrlInNewTab(),
            Action::make('publishReport')
                ->label('Publicar y generar QR')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->visible(fn (Order $record): bool => auth()->user()?->can('publishReport', $record) ?? false)
                ->modalHeading('Publicar reporte mediante QR')
                ->modalDescription('Cualquier persona que escanee el QR podrá consultar y descargar este PDF. No se solicitará otro código.')
                ->requiresConfirmation()
                ->action(function (Order $record): RedirectResponse {
                    app(PublishOrderReport::class)->handle($record, auth()->user());

                    Notification::make()->success()->title('Reporte publicado')->body('El QR ya está incluido en el PDF.')->send();

                    return redirect()->route('orders.report', $record);
                }),
            ActionGroup::make([
                EditAction::make(),
                Action::make('downloadReport')
                    ->label('Descargar PDF')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('generateReport', $record) ?? false)
                    ->url(fn (Order $record): string => route('orders.report', ['order' => $record, 'download' => 1])),
                Action::make('reopenResults')
                    ->label('Reabrir orden')
                    ->color('warning')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('reopenResults', $record) ?? false)
                    ->form([
                        Textarea::make('reason')->label('Motivo de reapertura')->required()->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Order $record, array $data): void {
                        app(ReopenLabOrder::class)->handle($record, $data['reason'], auth()->user());

                        Notification::make()->success()->title('Orden reabierta')->send();
                    }),
                Action::make('revokeReport')
                    ->label('Revocar acceso QR')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('revokeReport', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalDescription('El enlace dejará de mostrar el PDF y el reporte volverá a estado validado.')
                    ->action(function (Order $record): void {
                        app(RevokePublishedReport::class)->handle($record, auth()->user());

                        Notification::make()->success()->title('Acceso QR revocado')->send();
                    }),
                Action::make('cancel')
                    ->label('Anular orden')
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
                ->label('Más')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }
}
