@php
    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
    $mostrarLogoCabecera = empty($letterhead ?? null) && empty($ocultar_marca ?? false) && !empty($logo_data_url ?? null);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Vehículos</title>
    @include('pdf.standard.styles', ['orientation' => 'portrait'])
    <style>
        @if ($esHojaMembretadaCompleta)
        @page {
            size: letter portrait;
            margin: 34mm 10mm 32mm 10mm;
        }

        body {
            background-color: transparent;
        }
        @endif

        #letterhead-page-bg {
            position: fixed;
            top: -34mm;
            left: -10mm;
            width: 215.9mm;
            height: 279.4mm;
            z-index: -1000;
        }

        #letterhead-page-bg img {
            width: 100%;
            height: 100%;
            display: block;
        }

        .report-header {
            border: 1.2px solid #1f2937;
            margin-bottom: 6px;
        }
        .report-header td {
            border: 1px solid #1f2937;
            padding: 4px 6px;
        }
        .report-title-cell {
            background-color: var(--pdf-banda);
            color: var(--pdf-banda-texto);
            font-size: 10pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.25px;
        }
        .report-meta-cell {
            background-color: var(--pdf-tenue);
            font-size: 7pt;
            color: #111827;
        }
        .report-table th,
        .report-table td {
            border: 0.7pt solid #9ca3af;
            padding: 3.5px 4px;
            text-align: left;
            font-size: 7pt;
        }
        .report-table thead th {
            background-color: var(--pdf-encabezado);
            color: var(--pdf-banda-texto);
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1.1pt solid #1f2937;
        }
        .report-table tbody tr:nth-child(even) td {
            background-color: var(--pdf-tenue);
        }
        .vencido {
            color: #991b1b;
            font-weight: 700;
        }
        #letterhead-banner {
            text-align: center;
            margin-bottom: 5px;
        }
        #letterhead-banner img {
            max-width: 100%;
            max-height: 2.2cm;
        }
    </style>
</head>
<body>
    @if (!empty($letterhead ?? null) && $esHojaMembretadaCompleta)
        <div id="letterhead-page-bg">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @elseif (!empty($letterhead ?? null))
        <div id="letterhead-banner">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @endif
    @if (empty($ocultar_marca ?? false) && empty($letterhead ?? null) && !empty($logo_fondo ?? null))
        <div id="watermark" style="position:fixed;top:25%;left:15%;width:70%;z-index:-1000;">
            <img src="data:image/png;base64,{{ $logo_fondo }}" style="width:100%;height:auto;opacity:0.15;" alt="" />
        </div>
    @endif
    <table class="report-header" cellpadding="0" cellspacing="0">
        <tr>
            @if ($mostrarLogoCabecera)
                <td style="width: 15%; text-align: center; background-color: #ffffff;">
                    <img src="{{ $logo_data_url }}" alt="Logo" style="max-height: 36px; max-width: 90px; width: auto; height: auto;" />
                </td>
            @endif
            <td class="report-title-cell" style="width: {{ $mostrarLogoCabecera ? '43%' : '55%' }};">Reporte de Vehículos</td>
            <td class="report-meta-cell" style="width: {{ $mostrarLogoCabecera ? '42%' : '45%' }};">
                <strong>Filtro:</strong> {{ $filtroDescripcion ?? ($filtros['filter_type'] ?? '-') }} ·
                <strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }} ·
                <strong>Registros:</strong> {{ $filas->count() }}
            </td>
        </tr>
    </table>
    <table class="report-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>Placa</th><th>Modelo</th><th>Clase</th><th>Carrocería</th><th>Modalidad</th><th>Propiedad</th><th>Sede</th>
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
                    <td>{{ $f['ownership_label'] ?? '-' }}</td>
                    <td>{{ $f['branch_name'] ?? 'Sin sede' }}</td>
                    <td>{{ $f['soat_expiry'] ?? '-' }}</td>
                    <td>{{ ($f['es_particular'] ?? false) ? 'No aplica' : ($f['rcc_expiry'] ?? '-') }}</td>
                    <td>{{ ($f['es_particular'] ?? false) ? 'No aplica' : ($f['rce_expiry'] ?? '-') }}</td>
                    <td>{{ $f['rtm_expiry'] ?? '-' }}</td>
                    <td>{{ ($f['es_particular'] ?? false) ? 'No aplica' : ($f['operation_card_expiry'] ?? '-') }}</td>
                    <td>{{ $f['agreement_name'] ?? 'Sin convenio' }}</td>
                </tr>
            @empty
                <tr><td colspan="13">Sin registros para los filtros indicados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
