<?php

namespace App\Filament\Resources\ReferringDoctors;

use App\Filament\Resources\ReferringDoctors\Pages\CreateReferringDoctor;
use App\Filament\Resources\ReferringDoctors\Pages\EditReferringDoctor;
use App\Filament\Resources\ReferringDoctors\Pages\ListReferringDoctors;
use App\Filament\Resources\ReferringDoctors\Pages\ViewReferringDoctor;
use App\Models\ReferringDoctor;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReferringDoctorResource extends Resource
{
    protected static ?string $model = ReferringDoctor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 3;

    protected static UnitEnum|string|null $navigationGroup = 'Operaciones';

    protected static ?string $navigationLabel = 'Médicos remitentes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'médico remitente';

    protected static ?string $pluralModelLabel = 'Médicos remitentes';

    protected static ?string $slug = 'medicos-remitentes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::formFields());
    }

    /**
     * Campos del formulario, compartidos con el alta rápida desde la orden.
     *
     * @return list<Field>
     */
    public static function formFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre')
                ->helperText('Como debe aparecer en el reporte, por ejemplo "Dr. Juan Pérez" o "Farmacia Santo Domingo".')
                ->required()
                ->maxLength(180)
                ->unique(ignoreRecord: true),
            Select::make('kind')
                ->label('Tipo')
                ->options(ReferringDoctor::KINDS)
                ->in(array_keys(ReferringDoctor::KINDS))
                ->default(ReferringDoctor::KIND_DOCTOR)
                ->required(),
            TextInput::make('specialty')->label('Especialidad')->maxLength(120),
            TextInput::make('license_number')->label('No. de colegiado')->maxLength(40),
            TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30),
            Toggle::make('is_active')
                ->label('Activo')
                ->helperText('Los inactivos no aparecen al crear órdenes nuevas.')
                ->default(true),
            Textarea::make('notes')->label('Notas')->maxLength(2000)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('orders'))
            ->defaultSort('name')
            ->paginationPageOptions([10, 25, 50])
            ->emptyStateHeading('No se encontraron médicos remitentes')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('kind')->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => ReferringDoctor::KINDS[$state] ?? $state)
                    ->badge(),
                TextColumn::make('specialty')->label('Especialidad')->searchable()->placeholder('—'),
                TextColumn::make('license_number')->label('Colegiado')->searchable()->placeholder('—'),
                TextColumn::make('phone')->label('Teléfono')->placeholder('—'),
                TextColumn::make('orders_count')->label('Órdenes')->sortable()->numeric(),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('kind')->label('Tipo')->options(ReferringDoctor::KINDS),
                TernaryFilter::make('is_active')->label('Activo')->default(true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferringDoctors::route('/'),
            'create' => CreateReferringDoctor::route('/create'),
            'view' => ViewReferringDoctor::route('/{record}'),
            'edit' => EditReferringDoctor::route('/{record}/edit'),
        ];
    }
}
