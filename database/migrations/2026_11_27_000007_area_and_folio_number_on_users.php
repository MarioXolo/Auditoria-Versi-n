<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sin equipos: cada encargado es su propio grupo y tiene su número (ID1, ID2…) para el folio
 * de sus actividades. Cada gerente tiene un área (Coordinación, Territorial, Técnica, Agenda),
 * que es lo que ve el encargado cuando le asignan una actividad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('area', 60)->nullable()->after('role');
            $table->unsignedInteger('folio_number')->nullable()->unique()->after('area');
        });

        // Número para los encargados que ya existen, en el orden en que se crearon,
        // empezando después del número de equipo más alto para no repetir folios ya usados.
        $start = (int) max(
            (int) DB::table('teams')->max('number'),
            (int) DB::table('activities')->max('folio_group'),
        );
        foreach (DB::table('users')->where('role', 'encargado')->orderBy('id')->pluck('id') as $i => $id) {
            DB::table('users')->where('id', $id)->update(['folio_number' => $start + $i + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['folio_number']);
            $table->dropColumn(['area', 'folio_number']);
        });
    }
};
