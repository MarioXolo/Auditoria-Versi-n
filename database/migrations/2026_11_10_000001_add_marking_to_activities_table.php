<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Quién marcó la actividad como completada o incompleta (siempre un superior), cuándo y por qué
            $table->foreignId('marked_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable()->after('marked_by');
            $table->text('mark_notes')->nullable()->after('marked_at');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marked_by');
            $table->dropColumn(['marked_at', 'mark_notes']);
        });
    }
};
