<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semáforo de votos por sección: cada sección guarda su lista nominal oficial, su
 * representante general y todas las comunidades que abarca (una comunidad puede caer en varias).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedInteger('listado_nominal')->nullable()->after('community_id');
            $table->string('representante', 120)->nullable()->after('listado_nominal');
        });

        Schema::create('community_section', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->unique(['section_id', 'community_id']);
        });

        // La comunidad que ya tenía cada sección pasa a ser una de sus comunidades
        foreach (DB::table('sections')->whereNotNull('community_id')->get(['id', 'community_id']) as $s) {
            DB::table('community_section')->insert(['section_id' => $s->id, 'community_id' => $s->community_id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_section');
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['listado_nominal', 'representante']);
        });
    }
};
