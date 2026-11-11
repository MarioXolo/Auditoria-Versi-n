<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V2 · etapa 2 del Cuaderno Maestro de Campaña: pestañas 5 (Mapeo de Actores),
 * 6 (Defensa del Voto), 7 (Presupuesto), 8 (Cronograma) y 10 (Logros Comunicables).
 * Solo agrega tablas y una columna; no toca datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->decimal('tope_gastos', 14, 2)->default(0)->after('votantes_por_promotor');
        });

        Schema::create('campaign_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('tipo', 120)->nullable();
            $table->foreignId('community_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('postura', 20)->nullable();
            $table->unsignedInteger('votos_influye')->default(0);
            $table->boolean('contactado')->default(false);
            $table->string('responsable', 150)->nullable();
            $table->string('notas', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_polling_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('seccion', 10);
            $table->string('casilla', 20);
            $table->string('rc_propietario', 150)->nullable();
            $table->string('tel_propietario', 30)->nullable();
            $table->string('rc_suplente', 150)->nullable();
            $table->string('tel_suplente', 30)->nullable();
            $table->boolean('acreditado')->default(false);
            $table->boolean('capacitado')->default(false);
            $table->string('rg_zona', 120)->nullable();
            $table->boolean('reporto_acta')->default(false);
            $table->timestamps();
            $table->unique(['campaign_id', 'seccion', 'casilla']);
        });

        Schema::create('campaign_budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('rubro', 150);
            $table->decimal('porcentaje', 5, 4)->default(0);
            $table->decimal('asignado', 14, 2)->default(0);
            $table->decimal('ejercido', 14, 2)->default(0);
            $table->string('notas', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('fase', 120)->nullable();
            $table->string('tarea', 255);
            $table->string('area', 120)->nullable();
            $table->date('fecha_limite')->nullable();
            $table->string('nota_fecha', 255)->nullable();
            $table->string('estatus', 20)->default('Pendiente');
            $table->timestamps();
        });

        Schema::create('campaign_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('logro', 255);
            $table->string('pilar', 40)->nullable();
            $table->string('lugar', 150)->nullable();
            $table->string('beneficiados', 120)->nullable();
            $table->decimal('recurso', 14, 2)->nullable();
            $table->boolean('evidencia')->default(false);
            $table->string('frase', 500)->nullable();
            $table->timestamps();
        });

        // Las campañas que ya existen reciben los rubros sugeridos del Excel (en ceros)
        $now = now();
        foreach (DB::table('campaigns')->pluck('id') as $campaignId) {
            foreach (\App\Models\Campaign::DEFAULT_BUDGET as $item) {
                DB::table('campaign_budget_items')->insert($item + ['campaign_id' => $campaignId, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_achievements');
        Schema::dropIfExists('campaign_tasks');
        Schema::dropIfExists('campaign_budget_items');
        Schema::dropIfExists('campaign_polling_stations');
        Schema::dropIfExists('campaign_actors');
        Schema::table('campaigns', fn (Blueprint $table) => $table->dropColumn('tope_gastos'));
    }
};
