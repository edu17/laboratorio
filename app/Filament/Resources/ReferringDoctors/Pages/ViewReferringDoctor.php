<?php

namespace App\Filament\Resources\ReferringDoctors\Pages;

use App\Filament\Resources\ReferringDoctors\ReferringDoctorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReferringDoctor extends ViewRecord
{
    protected static string $resource = ReferringDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
