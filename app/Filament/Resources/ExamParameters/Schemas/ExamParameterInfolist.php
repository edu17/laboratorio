<?php

namespace App\Filament\Resources\ExamParameters\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ExamParameterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('section.exam.name')->label('Examen'),
                TextEntry::make('section.name')->label('Sección'),
                TextEntry::make('code')->label('Identificador'),
                TextEntry::make('name')->label('Parámetro'),
                TextEntry::make('result_type')->label('Tipo de resultado'),
                TextEntry::make('unit')->label('Unidad')->placeholder('Sin definir'),
                TextEntry::make('reference_text')->label('Referencia')->placeholder('Pendiente'),
                TextEntry::make('options')->label('Opciones permitidas')->listWithLineBreaks()->placeholder('No definidas'),
                TextEntry::make('clinical_validation_status')->label('Validación clínica')
                    ->formatStateUsing(fn (string $state): string => $state === 'validated' ? 'Validado' : 'Pendiente'),
            ]);
    }
}
