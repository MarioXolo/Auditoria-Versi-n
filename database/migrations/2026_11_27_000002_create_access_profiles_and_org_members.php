<?php

use App\Support\Access;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Punto 19 · Organigrama y roles:
 * - Perfiles de acceso con permisos de ver/capturar/revisar/exportar por módulo (y «solo sus votantes»).
 * - Perfil en cada usuario; el promotor se liga con su cuenta.
 * - Personas del organigrama (no todas necesitan cuenta), con a quién reportan y si son servidores públicos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('description', 255)->nullable();
            $table->json('permisos');
            $table->boolean('solo_sus_votantes')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('access_profile_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::table('campaign_promoters', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('coordinador')->constrained()->nullOnDelete();
        });

        Schema::create('org_members', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('puesto', 150);
            $table->foreignId('reporta_a_id')->nullable()->constrained('org_members')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delegacion', 120)->nullable();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('telefono', 30)->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('servidor_publico')->default(false);
            $table->string('notas', 500)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach (Access::defaults() as [$name, $desc, $permisos, $solo]) {
            DB::table('access_profiles')->insert([
                'name' => $name, 'description' => $desc, 'permisos' => json_encode($permisos),
                'solo_sus_votantes' => $solo, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('org_members');
        Schema::table('campaign_promoters', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('access_profile_id'));
        Schema::dropIfExists('access_profiles');
    }
};
