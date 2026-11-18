<?php

namespace App\Services;

use App\Models\Minute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lee una minuta en el formato de Excel del equipo (5 secciones) y la guarda.
 * Las secciones se ubican por su encabezado («2. CONTROL DE PARTICIPANTES»…), no por
 * número de fila, porque cada archivo tiene más o menos renglones. No usa librerías:
 * un .xlsx es un ZIP con XML.
 */
class MinuteExcelImporter
{
    private const SECTIONS = [
        'INFORMACI' => 'general',
        'PARTICIPANTES' => 'participantes',
        'PUNTOS TRATADOS' => 'puntos',
        'ACUERDOS' => 'acuerdos',
        'COMPROMISOS' => 'compromisos',
    ];

    /** @return array{minute: Minute, duplicate: bool} */
    public function import(string $path, string $originalName, ?int $userId): array
    {
        $data = $this->parse($path);

        $existing = Minute::whereDate('fecha', $data['fecha'])
            ->where('titulo', $data['titulo'])
            ->where('hora_inicio', $data['hora_inicio'])
            ->first();
        if ($existing) {
            return ['minute' => $existing, 'duplicate' => true];
        }

        $minute = DB::transaction(function () use ($data, $originalName, $userId) {
            $minute = Minute::create([
                'titulo' => $data['titulo'],
                'tema' => $data['tema'],
                'objetivo' => $data['objetivo'],
                'fecha' => $data['fecha'],
                'hora_inicio' => $data['hora_inicio'],
                'hora_fin' => $data['hora_fin'],
                'lugar' => $data['lugar'],
                'archivo_origen' => mb_substr($originalName, 0, 200),
                'created_by' => $userId,
            ]);
            foreach ($data['participantes'] as $i => $p) {
                $minute->participants()->create($p + ['orden' => $i + 1]);
            }
            foreach (['puntos' => 'punto', 'acuerdos' => 'acuerdo'] as $key => $tipo) {
                foreach ($data[$key] as $i => $texto) {
                    $minute->items()->create(['tipo' => $tipo, 'orden' => $i + 1, 'texto' => $texto]);
                }
            }
            foreach ($data['compromisos'] as $i => $c) {
                $minute->commitments()->create($c + ['orden' => $i + 1]);
            }

            return $minute;
        });

        return ['minute' => $minute, 'duplicate' => false];
    }

    /** Convierte el Excel en un arreglo con las 5 secciones. */
    public function parse(string $path): array
    {
        $rows = $this->readFirstSheet($path);

        $m = ['titulo' => null, 'tema' => null, 'objetivo' => null, 'fecha' => null, 'hora_inicio' => null, 'hora_fin' => null,
            'lugar' => null, 'participantes' => [], 'puntos' => [], 'acuerdos' => [], 'compromisos' => []];
        $section = null;

        foreach ($rows as $r => $cells) {
            if ($r === 2 && isset($cells['A'])) {
                $m['titulo'] = $this->text($cells['A']);
                continue;
            }
            $a = $this->text($cells['A'] ?? null) ?? '';

            if (count($cells) === 1 && preg_match('/^\s*\d\.\s/u', $a) && $a === mb_strtoupper($a)) {
                foreach (self::SECTIONS as $needle => $key) {
                    if (str_contains($a, $needle)) {
                        $section = $key;
                        continue 2;
                    }
                }
            }

            switch ($section) {
                case 'general':
                    $label = mb_strtolower($a);
                    if (str_starts_with($label, 'tema')) {
                        $m['tema'] = $this->text($cells['B'] ?? null);
                    } elseif (str_starts_with($label, 'objetivo')) {
                        $m['objetivo'] = $this->text($cells['B'] ?? null);
                    } elseif (str_starts_with($label, 'fecha')) {
                        $m['fecha'] = $this->date($cells['B'] ?? null);
                        $m['hora_inicio'] = $this->time($cells['D'] ?? null);
                        $m['hora_fin'] = $this->time($cells['F'] ?? null);
                    } elseif (str_starts_with($label, 'lugar')) {
                        $m['lugar'] = $this->text($cells['B'] ?? null);
                    }
                    break;

                case 'participantes':
                    if (str_starts_with($a, 'Nombre')) {
                        break;
                    }
                    $nombre = $this->text($cells['A'] ?? null) ?? $this->text($cells['B'] ?? null);
                    if ($nombre) {
                        $mod = $this->text($cells['E'] ?? null);
                        $m['participantes'][] = [
                            'nombre' => mb_substr($nombre, 0, 150),
                            'puesto' => $this->text($cells['C'] ?? null),
                            'asistio' => filled($this->text($cells['D'] ?? null)),
                            'modalidad' => $mod ? mb_substr($mod, 0, 20) : null,
                        ];
                    }
                    break;

                case 'puntos':
                case 'acuerdos':
                    // El número del Excel («3. …») se quita: la lista se numera sola por su orden
                    $texto = trim(implode(' ', array_filter(array_map(fn ($c) => $this->text($c), $cells))));
                    $texto = trim(preg_replace('/^\d+\s*[.)-]\s*/u', '', $texto));
                    if ($texto !== '') {
                        $m[$section][] = $texto;
                    }
                    break;

                case 'compromisos':
                    if ($a === 'ID') {
                        break;
                    }
                    $actividad = $this->text($cells['B'] ?? null) ?? $this->text($cells['C'] ?? null);
                    $limite = $cells['E'] ?? null;
                    $c = [
                        'actividad' => $actividad,
                        'responsable' => $this->text($cells['D'] ?? null),
                        'fecha_limite' => is_numeric($limite) ? $this->date($limite) : $this->text($limite),
                        'estatus' => $this->status($this->text($cells['F'] ?? null)),
                    ];
                    // Las filas que solo traen el número de plantilla («3. Pendiente») se descartan
                    if ($c['actividad'] || $c['responsable'] || $c['fecha_limite'] || $c['estatus']) {
                        $c['actividad'] ??= '(sin descripción)';
                        $m['compromisos'][] = $c;
                    }
                    break;
            }
        }

        if (! $m['fecha']) {
            throw new RuntimeException('No se encontró la fecha de la reunión (renglón «Fecha (DD/MM/AAAA):»).');
        }
        $m['titulo'] = $m['titulo'] ? preg_replace('/\s+/u', ' ', $m['titulo']) : Minute::defaultTitle($m['fecha']);

        return $m;
    }

