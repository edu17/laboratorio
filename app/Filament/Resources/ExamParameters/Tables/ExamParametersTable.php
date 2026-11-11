<?php

namespace App\Filament\Resources\ExamParameters\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamParametersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('section.exam.name')->label('Examen')->searchable()->sortable(),
                TextColumn::make('section.name')->label('Sección')->searchable()->sortable(),
                TextColumn::make('code')->label('Código')->searchable(),
                TextColumn::make('name')->label('Parámetro')->searchable()->sortable(),
                TextColumn::make('result_type')->label('Tipo')->badge(),
                TextColumn::make('unit')->label('Unidad')->placeholder('Sin definir'),
                TextColumn::make('reference_text')->label('Referencia')->placeholder('Pendiente'),
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
