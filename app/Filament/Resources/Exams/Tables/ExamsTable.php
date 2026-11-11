<?php

namespace App\Filament\Resources\Exams\Tables;

use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('name')->label('Examen')->searchable()->sortable(),
                TextColumn::make('category')
                    ->label('Grupo')
                    ->formatStateUsing(fn (?string $state): string => Exam::CATEGORIES[$state] ?? 'Otros exámenes')
                    ->placeholder('Otros exámenes')
                    ->sortable(),
                TextColumn::make('sections_count')->counts('sections')->label('Secciones'),
                TextColumn::make('catalog_versions_count')->counts('catalogVersions')->label('Versiones publicadas'),
                TextColumn::make('clinical_validation_status')->label('Validación clínica')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'validated' ? 'Validado' : 'Pendiente'),
                TextColumn::make('is_active')->label('Estado')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Vista previa PDF')
                    ->visible(fn (Exam $record): bool => auth()->user()?->can('view', $record) ?? false)
                    ->url(fn (Exam $record): string => route('exams.preview', $record))
                    ->openUrlInNewTab(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
