<?php

namespace App\Filament\Resources\ExamSections\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exam.name')->label('Examen')->searchable()->sortable(),
                TextColumn::make('name')->label('Sección')->searchable()->sortable(),
                TextColumn::make('structure')->label('Estructura')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'parasitological_matrix' ? 'Matriz parasitológica' : 'Tabla'),
                TextColumn::make('parameters_count')->counts('parameters')->label('Elementos'),
                TextColumn::make('sort_order')->label('Orden')->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
