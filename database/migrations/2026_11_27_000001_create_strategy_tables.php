<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puntos nuevos (fase 1):
 * - Catálogo de secciones electorales ligado a comunidades.
 * - Libros de «11. Estrategia de campaña» en una sola tabla con los campos comunes
 *   y los datos propios de cada libro; los eventos llevan su control para la Agenda IEEQ.
 * - Casillas: tipo (normal, especial, nueva) y datos electorales en la tabla de Defensa del Voto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 10)->unique();
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('strategy_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('libro', 40);
            // Campos comunes a todos los puntos nuevos
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->string('estatus', 20)->default('Pendiente');
            $table->decimal('costo_estimado', 14, 2)->nullable();
            $table->decimal('costo_real', 14, 2)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('datos')->nullable();
            $table->json('archivos')->nullable();
            // Agenda reportada al IEEQ (solo eventos)
            $table->boolean('es_evento')->default(false);
            $table->string('ieeq_estatus', 20)->nullable();
            $table->date('ieeq_fecha_limite')->nullable();
            $table->date('ieeq_reportado_el')->nullable();
            $table->string('ieeq_folio', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign_id', 'libro']);
            $table->index(['es_evento', 'ieeq_estatus']);
            $table->index('fecha');
        });

        Schema::table('campaign_polling_stations', function (Blueprint $table) {
            $table->string('tipo', 20)->default('Normal')->after('casilla');
            $table->string('domicilio', 255)->nullable()->after('tipo');
            $table->unsignedInteger('listado_nominal')->nullable()->after('domicilio');
            $table->unsignedInteger('votos_2024')->nullable()->after('listado_nominal');
            $table->string('motivo_nueva', 255)->nullable()->after('votos_2024');
            $table->index('tipo');
        });

        // Precarga del catálogo con las secciones que ya se capturaron en votantes y casillas
        $claves = collect()
            ->merge(DB::table('campaign_voters')->whereNotNull('seccion')->where('seccion', '!=', '')->distinct()->pluck('seccion'))
            ->merge(DB::table('campaign_polling_stations')->whereNotNull('seccion')->where('seccion', '!=', '')->distinct()->pluck('seccion'))
            ->map(fn ($c) => trim((string) $c))->filter()->unique()->sort()->values();

        $now = now();
        foreach ($claves as $clave) {
            $community = DB::table('campaign_voters')->where('seccion', $clave)->whereNotNull('community_id')
                ->groupBy('community_id')->orderByRaw('count(*) desc')->value('community_id');
            DB::table('sections')->insert(['clave' => mb_substr($clave, 0, 10), 'community_id' => $community, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('campaign_polling_stations', function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->dropColumn(['tipo', 'domicilio', 'listado_nominal', 'votos_2024', 'motivo_nueva']);
        });
        Schema::dropIfExists('strategy_records');
        Schema::dropIfExists('sections');
    }
};
