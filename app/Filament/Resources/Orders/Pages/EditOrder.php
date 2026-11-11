<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\UpdateLabOrder;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\OrderExam;
use App\Models\Patient;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['exam_ids'] = $this->getRecord()
            ->orderExams()
            ->whereNotIn('status', [OrderExam::STATUS_CANCELED, OrderExam::STATUS_WITHDRAWN])
            ->orderBy('exam_id')
            ->pluck('exam_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateLabOrder::class)->handle(
            $record,
            Patient::query()->findOrFail($data['patient_id']),
            $data['exam_ids'],
            $data['notes'] ?? null,
            auth()->user(),
            $data['referring_doctor'] ?? null,
        );
    }
}
