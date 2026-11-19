<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Versión 3: el sistema queda con tres roles (Admin, Gerente y Encargado).
 * Las cuentas con rol Empleado se desactivan; sus actividades se conservan en el historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'empleado')->where('active', true)->update(['active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // No se reactivan solas: un administrador puede hacerlo desde Usuarios.
    }
};
