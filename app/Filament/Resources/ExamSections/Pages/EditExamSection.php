<?php

namespace App\Filament\Resources\ExamSections\Pages;

use App\Filament\Resources\ExamSections\ExamSectionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExamSection extends EditRecord
{
    protected static string $resource = ExamSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
