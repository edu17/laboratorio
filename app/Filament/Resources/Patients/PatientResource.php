<?php

namespace App\Filament\Resources\Patients;

use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Models\Patient;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 2;

    protected static UnitEnum|string|null $navigationGroup = 'Operaciones';

    protected static ?string $recordTitleAttribute = 'first_names';

    protected static ?string $modelLabel = 'paciente';

    protected static ?string $pluralModelLabel = 'Pacientes';

    protected static ?string $slug = 'pacientes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_names')
                    ->label('Nombres')
                    ->required()
                    ->maxLength(150),
                TextInput::make('last_names')->label('Apellidos')->maxLength(150),
                DatePicker::make('birth_date')->label('Fecha de nacimiento')->maxDate(today())->rules(['before_or_equal:today']),
                Select::make('sex')->label('Sexo')->options(['femenino' => 'Femenino', 'masculino' => 'Masculino'])->in(['femenino', 'masculino'])->placeholder('Sin especificar'),
                TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30),
                TextInput::make('identifier')->label('Identificador')->maxLength(100)->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_names')
            ->defaultSort('id', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->emptyStateHeading('No se encontraron pacientes')
            ->columns([
                TextColumn::make('first_names')
                    ->label('Nombres')->searchable()->sortable(),
                TextColumn::make('last_names')->label('Apellidos')->searchable()->sortable(),
                TextColumn::make('identifier')->label('Identificador')->searchable()->placeholder('—'),
                TextColumn::make('birth_date')->label('Nacimiento')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('phone')->label('Teléfono')->searchable()->placeholder('—'),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatients::route('/'),
            'create' => CreatePatient::route('/create'),
            'view' => ViewPatient::route('/{record}'),
            'edit' => EditPatient::route('/{record}/edit'),
        ];
    }
}
