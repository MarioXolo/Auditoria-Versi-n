<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Validación de ubicación hecha a mano por un administrador o gerente (sin GPS), con su motivo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('ubicacion_validada_por')->nullable()->after('ubicacion_at')->constrained('users')->nullOnDelete();
            $table->timestamp('ubicacion_validada_at')->nullable()->after('ubicacion_validada_por');
            $table->string('ubicacion_nota', 500)->nullable()->after('ubicacion_validada_at');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ubicacion_validada_por');
            $table->dropColumn(['ubicacion_validada_at', 'ubicacion_nota']);
        });
    }
};
