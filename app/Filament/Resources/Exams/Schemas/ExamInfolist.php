<?php

namespace App\Filament\Resources\Exams\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ExamInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code')->label('Código'),
                TextEntry::make('name')->label('Examen'),
                TextEntry::make('source')->label('Fuente de estructura')->placeholder('No indicada'),
                TextEntry::make('clinical_validation_status')->label('Validación clínica')
                    ->formatStateUsing(fn (string $state): string => $state === 'validated' ? 'Validado' : 'Pendiente'),
                TextEntry::make('description')->label('Descripción')->placeholder('Sin descripción'),
                TextEntry::make('is_active')->label('Estado')->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo'),
            ]);
    }
}
