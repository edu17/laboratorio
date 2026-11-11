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
        $activeOrderExams = $this->getRecord()
            ->orderExams()
            ->whereNotIn('status', [OrderExam::STATUS_CANCELED, OrderExam::STATUS_WITHDRAWN])
            ->orderBy('exam_id')
            ->get();

        $data['exam_ids'] = $activeOrderExams
            ->map(fn (OrderExam $orderExam): int => (int) $orderExam->exam_id)
            ->all();
        $data['requested_parameter_codes'] = $activeOrderExams
            ->mapWithKeys(fn (OrderExam $orderExam): array => [
                (int) $orderExam->exam_id => $orderExam->requested_parameter_codes
                    ?? array_keys(OrderExam::requestableParameters($orderExam->catalog_snapshot ?? [])),
            ])
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
            null,
            $data['requested_parameter_codes'] ?? [],
            filled($data['referring_doctor_id'] ?? null) ? (int) $data['referring_doctor_id'] : null,
        );
    }
}
