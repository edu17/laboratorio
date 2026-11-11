<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Actions\Exams\PublishExamCatalogVersion;
use App\Filament\Resources\Exams\ExamResource;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
            Action::make('publishCatalogVersion')
                ->label('Publicar versión de catálogo')
                ->visible(fn (Exam $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->requiresConfirmation()
                ->action(function (Exam $record): void {
                    $version = app(PublishExamCatalogVersion::class)->handle($record, auth()->user());

                    Notification::make()
                        ->success()
                        ->title('Versión '.$version->version_number.' publicada')
                        ->send();
                }),
        ];
    }
}
