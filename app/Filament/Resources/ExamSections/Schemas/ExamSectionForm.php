<?php

namespace App\Filament\Resources\ExamSections\Schemas;

use App\Models\ExamSection;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ExamSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('exam_id')->label('Examen')->relationship('exam', 'name')->searchable()->preload()->required(),
                TextInput::make('code')->label('Código')->required()->alphaDash()->maxLength(80)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('exam_id', $get('exam_id'))),
                TextInput::make('name')->label('Nombre de sección')->required()->maxLength(180),
                Select::make('structure')->label('Tipo de estructura')->options([
                    'table' => 'Tabla de parámetros',
                    'parasitological_matrix' => 'Matriz parasitológica',
                ])->required()->live(),
                Select::make('settings.report_layout')
                    ->label('Distribución en el PDF')
                    ->options([
                        'standard' => 'Tabla: parámetro, resultado, unidad y referencia',
                        'paired' => 'Dos grupos de parámetro y resultado (como HECES)',
                    ])
                    ->in(['standard', 'paired'])
                    ->placeholder('Automática: conservar el diseño actual')
                    ->helperText('Para mostrar ORINA como HECES, selecciona dos grupos. Guarda y recarga la vista previa.')
                    ->visible(fn (Get $get): bool => $get('structure') === 'table')
                    ->columnSpanFull(),
                TagsInput::make('settings.columns')->label('Columnas de la matriz')
                    ->placeholder('Agregar columna')
                    ->required(fn (Get $get): bool => $get('structure') === 'parasitological_matrix')
                    ->visible(fn (Get $get): bool => $get('structure') === 'parasitological_matrix')
                    ->columnSpanFull(),
                TextInput::make('source')->label('Fuente de estructura')->maxLength(180),
                Placeholder::make('clinical_validation_status')
                    ->label('Estado de validación clínica')
                    ->content(fn (?ExamSection $record): string => $record?->clinical_validation_status === 'validated'
                        ? 'Validado clínicamente'
                        : 'Pendiente de validación clínica'),
                Toggle::make('is_active')->label('Activo')->default(true),
                TextInput::make('sort_order')->label('Orden')->numeric()->minValue(0)->default(0)->required(),
            ]);
    }
}
