<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\CreateLabOrder;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Patient;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateLabOrder::class)->handle(
            Patient::query()->findOrFail($data['patient_id']),
            $data['exam_ids'],
            auth()->user(),
            $data['notes'] ?? null,
            $data['referring_doctor'] ?? null,
            $data['requested_parameter_codes'] ?? [],
        );
    }
}
