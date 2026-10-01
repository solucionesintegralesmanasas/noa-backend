<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Vehículos</title>
    <style>
        @page { size: letter landscape; margin: 8mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; color: #000; background: #fff; }
        h1 { font-size: 13pt; margin-bottom: 2mm; }
        .meta { font-size: 8pt; color: #444; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.7pt solid #444; padding: 2mm 1.5mm; text-align: left; }
        th { background: #e9ecef; font-size: 7.5pt; }
        td { font-size: 7.5pt; }
        .vencido { color: #b02a37; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Reporte de Vehículos</h1>
    <div class="meta">
        Filtro: {{ $filtroDescripcion ?? ($filtros['filter_type'] ?? '-') }} |
        Generado: {{ now()->format('d/m/Y H:i') }} |
        Registros: {{ $filas->count() }}
    </div>
    <table>
        <thead>
            <tr>
                <th>Placa</th><th>Modelo</th><th>Clase</th><th>Carrocería</th><th>Modalidad</th>
                <th>SOAT</th><th>RCC</th><th>RCE</th><th>RTM</th>
                <th>Tarj. Op.</th><th>Convenio</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $f)
                <tr>
                    <td><strong>{{ $f['vehicle_license_plate'] }}</strong></td>
                    <td>{{ $f['model'] ?? '-' }}</td>
                    <td>{{ $f['vehicle_class'] ?? '-' }}</td>
                    <td>{{ $f['body_type'] ?? '-' }}</td>
                    <td>{{ $f['modality_label'] ?? '-' }}</td>
                    <td>{{ $f['soat_expiry'] ?? '-' }}</td>
                    <td>{{ ($f['es_particular'] ?? false) ? 'No aplica' : ($f['rcc_expiry'] ?? '-') }}</td>
                    <td>{{ ($f['es_particular'] ?? false) ? 'No aplica' : ($f['rce_expiry'] ?? '-') }}</td>
                    <td>{{ $f['rtm_expiry'] ?? '-' }}</td>
                    <td>{{ ($f['es_particular'] ?? false) ? 'No aplica' : ($f['operation_card_expiry'] ?? '-') }}</td>
                    <td>{{ $f['agreement_name'] ?? 'Sin convenio' }}</td>
                </tr>
            @empty
                <tr><td colspan="11">Sin registros para los filtros indicados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
