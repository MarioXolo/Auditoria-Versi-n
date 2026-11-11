<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V2 · Cuaderno Maestro de Campaña (etapa 1).
 * Cada campaña tiene su propia meta de votos, estructura territorial,
 * promotores, base de votantes e indicadores capturados a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('municipality', 120);
            $table->date('election_date')->nullable();
            // Calculadora de meta de votos (pestaña «Meta de Votos»)
            $table->unsignedInteger('listado_nominal')->default(0);
            $table->decimal('participacion', 5, 4)->default(0.55);
            $table->decimal('objetivo', 5, 4)->default(0.50);
            $table->decimal('colchon', 5, 4)->default(0.10);
            $table->unsignedSmallInteger('votantes_por_promotor')->default(10);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campaign_territories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->restrictOnDelete();
            $table->string('responsable', 150)->nullable();
            $table->unsignedInteger('listado_nominal')->default(0);
            $table->unsignedInteger('meta_votos')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'community_id']);
        });

        Schema::create('campaign_promoters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->foreignId('community_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('secciones', 120)->nullable();
            $table->string('coordinador', 150)->nullable();
            $table->unsignedInteger('meta_votos')->default(10);
            $table->boolean('verificado')->default(false);
            $table->timestamps();
            $table->index(['campaign_id', 'community_id']);
        });

        Schema::create('campaign_voters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('seccion', 10)->nullable();
            $table->string('casilla', 10)->nullable();
            $table->string('domicilio', 255)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->foreignId('community_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('promoter_id')->nullable()->constrained('campaign_promoters')->nullOnDelete();
            $table->string('afinidad', 20)->nullable();
            $table->boolean('recibe_apoyo')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['campaign_id', 'community_id']);
            $table->index(['campaign_id', 'promoter_id']);
            $table->index(['campaign_id', 'seccion']);
        });

        Schema::create('campaign_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->decimal('meta', 14, 4)->default(0);
            $table->decimal('valor', 14, 4)->default(0);
            $table->boolean('es_porcentaje')->default(false);
            $table->string('fecha_corte', 60)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_kpis');
        Schema::dropIfExists('campaign_voters');
        Schema::dropIfExists('campaign_promoters');
        Schema::dropIfExists('campaign_territories');
        Schema::dropIfExists('campaigns');
    }
};
