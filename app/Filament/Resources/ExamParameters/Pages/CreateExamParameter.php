<?php

namespace App\Filament\Resources\ExamParameters\Pages;

use App\Filament\Resources\ExamParameters\ExamParameterResource;
use App\Models\ExamSection;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateExamParameter extends CreateRecord
{
    protected static string $resource = ExamParameterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewExam')
                ->label('Vista previa del examen')
                ->tooltip('Muestra únicamente los parámetros ya guardados del examen seleccionado.')
                ->visible(fn (): bool => filled($this->data['exam_section_id'] ?? null))
                ->url(fn (): string => route('exams.preview', ExamSection::query()->findOrFail($this->data['exam_section_id'])->exam_id))
                ->openUrlInNewTab(),
        ];
    }
}
