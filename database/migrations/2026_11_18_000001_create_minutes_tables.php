<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minutas de reunión, con las mismas 5 secciones del formato de Excel:
 * 1. Información general · 2. Participantes · 3. Puntos tratados · 4. Acuerdos · 5. Compromisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minutes', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->string('tema', 255)->nullable();
            $table->text('objetivo')->nullable();
            $table->date('fecha')->index();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('lugar', 200)->nullable();
            $table->string('archivo_origen', 200)->nullable(); // nombre del Excel si se importó
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('minute_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('nombre', 150);
            $table->string('puesto', 150)->nullable();
            $table->boolean('asistio')->default(true);
            $table->string('modalidad', 20)->nullable();
        });

        Schema::create('minute_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 10); // punto | acuerdo
            $table->unsignedSmallInteger('orden')->default(0);
            $table->text('texto');
            $table->index(['minute_id', 'tipo', 'orden']);
        });

        Schema::create('minute_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->text('actividad');
            $table->string('responsable', 200)->nullable();
            $table->string('fecha_limite', 100)->nullable(); // fecha (AAAA-MM-DD) o texto libre como «Primera quincena de diciembre»
            $table->string('estatus', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minute_commitments');
        Schema::dropIfExists('minute_items');
        Schema::dropIfExists('minute_participants');
        Schema::dropIfExists('minutes');
    }
};
