<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'action';

    protected static ?string $modelLabel = 'evento de auditoría';

    protected static ?string $pluralModelLabel = 'Auditoría';

    protected static ?string $slug = 'auditoria';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('action')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->defaultSort('id', 'desc')
            ->recordUrl(null)
            ->columns([
                TextColumn::make('action')
                    ->label('Acción')->searchable(),
                TextColumn::make('user_id')->label('Usuario ID')->placeholder('Sistema'),
                TextColumn::make('entity_type')->label('Entidad'),
                TextColumn::make('entity_id')->label('Registro'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
            ])
            ->filters([
                //
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
