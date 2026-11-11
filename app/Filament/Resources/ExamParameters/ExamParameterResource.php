<?php

namespace App\Filament\Resources\ExamParameters;

use App\Filament\Resources\ExamParameters\Pages\CreateExamParameter;
use App\Filament\Resources\ExamParameters\Pages\EditExamParameter;
use App\Filament\Resources\ExamParameters\Pages\ListExamParameters;
use App\Filament\Resources\ExamParameters\Pages\ViewExamParameter;
use App\Filament\Resources\ExamParameters\Schemas\ExamParameterForm;
use App\Filament\Resources\ExamParameters\Schemas\ExamParameterInfolist;
use App\Filament\Resources\ExamParameters\Tables\ExamParametersTable;
use App\Models\ExamParameter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExamParameterResource extends Resource
{
    protected static ?string $model = ExamParameter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 3;

    protected static UnitEnum|string|null $navigationGroup = 'Catálogo clínico';

    protected static ?string $navigationLabel = 'Parámetros';

    protected static ?string $modelLabel = 'parámetro';

    protected static ?string $pluralModelLabel = 'Parámetros';

    protected static ?string $slug = 'catalogo/parametros';

    public static function form(Schema $schema): Schema
    {
        return ExamParameterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExamParameterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamParametersTable::configure($table);
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
            'index' => ListExamParameters::route('/'),
            'create' => CreateExamParameter::route('/create'),
            'view' => ViewExamParameter::route('/{record}'),
            'edit' => EditExamParameter::route('/{record}/edit'),
        ];
    }
}
