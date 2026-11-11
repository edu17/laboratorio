<?php

namespace App\Filament\Resources\ExamSections\Pages;

use App\Filament\Resources\ExamSections\ExamSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExamSections extends ListRecords
{
    protected static string $resource = ExamSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
