<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos que administran el admin y los gerentes:
 * tipos de actividad y comunidades.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['activity_types', 'communities'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name', 120)->unique();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('communities');
        Schema::dropIfExists('activity_types');
    }
};
