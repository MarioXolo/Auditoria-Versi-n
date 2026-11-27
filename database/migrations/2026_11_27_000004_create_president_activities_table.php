<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda del Presidente: actividades con los mismos campos que «Actividades», pero en su propia
 * tabla. No se mezclan con las actividades de los demás usuarios ni con sus dashboards, calendario o métricas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('president_activities', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();               // AP-0001
            $table->unsignedInteger('folio_seq')->default(0);
            $table->foreignId('activity_type_id')->constrained('activity_types')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('no_iniciado')->index();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('evidence_path')->nullable();
            $table->text('evidence_text')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->string('mark_notes', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('president_activities');
    }
};
