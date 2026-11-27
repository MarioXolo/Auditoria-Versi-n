<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La Agenda del Presidente deja de usar el catálogo compartido de tipos de actividad:
 * tiene su propia lista privada, para que lo que se escriba ahí no lo vean los demás
 * y lo que escriben los demás no aparezca ahí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('president_activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('president_activities', function (Blueprint $table) {
            $table->foreignId('president_type_id')->nullable()->after('folio_seq')->constrained('president_activity_types')->restrictOnDelete();
        });

        // Las actividades que ya existan conservan su tipo, ahora en la lista privada
        $now = now();
        foreach (DB::table('president_activities')->join('activity_types', 'activity_types.id', '=', 'president_activities.activity_type_id')
            ->select('president_activities.id', 'activity_types.name')->get() as $row) {
            $typeId = DB::table('president_activity_types')->where('name', $row->name)->value('id')
                ?? DB::table('president_activity_types')->insertGetId(['name' => $row->name, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('president_activities')->where('id', $row->id)->update(['president_type_id' => $typeId]);
        }

        Schema::table('president_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('president_activities', function (Blueprint $table) {
            $table->foreignId('activity_type_id')->nullable()->constrained('activity_types')->restrictOnDelete();
            $table->dropConstrainedForeignId('president_type_id');
        });
        Schema::dropIfExists('president_activity_types');
    }
};
