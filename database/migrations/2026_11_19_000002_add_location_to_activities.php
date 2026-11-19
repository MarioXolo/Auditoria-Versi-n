<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geolocalización opcional por actividad: un punto destino (pin + radio) y la
 * ubicación que el encargado valida desde su celular al estar en el lugar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('requiere_ubicacion')->default(false)->after('evidence_text');
            $table->decimal('destino_lat', 10, 7)->nullable()->after('requiere_ubicacion');
            $table->decimal('destino_lng', 10, 7)->nullable()->after('destino_lat');
            $table->unsignedInteger('destino_radio')->default(300)->after('destino_lng'); // metros
            $table->string('destino_nombre', 150)->nullable()->after('destino_radio');
            $table->decimal('ubicacion_lat', 10, 7)->nullable()->after('destino_nombre');
            $table->decimal('ubicacion_lng', 10, 7)->nullable()->after('ubicacion_lat');
            $table->unsignedInteger('ubicacion_precision')->nullable()->after('ubicacion_lng'); // metros
            $table->unsignedInteger('ubicacion_distancia')->nullable()->after('ubicacion_precision'); // metros al destino
            $table->timestamp('ubicacion_at')->nullable()->after('ubicacion_distancia');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['requiere_ubicacion', 'destino_lat', 'destino_lng', 'destino_radio', 'destino_nombre',
                'ubicacion_lat', 'ubicacion_lng', 'ubicacion_precision', 'ubicacion_distancia', 'ubicacion_at']);
        });
    }
};
