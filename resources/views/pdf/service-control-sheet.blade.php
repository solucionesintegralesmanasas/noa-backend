<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>OPE-F-006 Planilla de Control de Prestación de Servicios</title>
    <style>
        /* ============================================================
           DOMPDF COMPATIBLE - Transporte Sin Barreras S.A.S.
           OPE-F-006 Planilla de Control de Prestación de Servicios
           Versión: 002 | Fecha: 07/08/2025
           ============================================================ */

        @page {
            size: letter landscape;
            margin: 8mm 8mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #000000;
            background: #ffffff;
        }

        /* Marca de agua con el logo registrado de la empresa */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 520px;
            height: 520px;
            margin-left: -260px;
            margin-top: -260px;
            opacity: 0.07;
            z-index: -1000;
            text-align: center;
        }

        .watermark img {
            width: 100%;
            max-height: 100%;
        }

        .page {
            width: 100%;
            padding: 10mm 8mm;
        }

        /* ── ENCABEZADO ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5pt solid #000000;
            margin-bottom: 0;
        }

        .header-table td {
            padding: 3px 5px;
            vertical-align: middle;
        }

        .header-logo {
            width: 80px;
            text-align: center;
            border-right: 1pt solid #000000;
            vertical-align: middle;
        }

        .header-logo img {
            max-width: 70px;
            max-height: 50px;
        }

        .header-logo-placeholder {
            width: 70px;
            height: 50px;
            border: 1pt dashed #999;
            display: inline-block;
            font-size: 6pt;
            color: #999;
            text-align: center;
            line-height: 50px;
        }

        .header-title {
            text-align: center;
            font-size: 9.5pt;
            font-weight: bold;
            vertical-align: middle;
            border-right: 1pt solid #000000;
            padding: 8px 10px;
        }

        .header-meta {
            width: 130px;
            vertical-align: top;
            font-size: 7.5pt;
        }

        .header-meta table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-meta td {
            padding: 2px 4px;
            border-bottom: 0.5pt solid #cccccc;
            white-space: nowrap;
        }

        /* ── SEPARADOR ── */
        .spacer {
            height: 4px;
        }

        /* ── INFO VEHÍCULO ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border: 1pt solid #000000;
            margin-top: 4px;
        }

        .info-table td {
            padding: 3px 6px;
            border: 0.5pt solid #000000;
            font-size: 8pt;
            vertical-align: middle;
        }

        .info-label {
            font-weight: bold;
            white-space: nowrap;
            width: 120px;
        }

        .info-value {
            border-bottom: 0.5pt solid #666;
            min-width: 100px;
        }

        /* ── TABLA PRINCIPAL ── */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            border: 1pt solid #000000;
            margin-top: 4px;
            table-layout: fixed;
            word-wrap: break-word;
        }

        .main-table thead {
            display: table-header-group;
        }

        .main-table tr {
            page-break-inside: avoid;
        }

        .main-table th,
        .main-table td {
            border: 0.75pt solid #000000;
            text-align: center;
            vertical-align: middle;
            padding: 2px 2px;
            font-size: 7pt;
            overflow: hidden;
        }

        /* Encabezado rojo */
        .main-table th {
            background-color: #FF0000;
            color: #ffffff;
            font-weight: bold;
            font-size: 7pt;
        }

        .main-table th.sub-header {
            background-color: #FF0000;
            color: #ffffff;
            font-size: 6.5pt;
        }

        /* Filas de datos */
        .main-table tbody tr td {
            height: 16px;
            font-size: 7.5pt;
        }

        .main-table tbody tr:nth-child(even) td {
            background-color: #FFF5F5;
        }

        .col-fecha {
            width: 8%;
        }

        .col-ruta {
            width: 19%;
            text-align: left;
        }

        .col-hora {
            width: 5.5%;
        }

        .col-descanso {
            width: 5.5%;
        }

        .col-total {
            width: 5%;
        }

        .col-km {
            width: 5%;
        }

        .col-firma {
            width: 13%;
        }

        .firma-cell {
            height: 30px;
            padding: 2px;
            vertical-align: middle;
            text-align: center;
        }

        .firma-cell img {
            max-height: 26px;
            max-width: 85px;
            display: block;
            margin: 0 auto;
        }

        .col-conductor {
            width: 14%;
        }

        .ruta-detalle {
            display: block;
            font-size: 6pt;
            color: #555555;
            word-wrap: break-word;
        }

        .fecha-numero {
            display: block;
            font-weight: bold;
            font-size: 9pt;
        }

        .fecha-completa {
            display: block;
            font-size: 6.5pt;
            color: #000000;
            white-space: nowrap;
        }

        .estado-finalizada {
            display: block;
            font-size: 6pt;
            font-weight: bold;
            color: #0a7a2e;
        }

        .estado-abierta {
            display: block;
            font-size: 6pt;
            font-weight: bold;
            color: #b77900;
        }

        /* ── PIE DE PÁGINA ── */
        .footer-section {
            margin-top: 4px;
            border: 1pt solid #000000;
            page-break-inside: avoid;
        }

        .footer-obs {
            padding: 4px 6px;
            font-size: 7.5pt;
            border-bottom: 0.5pt solid #000;
            min-height: 24px;
        }

        .footer-obs span {
            font-weight: bold;
        }

        .footer-firmas {
            display: table;
            width: 100%;
        }

        .footer-firma-left,
        .footer-firma-right {
            display: table-cell;
            width: 50%;
            padding: 6px 10px 4px 10px;
            font-size: 7.5pt;
            vertical-align: bottom;
        }

        .footer-firma-left {
            border-right: 0.5pt solid #000;
        }

        .footer-firma-line {
            border-bottom: 0.75pt solid #000;
            margin-bottom: 3px;
            height: 20px;
        }

        .footer-nota {
            padding: 4px 6px;
            font-size: 6.5pt;
            font-style: italic;
            color: #333;
            border-top: 0.5pt solid #000;
            line-height: 1.4;
        }

        /* ── CONTROL DE CAMBIOS (segunda página) ── */
        .page-break {
            page-break-before: always;
        }

        .cambios-table {
            width: 100%;
            border-collapse: collapse;
            border: 1pt solid #000;
            margin-top: 6px;
        }

        .cambios-table th {
            background-color: #CC0000;
            color: #fff;
            font-weight: bold;
            font-size: 8pt;
            padding: 4px 6px;
            border: 0.75pt solid #000;
            text-align: center;
        }

        .cambios-table td {
            padding: 4px 6px;
            border: 0.75pt solid #000;
            font-size: 8pt;
        }

        .cambios-footer {
            width: 100%;
            border-collapse: collapse;
            border: 1pt solid #000;
            border-top: 0;
            margin-top: 0;
        }

        .cambios-footer td {
            padding: 6px;
            border: 0.75pt solid #000;
            font-size: 7.5pt;
            text-align: center;
            width: 33.33%;
            vertical-align: top;
        }

        .cambios-footer .firma-espacio {
            height: 35px;
            border-bottom: 0.5pt solid #000;
            margin-bottom: 3px;
        }

        /* ── ANEXO MAPA RECORRIDO GPS (siempre en segunda página) ── */
        .mapa-section {
            margin-top: 4px;
            border: 1pt solid #000000;
            page-break-before: always;
            page-break-inside: avoid;
        }

        .mapa-titulo {
            background-color: #1e3a5f;
            color: #ffffff;
            font-weight: bold;
            font-size: 8pt;
            text-align: center;
            padding: 4px 6px;
        }

        .mapa-subtitulo {
            font-size: 7pt;
            text-align: center;
            padding: 3px 6px;
            border-bottom: 0.5pt solid #000;
            color: #333;
        }

        .mapa-imagen {
            text-align: center;
            padding: 4px;
            background-color: #ffffff;
        }

        .mapa-imagen img {
            width: 100%;
            display: block;
        }

        .mapa-stats {
            width: 100%;
            border-collapse: collapse;
            border-top: 0.5pt solid #000;
        }

        .mapa-stats td {
            border: 0.5pt solid #000;
            font-size: 7.5pt;
            text-align: center;
            padding: 3px 4px;
            width: 25%;
        }

        .mapa-stats .stat-label {
            font-weight: bold;
            background-color: #eef2f7;
        }

        .mapa-leyenda {
            font-size: 7pt;
            text-align: center;
            padding: 3px 6px;
            color: #333;
        }

        .mapa-vacio {
            font-size: 8pt;
            text-align: center;
            padding: 14px 6px;
            color: #666;
        }
    </style>