    /** @return array<int, array<string, string|float>> renglón => [columna => valor] */
    private function readFirstSheet(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('El archivo no es un Excel (.xlsx) válido.');
        }

        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach ($this->xml($xml)->si as $si) {
                    $shared[] = $this->inlineText($si);
                }
            }

            $sheetPath = $this->firstSheetPath($zip);
            $sheet = $zip->getFromName($sheetPath);
            if ($sheet === false) {
                throw new RuntimeException('El Excel no tiene hojas.');
            }

            $rows = [];
            foreach ($this->xml($sheet)->sheetData->row as $row) {
                foreach ($row->c as $c) {
                    preg_match('/^([A-Z]+)(\d+)$/', (string) $c['r'], $ref);
                    if (! $ref) {
                        continue;
                    }
                    $type = (string) $c['t'];
                    $value = match ($type) {
                        's' => $shared[(int) $c->v] ?? '',
                        'inlineStr' => $this->inlineText($c->is),
                        'str', 'b', 'e' => (string) $c->v,
                        default => isset($c->v) ? (float) $c->v : null,
                    };
                    if ($value !== null && $value !== '' && ! (is_string($value) && trim($value) === '')) {
                        $rows[(int) $ref[2]][$ref[1]] = $value;
                    }
                }
            }
            ksort($rows);

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook && $rels) {
            $wb = $this->xml($workbook);
            $first = $wb->sheets->sheet[0] ?? null;
            if ($first) {
                $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($this->xml($rels)->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');

                        return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function xml(string $content): SimpleXMLElement
    {
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            throw new RuntimeException('El Excel está dañado.');
        }

        return $xml;
    }

    private function inlineText(SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $out = '';
        foreach ($node->r as $run) {
            $out .= (string) $run->t;
        }

        return $out;
    }

    private function text(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (is_float($v)) {
            $v = floor($v) == $v ? (string) (int) $v : (string) $v;
        }
        $v = trim(preg_replace('/[ \t]+/u', ' ', (string) $v));

        return $v === '' ? null : $v;
    }

    /** Fecha: número de serie de Excel o texto dd/mm/aaaa. Devuelve AAAA-MM-DD. */
    private function date(mixed $v): ?string
    {
        if (is_float($v)) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor($v))->toDateString();
        }
        $t = $this->text($v);
        if ($t && preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})#', $t, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if ($t && preg_match('/^\d{4}-\d{2}-\d{2}/', $t)) {
            return substr($t, 0, 10);
        }

        return $t;
    }

    /** Hora: fracción de día de Excel o textos como «16:00 pm», «14:00:00 p. m.». Devuelve HH:MM. */
    private function time(mixed $v): ?string
    {
        if (is_float($v)) {
            $minutes = (int) round(fmod($v, 1) * 1440);

            return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
        }
        $t = mb_strtolower((string) $this->text($v));
        if (! preg_match('/(\d{1,2}):(\d{2})/', $t, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $pm = (bool) preg_match('/p\.?\s*m/u', $t);
        if ($pm && $h < 12) {
            $h += 12;
        } elseif (! $pm && preg_match('/a\.?\s*m/u', $t) && $h === 12) {
            $h = 0;
        }

        return sprintf('%02d:%s', $h % 24, $m[2]);
    }

    private function status(?string $s): ?string
    {
        if (! $s) {
            return null;
        }
        foreach (Minute::ESTATUS as $known) {
            if (mb_strtolower($known) === mb_strtolower($s)) {
                return $known;
            }
        }

        return mb_substr($s, 0, 30);
    }
}
