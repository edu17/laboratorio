<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\OrderExam;
use App\Models\OrderValidationEvent;
use Filament\Infolists\Components\TextEntry;
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
            ->components([
                TextEntry::make('number')->label('Número de orden'),
                TextEntry::make('patient.first_names')->label('Nombres del paciente'),
                TextEntry::make('patient.last_names')->label('Apellidos del paciente'),
                TextEntry::make('ordered_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                TextEntry::make('status')->label('Estado'),
                TextEntry::make('validatedBy.name')->label('Validada por')->placeholder('Pendiente'),
                TextEntry::make('validated_at')->label('Fecha de validación')->dateTime('d/m/Y H:i')->placeholder('Pendiente'),
                TextEntry::make('validation_version')
                    ->label('Versión validada')
                    ->formatStateUsing(fn (?int $state): ?int => $state === 0 ? null : $state)
                    ->placeholder('Sin validar'),
                TextEntry::make('creator.name')->label('Creada por'),
                TextEntry::make('referring_doctor')->label('Médico remitente')->placeholder('Sin referencia'),
                TextEntry::make('notes')->label('Observaciones administrativas')->placeholder('Sin observaciones'),
                TextEntry::make('orderExams')
                    ->label(fn (): string => self::canViewResults() ? 'Exámenes y resultados capturados' : 'Exámenes solicitados')
                    ->state(fn (Order $record): array => $record->orderExams
                        ->map(function ($orderExam): string {
                            $examName = $orderExam->catalog_snapshot['exam']['name'] ?? $orderExam->exam->name;

                            if (! self::canViewResults()) {
                                return $examName.' — '.$orderExam->status;
                            }

                            $resultValues = $orderExam->resultValues->keyBy('parameter_code');
                            $results = [];

                            foreach ($orderExam->requestedCatalogSnapshot()['sections'] ?? [] as $section) {
                                if (($section['structure'] ?? null) === 'parasitological_matrix') {
                                    $sectionCode = $section['code'] ?? null;
                                    $matrixStatus = is_string($sectionCode)
                                        ? ($orderExam->section_statuses[$sectionCode] ?? OrderExam::SECTION_STATUS_PENDING)
                                        : OrderExam::SECTION_STATUS_PENDING;
                                    $matrixStatusLabel = match ($matrixStatus) {
                                        OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS => 'Completada con hallazgos',
                                        OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS => 'Completada sin hallazgos',
                                        default => 'Pendiente',
                                    };

                                    $results[] = ($section['name'] ?? 'Matriz parasitológica').': '.$matrixStatusLabel;
                                }

                                foreach ($section['parameters'] ?? [] as $parameter) {
                                    $code = $parameter['code'] ?? null;
                                    $value = is_string($code) ? $resultValues->get($code)?->value : null;

                                    if (! is_array($value)) {
                                        continue;
                                    }

                                    if (array_key_exists('value', $value) && filled($value['value'])) {
                                        $results[] = ($parameter['name'] ?? $code).': '.$value['value'];
                                    }

                                    if (is_array($value['cells'] ?? null)) {
                                        $columnLabels = array_slice($section['settings']['columns'] ?? [], 1);
                                        $cells = collect($value['cells'])
                                            ->filter(fn ($cell): bool => filled($cell))
                                            ->map(function (string $cell, string $columnKey) use ($columnLabels): string {
                                                foreach ($columnLabels as $columnLabel) {
                                                    if (str($columnLabel)->slug('_')->toString() === $columnKey) {
                                                        return $columnLabel.': '.$cell;
                                                    }
                                                }

                                                return $cell;
                                            })
                                            ->implode(', ');

                                        if ($cells !== '') {
                                            $results[] = ($parameter['name'] ?? $code).': '.$cells;
                                        }
                                    }
                                }
                            }

                            return $examName.' — '.$orderExam->status.($results === [] ? ' — Sin resultados capturados' : ' — '.implode('; ', $results));
                        })
                        ->all())
                    ->listWithLineBreaks(),
                TextEntry::make('validationEvents')
                    ->label('Historial de validación')
                    ->state(fn (Order $record): array => $record->validationEvents
                        ->map(fn (OrderValidationEvent $event): string => match ($event->action) {
                            OrderValidationEvent::ACTION_VALIDATED => 'Validada · versión '.$event->version.' · '.$event->actor->name.' · '.$event->acted_at->format('d/m/Y H:i'),
                            OrderValidationEvent::ACTION_REOPENED => 'Reabierta · versión '.$event->version.' · '.$event->actor->name.' · '.$event->acted_at->format('d/m/Y H:i')
                                .(filled($event->reason) ? ' · Motivo: '.$event->reason : ''),
                            default => 'Acción de validación · versión '.$event->version.' · '.$event->acted_at->format('d/m/Y H:i'),
                        })
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('Sin eventos de validación'),
                TextEntry::make('cancellation_reason')->label('Motivo de anulación')->placeholder('No anulada'),
                TextEntry::make('canceler.name')->label('Anulada por')->placeholder('—'),
                TextEntry::make('canceled_at')->label('Fecha de anulación')->dateTime('d/m/Y H:i')->placeholder('—'),
            ]);
    }
}