</head>

<body>

    @if (!empty($logo))
        <div class="watermark">
            <img src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo }}" alt="">
        </div>
    @endif

    <!-- ================================================================
     PÁGINA 1 — PLANILLA DE CONTROL
     ================================================================ -->
    <!-- ENCABEZADO -->
    <table class="header-table">
        <tr>
            <td class="header-logo" rowspan="1">
                @if (!empty($logo))
                    <img src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo }}" alt="Logo empresa">
                @else
                    <div class="header-logo-placeholder">SIN LOGO<br>Suba el logo en Empresas</div>
                @endif
            </td>
            <td class="header-title">
                PLANILLA DE CONTROL DE PRESTACION DE SERVICIOS<br>
                DE {{ $empresa_transportadora ?? 'TRANSPORTES SIN BARRERAS S.A.S.' }}
            </td>
            {{-- <td class="header-meta">
                <table>
                    <tr>
                        <td><strong>Código:</strong> OPE - F - 006</td>
                    </tr>
                    <tr>
                        <td><strong>Versión:</strong> 002</td>
                    </tr>
                    <tr>
                        <td><strong>Fecha:</strong> 07/08/2025</td>
                    </tr>
                    <tr>
                        <td><strong>Periodo:</strong> {{ $periodo ?? 'N/A' }}</td>
                    </tr>
                    @if (!empty($es_historial))
                        <tr>
                            <td
                                style="color: #FF0000; font-weight: bold; font-size: 7pt; text-align: center; border-bottom: none;">
                                [HISTORIAL]</td>
                        </tr>
                    @endif
                </table>
            </td> --}}
        </tr>
    </table>

    <!-- INFORMACIÓN DEL VEHÍCULO -->
    <table class="info-table">
        <tr>
            <td class="info-label">PLACA DE VEHICULO:</td>
            <td class="info-value">{{ $placa ?? 'N/A' }}</td>
            <td class="info-label">AREA O DEPENDENCIA:</td>
            <td class="info-value">{{ $area ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">CLASE DE VEHICULO:</td>
            <td class="info-value">{{ $clase ?? 'N/A' }}</td>
            <td class="info-label">NIT CLIENTE:</td>
            <td class="info-value">{{ $nit ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">EMPRESA / CLIENTE:</td>
            <td class="info-value" colspan="3">{{ $empresa ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">PROYECTO:</td>
            <td class="info-value" colspan="3">{{ $proyecto ?? 'N/A' }}@if(!empty($proyecto_vigencia ?? null)) (Vigencia: {{ $proyecto_vigencia }})@endif</td>
        </tr>
    </table>

    <!-- TABLA PRINCIPAL: UNA FILA POR CADA RUTA (RECORRIDO) -->
    <table class="main-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-fecha">FECHA</th>
                <th rowspan="2" class="col-ruta">RUTA DIARIA</th>
                <th rowspan="2" class="col-hora">HORA<br>INICIO</th>
                <th colspan="2" class="col-descanso">HORAS DE DESCANSO</th>
                <th rowspan="2" class="col-hora">HORA<br>FINAL</th>
                <th rowspan="2" class="col-total">TOTAL<br>HORAS</th>
                <th rowspan="2" class="col-km">KM<br>INICIAL</th>
                <th rowspan="2" class="col-km">KM<br>FINAL</th>
                <th rowspan="2" class="col-km">KM<br>TOTAL</th>
                <th rowspan="2" class="col-firma">FIRMA DEL<br>FUNCIONARIO</th>
                <th rowspan="2" class="col-conductor">NOMBRE DEL<br>CONDUCTOR</th>
            </tr>
            <tr>
                <th class="sub-header">HORA I</th>
                <th class="sub-header">HORA F</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dias as $dia)
                <tr>
                    <td><span class="fecha-numero">{{ $dia['numero'] }}</span><span class="fecha-completa">{{ $dia['fecha_completa'] ?? '' }}</span>
                        @if (array_key_exists('is_closed', $dia))
                            @if (!empty($dia['is_closed']))
                                <span class="estado-finalizada">FINALIZADA</span>
                            @else
                                <span class="estado-abierta">ABIERTA</span>
                            @endif
                        @endif
                    </td>
                    <td style="text-align: left; padding-left: 4px;">
                        {{ $dia['ruta_unica'] ?? ($dia['ruta'] ?? '') }}
                        @if (!empty($dia['detalle_cierre'] ?? null))
                            <span class="ruta-detalle">{{ $dia['detalle_cierre'] }}</span>
                        @endif
                    </td>
                    <td>{{ $dia['hora_inicio'] ?? '' }}</td>
                    <td>{{ $dia['descanso_inicio'] ?? '' }}</td>
                    <td>{{ $dia['descanso_fin'] ?? '' }}</td>
                    <td>{{ $dia['hora_fin'] ?? '' }}</td>
                    <td>{{ $dia['total_hours'] ?? '' }}</td>
                    <td>{{ isset($dia['km_inicial']) && is_numeric($dia['km_inicial']) ? rtrim(rtrim(number_format((float) $dia['km_inicial'], 2, '.', ''), '0'), '.') : ($dia['km_inicial'] ?? '') }}</td>
                    <td>{{ isset($dia['km_final']) && is_numeric($dia['km_final']) ? rtrim(rtrim(number_format((float) $dia['km_final'], 2, '.', ''), '0'), '.') : ($dia['km_final'] ?? '') }}</td>
                    <td>{{ isset($dia['km_total']) && is_numeric($dia['km_total']) ? rtrim(rtrim(number_format((float) $dia['km_total'], 2, '.', ''), '0'), '.') : ($dia['km_total'] ?? '') }}</td>
                    <td class="firma-cell">
                        @if (!empty($dia['firma_funcionario']))
                            <img src="data:image/png;base64,{{ $dia['firma_funcionario'] }}" alt="Firma funcionario">
                        @endif
                    </td>
                    <td style="text-align: left; padding-left: 4px;">{{ $dia['conductor'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- PIE / FIRMAS -->
    <div class="footer-section">
        <div class="footer-obs">
            <span>OBSERVACIONES:</span> &nbsp; {{ $observaciones ?? '' }}
        </div>

        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td
                    style="width:50%; padding:6px 10px; border-right:0.5pt solid #000; vertical-align:bottom; font-size:7.5pt; text-align: center;">
                    <div style="height: 45px; vertical-align: bottom; margin-bottom: 2px; padding: 3px;">
                        @if (!empty($firma_conductor))
                            <img src="data:image/png;base64,{{ $firma_conductor }}"
                                style="max-height: 38px; max-width: 180px;">
                        @endif
                    </div>
                    <div class="footer-firma-line"></div>
                    FIRMA DEL CONDUCTOR QUE ENTREGA
                </td>
                <td style="width:50%; padding:6px 10px; vertical-align:bottom; font-size:7.5pt; text-align: center;">
                    <div style="height: 45px; vertical-align: bottom; margin-bottom: 2px; padding: 3px;">
                        @if (!empty($firma_recibido))
                            <img src="data:image/png;base64,{{ $firma_recibido }}"
                                style="max-height: 38px; max-width: 180px;">
                        @endif
                    </div>
                    <div class="footer-firma-line"></div>
                    RECIBO Y FIRMA (COORDINADOR DE SERVICIOS)
                </td>
            </tr>
        </table>

        <div class="footer-nota">
            Señor conductor, por favor diligencie este formato de manera completa y correcta.
            Los criterios de calificación son rigurosos; asegúrese de registrar la información
            con letra legible y datos exactos.
        </div>
    </div>

    <!-- ANEXO: RECORRIDO GPS DEL VEHÍCULO EN PROYECTO -->
    @if (!empty($mapa_recorrido) || !empty($mapa_sin_datos))
        <div class="mapa-section">
            <div class="mapa-titulo">ANEXO: RECORRIDO GPS DEL VEHÍCULO EN PROYECTO</div>
            <div class="mapa-subtitulo">
                Proyecto: {{ $proyecto ?? 'N/A' }} &nbsp;|&nbsp; Placa: {{ $placa ?? 'N/A' }} &nbsp;|&nbsp; Periodo: {{ $periodo ?? 'N/A' }}@if(!empty($mapa_fecha ?? null)) &nbsp;|&nbsp; Recorrido GPS del {{ $mapa_fecha }}@endif
            </div>
            @if (!empty($mapa_recorrido))
                <div class="mapa-imagen">
                    <img src="data:image/png;base64,{!! $mapa_recorrido !!}" alt="Mapa del recorrido GPS" width="800">
                </div>
                @if (!empty($mapa_stats))
                    <table class="mapa-stats">
                        <tr>
                            <td class="stat-label">Puntos GPS</td>
                            <td class="stat-label">Distancia GPS</td>
                            <td class="stat-label">Hora inicio GPS</td>
                            <td class="stat-label">Hora fin GPS</td>
                        </tr>
                        <tr>
                            <td>{{ $mapa_stats['total_puntos'] ?? 0 }}</td>
                            <td>{{ $mapa_stats['distancia_km'] ?? 0 }} km</td>
                            <td>{{ $mapa_stats['hora_inicio'] ?? '—' }}</td>
                            <td>{{ $mapa_stats['hora_fin'] ?? '—' }}</td>
                        </tr>
                    </table>
                @endif
                <div class="mapa-leyenda">
                    @if (!empty($mapa_es_captura ?? null))
                        Captura del mapa en vivo pegada por el usuario
                    @else
                        Marcador verde = inicio del recorrido &nbsp;·&nbsp; Marcador rojo = fin del recorrido &nbsp;·&nbsp; Línea azul = trazado GPS
                    @endif
                </div>
            @else
                <div class="mapa-vacio">Sin puntos GPS registrados para este vehículo, proyecto y fecha. El vehículo pudo estar en disponibilidad o sin transmisión GPS ese día.</div>
            @endif
        </div>
    @endif
</body>

</html>
