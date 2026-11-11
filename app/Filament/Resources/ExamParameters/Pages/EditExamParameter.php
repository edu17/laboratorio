<?php

namespace App\Filament\Resources\ExamParameters\Pages;

use App\Filament\Resources\ExamParameters\ExamParameterResource;
use App\Models\ExamParameter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExamParameter extends EditRecord
{
    protected static string $resource = ExamParameterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewExam')
                ->label('Vista previa del examen')
                ->tooltip('Guarda el parámetro antes de actualizar la vista previa.')
                ->url(fn (ExamParameter $record): string => route('exams.preview', $record->section->exam_id))
                ->openUrlInNewTab(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
