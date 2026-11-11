<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Exam;
use App\Models\Order;
use App\Models\Patient;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('patient_id')
                    ->label('Paciente')
                    ->relationship('patient', 'first_names')
                    ->getOptionLabelFromRecordUsing(fn (Patient $record): string => trim($record->first_names.' '.$record->last_names).($record->identifier ? ' — '.$record->identifier : ''))
                    ->searchable(['first_names', 'last_names', 'identifier'])
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('first_names')->label('Nombres')->required()->maxLength(150),
                        TextInput::make('last_names')->label('Apellidos')->maxLength(150),
                        TextInput::make('identifier')->label('Identificador')->maxLength(100)->unique(),
                    ])
                    ->createOptionAction(fn ($action) => $action->label('Registrar paciente'))
                    ->createOptionUsing(function (array $data): int {
                        Gate::authorize('create', Patient::class);

                        return Patient::create($data)->getKey();
                    }),
                CheckboxList::make('exam_ids')
                    ->label('Exámenes solicitados')
                    ->options(fn (?Order $record): array => Exam::query()
                        ->where('is_active', true)
                        ->orWhereIn('id', $record?->orderExams()->pluck('exam_id')->all() ?? [])
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default([])
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('referring_doctor')->label('Doctor(a) remitente')->maxLength(180)->placeholder('Sin registrar'),
                Textarea::make('notes')->label('Observaciones administrativas')->maxLength(5000)->columnSpanFull(),
                DatePicker::make('ordered_at')->label('Fecha de orden')->default(today())->disabled()->dehydrated(false),
                TextInput::make('status')->label('Estado')->default('pendiente')->disabled()->dehydrated(false),
            ]);
    }
}
