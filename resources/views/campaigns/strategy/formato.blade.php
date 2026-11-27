@php
    $tipo = $book['formato'] === 'barda' ? 'barda' : 'lona';
    $d = fn (string $k) => $record?->dato($k);
    $line = fn ($v, $w = 'min-w-64') => $v ? e($v) : '<span class="blank '.$w.'"></span>';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Formato de autorización · {{ ucfirst($tipo) }}</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; color: #111; max-width: 760px; margin: 2rem auto; padding: 0 1.25rem; line-height: 1.6; }
        h1 { font-size: 1.25rem; text-align: center; margin: 0 0 .25rem; }
        .sub { text-align: center; color: #555; font-size: .9rem; margin-bottom: 2rem; }
        .blank { display: inline-block; border-bottom: 1px solid #111; height: 1.1em; vertical-align: bottom; }
        .min-w-64 { min-width: 16rem; } .min-w-40 { min-width: 10rem; } .min-w-24 { min-width: 6rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0 1.5rem; }
        td { border: 1px solid #999; padding: .45rem .6rem; vertical-align: top; }
        td:first-child { width: 34%; color: #444; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 4rem; text-align: center; }
        .firmas div { border-top: 1px solid #111; padding-top: .4rem; font-size: .9rem; }
        .nota { font-size: .8rem; color: #555; margin-top: 2.5rem; }
        .acciones { text-align: center; margin-bottom: 1.5rem; font-family: system-ui, sans-serif; }
        .acciones button { background: #1d4ed8; color: #fff; border: 0; border-radius: .5rem; padding: .6rem 1.2rem; font-size: 1rem; cursor: pointer; }
        @media print { .acciones { display: none; } body { margin: 0 auto; } }
    </style>
</head>
<body>
    <div class="acciones"><button onclick="window.print()">Imprimir o guardar en PDF</button></div>

    <h1>Autorización para colocar propaganda en {{ $tipo === 'barda' ? 'barda' : 'lona' }}</h1>
    <p class="sub">{{ $campaign->name }} · {{ $campaign->municipality }}</p>

    <p>En {{ $campaign->municipality }}, a <span class="blank min-w-24"></span> de <span class="blank min-w-40"></span> de 20<span class="blank min-w-24" style="min-width:2rem"></span>.</p>

    <p>Quien suscribe, {!! $line($d('propietario')) !!}, en mi carácter de propietario(a) o poseedor(a) del inmueble descrito abajo, autorizo de manera libre y gratuita {{ $tipo === 'barda' ? 'la pinta de propaganda en la barda' : 'la colocación de lona(s) de propaganda' }} del inmueble, en los términos siguientes:</p>

    <table>
        <tr><td>Ubicación</td><td>{!! $line($d('direccion')) !!}</td></tr>
        <tr><td>Comunidad / sección</td><td>{!! $line(trim(($record?->community?->name ?? '').($record?->section ? ' · Sección '.$record->section->clave : ''))) !!}</td></tr>
        <tr><td>{{ $tipo === 'barda' ? 'Medidas de la barda' : 'Medida y cantidad de lonas' }}</td><td>{!! $line($tipo === 'barda' ? $d('medidas') : trim(($d('medida') ?? '').($d('cantidad') ? ' · '.$d('cantidad').' pieza(s)' : ''))) !!}</td></tr>
        <tr><td>Vigencia</td><td>Desde {!! $line($record?->fecha?->format('d/m/Y'), 'min-w-24') !!} hasta {!! $line(($tipo === 'barda' ? $d('fecha_borrado') : $d('fecha_retiro')) ? \Carbon\Carbon::parse($tipo === 'barda' ? $d('fecha_borrado') : $d('fecha_retiro'))->format('d/m/Y') : null, 'min-w-24') !!}</td></tr>
        <tr><td>Teléfono de contacto</td><td>{!! $line($d('telefono')) !!}</td></tr>
    </table>

    <p>Al terminar la vigencia, la campaña se compromete a {{ $tipo === 'barda' ? 'borrar o blanquear la barda' : 'retirar la(s) lona(s)' }} y dejar el inmueble en las condiciones en que se recibió.</p>

    <div class="firmas">
        <div>Nombre y firma del propietario(a)</div>
        <div>Nombre y firma de quien recibe por la campaña</div>
    </div>

    <p class="nota">Anexar copia de identificación del propietario(a). Revisar este formato con el área jurídica antes de usarlo.</p>
</body>
</html>
