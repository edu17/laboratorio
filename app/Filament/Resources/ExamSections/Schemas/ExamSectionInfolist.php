<?php

namespace App\Filament\Resources\ExamSections\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ExamSectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('exam.name')->label('Examen'),
                TextEntry::make('code')->label('Código'),
                TextEntry::make('name')->label('Sección'),
                TextEntry::make('structure')->label('Estructura')
                    ->formatStateUsing(fn (string $state): string => $state === 'parasitological_matrix' ? 'Matriz parasitológica' : 'Tabla'),
                TextEntry::make('settings.columns')->label('Columnas de matriz')->listWithLineBreaks()->placeholder('No aplica'),
                TextEntry::make('settings.report_layout')->label('Distribución en el PDF')
                    ->formatStateUsing(fn (string $state): string => $state === 'paired' ? 'Dos grupos de parámetro y resultado' : 'Tabla con unidad y referencia')
                    ->placeholder('Automática: diseño actual del examen'),
                TextEntry::make('source')->label('Fuente de estructura')->placeholder('No indicada'),
                TextEntry::make('clinical_validation_status')->label('Validación clínica')
                    ->formatStateUsing(fn (string $state): string => $state === 'validated' ? 'Validado' : 'Pendiente'),
            ]);
    }
}
