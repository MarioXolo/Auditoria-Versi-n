<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            // El id es el folio visible: ACT-0001, ACT-0002…
            $table->id();
            $table->foreignId('activity_type_id')->constrained('activity_types')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('status', 20)->default('pendiente')->index();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('evidence_path')->nullable();
            $table->text('evidence_text')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
