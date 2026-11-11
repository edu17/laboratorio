<?php

namespace App\Filament\Resources\Exams\Schemas;

use App\Models\Exam;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label('Código')->required()->alphaDash()->maxLength(60)->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nombre del examen')->required()->maxLength(180),
                Select::make('category')
                    ->label('Grupo')
                    ->options(Exam::CATEGORIES)
                    ->placeholder('Otros exámenes')
                    ->helperText('Agrupa el examen al marcarlo en una orden.'),
                Textarea::make('description')->label('Descripción')->columnSpanFull(),
                TextInput::make('source')->label('Fuente de estructura')->maxLength(180),
                Placeholder::make('clinical_validation_status')
                    ->label('Estado de validación clínica')
                    ->content(fn (?Exam $record): string => $record?->clinical_validation_status === 'validated'
                        ? 'Validado clínicamente'
                        : 'Pendiente de validación clínica'),
                Toggle::make('is_active')->label('Activo')->default(true),
                Toggle::make('allows_partial_request')
                    ->label('Se puede pedir de forma parcial')
                    ->helperText('Permite marcar en la orden solo los análisis solicitados. Las matrices se informan siempre completas.')
                    ->default(false),
                TextInput::make('sort_order')->label('Orden')->numeric()->minValue(0)->default(0)->required(),
            ]);
    }
}
