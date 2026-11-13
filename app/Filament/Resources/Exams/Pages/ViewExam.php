<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExam extends ViewRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Vista previa PDF')
                ->url(fn (Exam $record): string => route('exams.preview', $record))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
