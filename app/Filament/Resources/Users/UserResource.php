<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $slug = 'usuarios';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')->label('Correo electrónico')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('password')->label('Contraseña')->password()->autocomplete('new-password')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->rule(Password::min(12)->mixedCase()->numbers()->symbols())
                    ->confirmed()->dehydrated(fn (?string $state): bool => filled($state)),
                TextInput::make('password_confirmation')->label('Confirmar contraseña')->password()->dehydrated(false),
                Toggle::make('is_active')->label('Acceso activo')->default(true)->required(),
                Select::make('roles')->label('Roles')->relationship('roles', 'name', fn (Builder $query): Builder => $query->where('guard_name', 'web'))
                    ->multiple()->preload()->searchable()
                    ->visible(fn (): bool => auth()->user()->can('Manage:Role'))
                    ->saveRelationshipsUsing(function (User $record, ?array $state): void {
                        Gate::authorize('Manage:Role');
                        $record->syncRoles($state ?? []);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')->searchable(),
                TextColumn::make('email')->label('Correo electrónico')->searchable(),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
