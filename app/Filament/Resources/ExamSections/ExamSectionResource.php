<?php

namespace App\Filament\Resources\ExamSections;

use App\Filament\Resources\ExamSections\Pages\CreateExamSection;
use App\Filament\Resources\ExamSections\Pages\EditExamSection;
use App\Filament\Resources\ExamSections\Pages\ListExamSections;
use App\Filament\Resources\ExamSections\Pages\ViewExamSection;
use App\Filament\Resources\ExamSections\Schemas\ExamSectionForm;
use App\Filament\Resources\ExamSections\Schemas\ExamSectionInfolist;
use App\Filament\Resources\ExamSections\Tables\ExamSectionsTable;
use App\Models\ExamSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExamSectionResource extends Resource
{
    protected static ?string $model = ExamSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Catálogo clínico';

    protected static ?string $navigationLabel = 'Secciones';

    protected static ?string $modelLabel = 'sección';

    protected static ?string $pluralModelLabel = 'Secciones';

    protected static ?string $slug = 'catalogo/secciones';

    public static function form(Schema $schema): Schema
    {
        return ExamSectionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExamSectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamSectionsTable::configure($table);
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
            'index' => ListExamSections::route('/'),
            'create' => CreateExamSection::route('/create'),
            'view' => ViewExamSection::route('/{record}'),
            'edit' => EditExamSection::route('/{record}/edit'),
        ];
    }
}
