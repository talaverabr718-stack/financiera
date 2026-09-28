<?php

namespace App\Console\Commands;

use App\Models\SystemRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin {email : Correo del administrador} {--name=Administrador : Nombre visible}';

    protected $description = 'Crea de forma interactiva la primera cuenta administrativa, sin exponer la contraseña en el shell';

    public function handle(): int
    {
        $password = $this->secret('Contraseña (mínimo 12 caracteres)');
        $confirmation = $this->secret('Confirma la contraseña');
        $data = [
            'email' => $this->argument('email'),
            'name' => $this->option('name'),
            'password' => $password,
            'password_confirmation' => $confirmation,
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($data): void {
                if (User::query()->lockForUpdate()->exists()) {
                    throw new \RuntimeException('Ya existe una cuenta de usuario. El primer administrador solo puede crearse en una instalación vacía.');
                }
                $role = SystemRole::query()->where('key', 'administrator')->where('is_active', true)->lockForUpdate()->first();
                if (! $role) {
                    throw new \RuntimeException('No existe el rol Administrador. Ejecuta las migraciones antes de crear la cuenta inicial.');
                }

                User::query()->create([
                    'system_role_id' => $role->id,
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                    'is_active' => true,
                ]);
            });
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Primer administrador creado correctamente.');

        return self::SUCCESS;
    }
}
