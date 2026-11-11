<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

#[Signature('lab:create-administrator')]
#[Description('Crear un administrador con contraseña ingresada de forma oculta')]
class CreateAdministrator extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta este comando de forma interactiva para ingresar la contraseña.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Nombre'),
            'email' => $this->ask('Correo electrónico'),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres)'),
            'password_confirmation' => $this->secret('Confirmar contraseña'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($data): void {
            if (! Role::query()->where('name', 'Administrador')->where('guard_name', 'web')->exists()) {
                (new RolesAndPermissionsSeeder)->run();
            }
            $user = User::create(collect($data)->only(['name', 'email', 'password'])->all());
            $user->assignRole('Administrador');
        });
        $this->info('Administrador creado. Ya puedes ingresar al panel.');

        return self::SUCCESS;
    }
}
