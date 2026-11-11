<?php

namespace App\Filament\Resources\ExamParameters\Pages;

use App\Filament\Resources\ExamParameters\ExamParameterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExamParameter extends ViewRecord
{
    protected static string $resource = ExamParameterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
