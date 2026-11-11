<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Guarda las fotos de evidencia y de asistencia.
 *
 * - Comprime las fotos al subirlas: lado mayor de hasta 1600 px y JPEG calidad 75
 *   (una foto de celular de 3–5 MB queda en ~200–400 KB). Corrige la rotación del celular.
 * - Si el archivo no se puede comprimir (HEIC, PDF, sin GD) se guarda tal cual.
 * - El disco se elige en el .env con ARCHIVOS_DISCO: «local» (disco del servidor)
 *   o «s3» (Object Storage). Los archivos nunca son públicos: se sirven por la app.
 */
class PhotoStorage
{
    public static function diskName(): string
    {
        return (string) config('auditoria.archivos.disco', 'local');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /** Guarda el archivo (comprimido si es foto) y regresa la ruta relativa. */
    public static function store(UploadedFile $file, string $directory): string
    {
        $jpeg = self::compress($file);

        if ($jpeg === null) {
            return $file->store($directory, self::diskName());
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.jpg';
        self::disk()->put($path, $jpeg);

        return $path;
    }

    public static function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && self::disk()->exists($path);
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            self::disk()->delete($path);
        }
    }

    public static function response(string $path): StreamedResponse
    {
        return self::disk()->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * JPEG comprimido o null si no aplica (formato no soportado, GD ausente,
     * o si el resultado no es más ligero que el original).
     */
    public static function compress(UploadedFile $file): ?string
    {
        $cfg = config('auditoria.archivos');
        if (! ($cfg['comprimir'] ?? true) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        $raw = @file_get_contents($file->getRealPath());
        $image = $raw ? @imagecreatefromstring($raw) : false;
        if (! $image) {
            return null;
        }

        $image = self::applyOrientation($image, $file->getRealPath(), $mime);

        $max = (int) ($cfg['lado_maximo'] ?? 1600);
        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) > $max) {
            $ratio = $max / max($w, $h);
            $scaled = imagescale($image, max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio)), IMG_BICUBIC);
            imagedestroy($image);
            $image = $scaled;
        }

        // PNG/WEBP con transparencia: fondo blanco para el JPEG
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);

        imageinterlace($canvas, true);
        ob_start();
        imagejpeg($canvas, null, (int) ($cfg['calidad'] ?? 75));
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        return ($jpeg !== '' && strlen($jpeg) < strlen($raw)) ? $jpeg : null;
    }

    /** Endereza fotos de celular según su etiqueta EXIF de orientación. */
    private static function applyOrientation(\GdImage $image, string $path, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
