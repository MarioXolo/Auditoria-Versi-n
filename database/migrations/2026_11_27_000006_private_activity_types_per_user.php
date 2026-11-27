<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de actividad privados: lo que alguien escribe en «Otro…» queda solo para esa persona
 * (owner_id) y no aparece en la lista de los demás. La lista común (owner_id nulo) la
 * administran admin y gerentes en Catálogos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('name')->constrained('users')->restrictOnDelete();
            $table->dropUnique(['name']);
            $table->unique(['name', 'owner_id']);
        });

        // Los tipos que ya se habían escrito con «Otro…» se vuelven privados de quien los escribió:
        // se reconocen porque se crearon al mismo tiempo que su primera actividad y solo esa persona los ha usado.
        foreach (DB::table('activity_types')->get() as $type) {
            $acts = DB::table('activities')->where('activity_type_id', $type->id)->orderBy('created_at')->get(['created_by', 'created_at']);
            if ($acts->isEmpty() || $acts->pluck('created_by')->unique()->count() !== 1) {
                continue;
            }
            $first = \Carbon\Carbon::parse($acts->first()->created_at);
            $created = \Carbon\Carbon::parse($type->created_at);
            if (abs($first->diffInSeconds($created, false)) <= 10) {
                DB::table('activity_types')->where('id', $type->id)->update(['owner_id' => $acts->first()->created_by]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropUnique(['name', 'owner_id']);
            $table->dropConstrainedForeignId('owner_id');
            $table->unique('name');
        });
    }
};
