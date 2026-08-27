<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin {email : Correo del administrador} {--name=Administrador : Nombre visible}';

    protected $description = 'Crea la cuenta administrativa inicial sin exponer la contraseña en el historial del shell';

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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'email' => $data['email'],
            'name' => $data['name'],
            'password' => Hash::make($data['password']),
        ]);

        $this->info('Administrador creado correctamente.');

        return self::SUCCESS;
    }
}
