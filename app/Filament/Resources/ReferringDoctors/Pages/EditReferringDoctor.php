<?php

namespace App\Filament\Resources\ReferringDoctors\Pages;

use App\Filament\Resources\ReferringDoctors\ReferringDoctorResource;
use Filament\Resources\Pages\EditRecord;

class EditReferringDoctor extends EditRecord
{
    protected static string $resource = ReferringDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
