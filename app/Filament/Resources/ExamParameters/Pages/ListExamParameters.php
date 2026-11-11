<?php

namespace App\Filament\Resources\ExamParameters\Pages;

use App\Filament\Resources\ExamParameters\ExamParameterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExamParameters extends ListRecords
{
    protected static string $resource = ExamParameterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
