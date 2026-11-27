<?php

use App\Services\StrategyBooks;
use App\Support\Access;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puntos 12 a 18:
 * - Catálogos que se amplían desde los formularios (cuartos de guerra, sectores, pilares, personas monitoreadas).
 * - Registro de quién consulta o exporta Estadísticas (punto 16).
 * - Los perfiles que vienen de fábrica reciben los permisos de los módulos nuevos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('catalog', 30);
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['catalog', 'name']);
        });

        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module', 30);
            $table->string('action', 30);
            $table->string('detail', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['module', 'created_at']);
        });

        $now = now();
        foreach (StrategyBooks::CATALOGS as $catalog => [, $items]) {
            foreach ($items as $name) {
                DB::table('strategy_catalog_items')->insert(['catalog' => $catalog, 'name' => $name, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // Perfiles de fábrica: agregar los módulos nuevos sin tocar lo que ya se haya ajustado
        foreach (Access::defaults() as [$name, , $permisos]) {
            $row = DB::table('access_profiles')->where('name', $name)->first();
            if (! $row) {
                continue;
            }
            $current = json_decode($row->permisos, true) ?: [];
            foreach (Access::NEW_IN_V5 as $module) {
                if (isset($permisos[$module]) && ! isset($current[$module])) {
                    $current[$module] = $permisos[$module];
                }
            }
            DB::table('access_profiles')->where('id', $row->id)->update(['permisos' => json_encode($current), 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
        Schema::dropIfExists('strategy_catalog_items');
    }
};
