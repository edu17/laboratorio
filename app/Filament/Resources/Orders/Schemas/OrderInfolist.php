<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\OrderValidationEvent;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    /**
     * Ver la orden no implica ver sus resultados clínicos: eso exige View:Result, como en OrderExamPolicy.
     */
    public static function canViewResults(): bool
    {
        return auth()->user()?->can('View:Result') ?? false;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 2])
            ->components(fn (Order $record): array => [
                self::patientSection(),
                self::orderSection(),
                ...(self::canViewResults() ? self::examResultSections($record) : [self::requestedExamsSection()]),
                self::validationHistorySection(),
            ]);
    }

    private static function patientSection(): Section
    {
        return Section::make('Paciente')
            ->icon('heroicon-o-user')
            ->columns(2)
            ->schema([
                TextEntry::make('patient.full_name')
                    ->label('Nombre')
                    ->weight('bold')
                    ->columnSpanFull()
                    ->url(fn (Order $record): ?string => auth()->user()?->can('view', $record->patient)
                        ? PatientResource::getUrl('view', ['record' => $record->patient_id])
                        : null),
                TextEntry::make('patient.age_display')->label('Edad')->placeholder('Sin fecha de nacimiento'),
                TextEntry::make('patient.sex')
                    ->label('Sexo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'femenino' => 'Femenino',
                        'masculino' => 'Masculino',
                        default => 'Sin registrar',
                    })
                    ->placeholder('Sin registrar'),
                TextEntry::make('patient.identifier')->label('Documento')->placeholder('Sin documento'),
                TextEntry::make('referring_doctor')->label('Médico remitente')->placeholder('Sin referencia'),
            ]);
    }

    private static function orderSection(): Section
    {
        return Section::make('Orden')
            ->icon('heroicon-o-clipboard-document-list')
            ->columns(2)
            ->schema([
                TextEntry::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => Order::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('ordered_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                TextEntry::make('creator.name')->label('Creada por'),
                TextEntry::make('validatedBy.name')
                    ->label('Validada por')
                    ->state(fn (Order $record): ?string => $record->validatedBy
                        ? $record->validatedBy->name.' · '.$record->validated_at?->format('d/m/Y H:i').' · versión '.$record->validation_version
                        : null)
                    ->placeholder('Sin validar'),
                TextEntry::make('notes')->label('Observaciones administrativas')->columnSpanFull()->placeholder('Sin observaciones'),
                TextEntry::make('cancellation_reason')
                    ->label('Anulada')
                    ->state(fn (Order $record): string => $record->cancellation_reason
                        .' · '.$record->canceler?->name.' · '.$record->canceled_at?->format('d/m/Y H:i'))
                    ->color('danger')
                    ->columnSpanFull()
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_CANCELED),
            ]);
    }

    /**
     * Sin permiso para ver resultados solo se listan los exámenes y su estado.
     */
    private static function requestedExamsSection(): Section
    {
        return Section::make('Exámenes solicitados')
            ->icon('heroicon-o-beaker')
            ->columnSpanFull()
            ->schema([
                TextEntry::make('orderExams')
                    ->hiddenLabel()
                    ->state(fn (Order $record): array => $record->loadMissing('orderExams.exam')->orderExams
                        ->map(fn (OrderExam $orderExam): string => self::examName($orderExam).' — '.(OrderExam::STATUS_LABELS[$orderExam->status] ?? $orderExam->status))
                        ->all())
                    ->listWithLineBreaks()
                    ->bulleted(),
            ]);
    }

    /**
     * Un bloque por examen con sus resultados en tabla, con el mismo formato del reporte impreso, y el acceso
     * directo a su captura mientras la orden siga abierta.
     *
     * @return list<Section>
     */
    private static function examResultSections(Order $record): array
    {
        $record->loadMissing(['patient', 'orderExams.exam', 'orderExams.resultValues']);
        $formatter = app(BuildValidatedOrderReportData::class);

        return $record->orderExams
            ->map(function (OrderExam $orderExam) use ($record, $formatter): Section {
                $orderExam->setRelation('order', $record);
                $missing = count($orderExam->missingResults());
                $isClosed = in_array($orderExam->status, [OrderExam::STATUS_CANCELED, OrderExam::STATUS_WITHDRAWN], true);

                return Section::make(self::examName($orderExam))
                    ->icon('heroicon-o-beaker')
                    ->description(implode(' · ', array_filter([
                        OrderExam::STATUS_LABELS[$orderExam->status] ?? $orderExam->status,
                        ! $isClosed && $missing > 0 ? ($missing === 1 ? '1 resultado pendiente' : $missing.' resultados pendientes') : null,
                        ! $isClosed && $missing === 0 ? 'Resultados completos' : null,
                    ])))
                    ->columnSpanFull()
                    ->collapsible()
                    ->afterHeader([
                        Action::make('captureExam'.$orderExam->getKey())
                            ->label($missing > 0 ? 'Capturar' : 'Editar resultados')
                            ->icon('heroicon-o-pencil-square')
                            ->size('sm')
                            ->visible(auth()->user()?->can('captureResults', $orderExam) ?? false)
                            ->url(OrderResource::getUrl('capture-results', ['record' => $record, 'orderExam' => $orderExam])),
                    ])
                    ->schema([
                        ViewEntry::make('examResults'.$orderExam->getKey())
                            ->hiddenLabel()
                            ->view('filament.orders.exam-results')
                            ->viewData(['exam' => $formatter->formatOrderExam($orderExam, $record->patient?->sex)]),
                    ]);
            })
            ->values()
            ->all();
    }

    private static function validationHistorySection(): Section
    {
        return Section::make('Historial de validación')
            ->icon('heroicon-o-clock')
            ->columnSpanFull()
            ->collapsed()
            ->visible(fn (Order $record): bool => $record->validationEvents()->exists())
            ->schema([
                TextEntry::make('validationEvents')
                    ->hiddenLabel()
                    ->state(fn (Order $record): array => $record->loadMissing('validationEvents.actor')->validationEvents
                        ->map(fn (OrderValidationEvent $event): string => match ($event->action) {
                            OrderValidationEvent::ACTION_VALIDATED => 'Validada · versión '.$event->version.' · '.$event->actor->name.' · '.$event->acted_at->format('d/m/Y H:i'),
                            OrderValidationEvent::ACTION_REOPENED => 'Reabierta · versión '.$event->version.' · '.$event->actor->name.' · '.$event->acted_at->format('d/m/Y H:i')
                                .(filled($event->reason) ? ' · Motivo: '.$event->reason : ''),
                            default => 'Acción de validación · versión '.$event->version.' · '.$event->acted_at->format('d/m/Y H:i'),
                        })
                        ->all())
                    ->listWithLineBreaks(),
            ]);
    }

    private static function examName(OrderExam $orderExam): string
    {
        return $orderExam->catalog_snapshot['exam']['name'] ?? $orderExam->exam->name;
    }
}
