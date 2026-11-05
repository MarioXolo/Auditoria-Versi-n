<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Crea el primer administrador con los datos de ADMIN_* en el .env.
     * Si no hay contraseña definida, genera una y la muestra en consola.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');

        if (User::where('email', $email)->exists()) {
            $this->command?->info("El administrador {$email} ya existe, no se modificó.");

            return;
        }

        $password = env('ADMIN_PASSWORD') ?: Str::password(14, symbols: false);

        User::create([
            'name' => env('ADMIN_NAME', 'Administrador'),
            'email' => $email,
            'password' => $password,
            'role' => Role::Admin->value,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Administrador creado: {$email}");

        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Contraseña generada: {$password}  (cámbiala después de entrar)");
        }
    }
}
