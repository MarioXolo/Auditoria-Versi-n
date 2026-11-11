<?php

use App\Services\PhotoStorage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
 * Pasa las fotos guardadas en el disco del servidor al disco configurado
 * en ARCHIVOS_DISCO (por ejemplo, Object Storage). Se puede correr varias veces.
 */
Artisan::command('auditoria:mover-archivos {--borrar : Borra del servidor lo que ya se copió}', function () {
    $target = PhotoStorage::diskName();
    if ($target === 'local') {
        $this->error('ARCHIVOS_DISCO es «local»: no hay a dónde mover. Configura el Object Storage primero.');

        return 1;
    }

    $local = Storage::disk('local');
    $remote = PhotoStorage::disk();
    $files = array_merge($local->allFiles('evidencias'), $local->allFiles('asistencia'));
    $copied = 0;

    foreach ($files as $path) {
        if (! $remote->exists($path)) {
            $remote->writeStream($path, $local->readStream($path));
            $copied++;
        }
        if ($this->option('borrar') && $remote->exists($path)) {
            $local->delete($path);
        }
    }

    $this->info("Archivos revisados: ".count($files).". Copiados a «{$target}»: {$copied}.");
})->purpose('Mueve evidencias y fotos de asistencia al disco configurado');

/*
 * Sube un respaldo de la base al Object Storage (carpeta respaldos/) y borra
 * los de más de N días. Lo usa deploy/backup.sh.
 */
Artisan::command('auditoria:subir-respaldo {archivo} {--dias=30}', function (string $archivo) {
    if (PhotoStorage::diskName() === 'local') {
        $this->line('ARCHIVOS_DISCO es «local»: el respaldo se queda solo en el servidor.');

        return 0;
    }

    $disk = PhotoStorage::disk();
    $disk->writeStream('respaldos/'.basename($archivo), fopen($archivo, 'r'));

    $limit = now()->subDays((int) $this->option('dias'))->getTimestamp();
    foreach ($disk->files('respaldos') as $old) {
        if ($disk->lastModified($old) < $limit) {
            $disk->delete($old);
        }
    }

    $this->info('Respaldo subido: respaldos/'.basename($archivo));
})->purpose('Sube un respaldo de la base al Object Storage');
