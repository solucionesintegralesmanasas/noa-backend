<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Actividad Vehicular</title>
    <style>
        @page { size: letter portrait; margin: 8mm; }
        * { margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; color: #000; background: #fff; }
        h1 { font-size: 13pt; margin-bottom: 2mm; }
        .meta { font-size: 8pt; color: #444; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.7pt solid #444; padding: 2mm 1.5mm; text-align: left; }
        th { background: #e9ecef; font-size: 7.5pt; }
        td { font-size: 7.5pt; }
    </style>
</head>
<body>
    @if (!empty($letterhead ?? null))
        <div style="text-align:center;margin-bottom:6px;">
            <img src="{{ $letterhead }}" alt="Membrete" style="max-width:100%;height:auto;" />
        </div>
    @endif
    @if (empty($ocultar_marca ?? false) && !empty($logo_fondo ?? null))
        <div style="position:fixed;top:25%;left:15%;width:70%;z-index:-1000;">
            <img src="data:image/png;base64,{{ $logo_fondo }}" style="width:100%;height:auto;opacity:0.15;" alt="" />
        </div>
    @endif
    <h1>Reporte de Actividad Vehicular</h1>
    <div class="meta">
        Rango: {{ $filtros['fecha_desde'] ?? '-' }} al {{ $filtros['fecha_hasta'] ?? '-' }} |
        Generado: {{ now()->format('d/m/Y H:i') }} |
        Registros: {{ $filas->count() }}
    </div>
    <table>
        <thead>
            <tr>
                <th>Fecha</th><th>Vehículo</th><th>Proyecto</th><th>Tipo</th><th>Conductor</th><th>Estado/Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $f)
                <tr>
                    <td>{{ \Illuminate\Support\Str::of((string) $f->event_date)->before(' ') }}</td>
                    <td><strong>{{ $f->vehicle_license_plate ?? '-' }}</strong></td>
                    <td>{{ $f->project_name ?? 'Sin proyecto' }}</td>
                    <td>{{ $f->event_type }}</td>
                    <td>{{ $f->driver_name ?? '-' }}</td>
                    <td>{{ $f->status_value ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Sin registros para los filtros indicados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
