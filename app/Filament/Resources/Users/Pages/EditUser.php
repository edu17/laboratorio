<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function beforeSave(): void
    {
        if ($this->getRecord()->is(auth()->user()) && ! $this->data['is_active']) {
            throw ValidationException::withMessages(['data.is_active' => 'No puedes desactivar tu propio acceso.']);
        }

        if ($this->getRecord()->is(auth()->user()) && auth()->user()->can('Manage:Role')) {
            $currentRoles = $this->getRecord()->roles->modelKeys();
            $selectedRoles = array_map('intval', $this->data['roles'] ?? []);
            sort($currentRoles);
            sort($selectedRoles);

            if ($currentRoles !== $selectedRoles) {
                throw ValidationException::withMessages(['data.roles' => 'Otro administrador debe modificar tus roles.']);
            }
        }
    }
}
