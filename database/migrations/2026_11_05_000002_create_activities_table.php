<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            // Folio por equipo: ID{número de equipo}.{secuencia}, ej. ID1.0001. Equipo 0 = sin equipo.
            $table->string('folio', 20)->unique();
            $table->unsignedTinyInteger('folio_group')->default(0);
            $table->unsignedInteger('folio_seq')->default(0);
            $table->foreignId('activity_type_id')->constrained('activity_types')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('status', 20)->default('no_iniciado')->index();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('evidence_path')->nullable();
            $table->text('evidence_text')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['folio_group', 'folio_seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
