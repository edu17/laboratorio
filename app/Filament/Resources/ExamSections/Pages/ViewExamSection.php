<?php

namespace App\Filament\Resources\ExamSections\Pages;

use App\Filament\Resources\ExamSections\ExamSectionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExamSection extends ViewRecord
{
    protected static string $resource = ExamSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
