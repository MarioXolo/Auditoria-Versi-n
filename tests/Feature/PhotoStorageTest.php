<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use App\Services\PhotoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

class PhotoStorageTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    public function test_large_photos_are_resized_and_converted_to_jpeg(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Requiere la extensión GD (en el servidor sí está instalada).');
        }

        Storage::fake('local');
        $photo = UploadedFile::fake()->image('foto-celular.png', 4000, 3000);

        $path = PhotoStorage::store($photo, 'evidencias');

        $this->assertStringEndsWith('.jpg', $path);
        $stored = Storage::disk('local')->get($path);
        [$w, $h] = getimagesizefromstring($stored);
        $this->assertSame([1600, 1200], [$w, $h]);
        $this->assertLessThan($photo->getSize(), strlen($stored));
    }

    public function test_files_that_cannot_be_compressed_are_kept_as_is(): void
    {
        Storage::fake('local');
        $path = PhotoStorage::store(UploadedFile::fake()->create('foto.heic', 300, 'image/heic'), 'evidencias');

        $this->assertStringStartsWith('evidencias/', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_object_storage_disk_is_used_when_configured(): void
    {
        Storage::fake('s3');
        config(['auditoria.archivos.disco' => 's3']);

        $encargado = User::factory()->role('encargado')->create();
        $team = Team::create(['name' => 'Norte', 'leader_id' => $encargado->id]);
        $encargado->update(['team_id' => $team->id]);
        $empleado = User::factory()->create(['team_id' => $team->id]);

        $this->actingAs($encargado)->post('/activities', $this->payload([
            'assigned_to' => $empleado->id,
            'evidence' => UploadedFile::fake()->create('foto.jpg', 200, 'image/jpeg'),
        ]))->assertRedirect();

        $activity = Activity::latest('id')->first();
        Storage::disk('s3')->assertExists($activity->evidence_path);
        $this->actingAs($empleado)->get("/activities/{$activity->id}/evidence")->assertOk();
    }
}
