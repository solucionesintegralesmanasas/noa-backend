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
        }

        .main-table th,
        .main-table td {
            border: 0.75pt solid #000000;
            text-align: center;
            vertical-align: middle;
            padding: 2px 2px;
            font-size: 7pt;
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
            width: 7%;
        }

        .col-ruta {
            width: 18%;
            text-align: left;
        }

        .col-hora {
            width: 7%;
        }

        .col-descanso {
            width: 7%;
        }

        .col-total {
            width: 6%;
        }

        .col-km {
            width: 6%;
        }

        .col-firma {
            width: 14%;
        }

        .firma-cell {
            height: 30px;
            padding: 2px;
            vertical-align: middle;
            text-align: center;
            border: 0.5pt solid #000;
            background-color: #ffffff;
        }

        .firma-cell img {
            max-height: 26px;
            max-width: 85px;
            display: block;
            margin: 0 auto;
        }

        .firma-cell-empty {
            height: 26px;
            border: 0.5pt dashed #cccccc;
            display: inline-block;
            width: 85px;
        }

        .col-conductor {
            width: 16%;
        }

        /* ── PIE DE PÁGINA ── */
        .footer-section {
            margin-top: 4px;
            border: 1pt solid #000000;
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
    </style>
</head>

<body>

    <!-- ================================================================
     PÁGINA 1 — PLANILLA DE CONTROL
     ================================================================ -->
    <!-- ENCABEZADO -->
    <table class="header-table">
        <tr>
            <td class="header-logo" rowspan="1">
                @if (!empty($logo))
                    <img src="data:image/png;base64,{{ $logo }}" alt="Logo">
                @else
                    <div class="header-logo-placeholder">LOGO</div>
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

    <!-- TABLA PRINCIPAL -->
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
                    <td>{{ $dia['numero'] }}</td>
                    <td style="text-align: left; padding-left: 4px;">
                        @if (!empty($dia['rutas_detalle']) && is_array($dia['rutas_detalle']))
                            @foreach ($dia['rutas_detalle'] as $i => $rd)
                                <div>{{ $i + 1 }}. {{ ($rd['origin'] ?? '') . (($rd['origin'] ?? '') !== '' ? ' - ' : '') . ($rd['destination'] ?? '') }}
                                    @if (!empty($rd['end_time']))
                                        <span style="color:#777;">· Fin {{ \Carbon\Carbon::parse($rd['end_time'])->format('H:i') }}</span>
                                    @endif
                                    @if (isset($rd['ending_kilometer']) && $rd['ending_kilometer'] !== null)
                                        <span style="color:#777;">· km {{ number_format((float)$rd['ending_kilometer'], 0, ',', '.') }}</span>
                                    @endif
                                </div>
                            @endforeach
                        @elseif (!empty($dia['rutas']) && is_array($dia['rutas']))
                            @foreach ($dia['rutas'] as $i => $r)
                                <div>{{ $i + 1 }}. {{ $r }}</div>
                            @endforeach
                        @else
                            {{ $dia['ruta'] ?? '' }}
                        @endif
                    </td>
                    <td>{{ $dia['hora_inicio'] ?? '' }}</td>
                    <td>{{ $dia['descanso_inicio'] ?? '' }}</td>
                    <td>{{ $dia['descanso_fin'] ?? '' }}</td>
                    <td>{{ $dia['hora_fin'] ?? '' }}</td>
                    <td>{{ $dia['total_hours'] ?? '' }}</td>
                    <td>{{ $dia['km_inicial'] ?? '' }}</td>
                    <td>{{ $dia['km_final'] ?? '' }}</td>
                    <td>{{ $dia['km_total'] ?? '' }}</td>
                    <td class="firma-cell">
                        @if (!empty($dia['firma_funcionario']))
                            <img src="data:image/png;base64,{{ $dia['firma_funcionario'] }}">
                        @else
                            <div class="firma-cell-empty"></div>
                        @endif
                    </td>
                    <td style="text-align: left; padding-left: 4px;">{{ $dia['conductor'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- CIERRE Y FIRMAS POR RECORRIDO (solo cuando hay más de un recorrido con cierre) -->
    @php
        $rutasConCierre = collect($rutas_detalle ?? [])->filter(function ($rd) {
            return !empty($rd['end_time']) || (isset($rd['ending_kilometer']) && $rd['ending_kilometer'] !== null) || !empty($rd['end_novelty']);
        });
    @endphp
    @if ($rutasConCierre->count() > 0)
        <div style="margin-top: 6px;">
            <div style="background:#FF0000; color:#ffffff; font-weight:bold; font-size:8pt; padding:3px 6px; border:1pt solid #000; border-bottom:0;">
                CIERRE Y FIRMAS POR RECORRIDO ({{ $rutasConCierre->count() }})
            </div>
            @foreach ($rutasConCierre as $i => $rd)
                <table style="width:100%; border-collapse:collapse; border:1pt solid #000;">
                    <tr>
                        <td style="font-weight:bold; width:10%; border:0.5pt solid #000; padding:2px 4px;">RECORRIDO {{ $i + 1 }}</td>
                        <td style="width:40%; border:0.5pt solid #000; padding:2px 4px;">{{ ($rd['origin'] ?? '') . (($rd['origin'] ?? '') !== '' ? ' → ' : '') . ($rd['destination'] ?? '') }}</td>
                        <td style="font-weight:bold; width:12%; border:0.5pt solid #000; padding:2px 4px;">HORA FIN</td>
                        <td style="width:13%; border:0.5pt solid #000; padding:2px 4px;">{{ !empty($rd['end_time']) ? \Carbon\Carbon::parse($rd['end_time'])->format('H:i') : '' }}</td>
                        <td style="font-weight:bold; width:12%; border:0.5pt solid #000; padding:2px 4px;">KM FINAL</td>
                        <td style="width:13%; border:0.5pt solid #000; padding:2px 4px;">{{ isset($rd['ending_kilometer']) && $rd['ending_kilometer'] !== null ? number_format((float)$rd['ending_kilometer'], 0, ',', '.') : '' }}</td>
                    </tr>
                    @if (!empty($rd['number_of_tolls']) || !empty($rd['total_toll_value']) || !empty($rd['end_novelty']))
                    <tr>
                        <td style="font-weight:bold; border:0.5pt solid #000; padding:2px 4px;">PEAJES</td>
                        <td style="border:0.5pt solid #000; padding:2px 4px;">{{ ($rd['number_of_tolls'] ?? 0) }} · Valor ${{ isset($rd['total_toll_value']) ? number_format((float)$rd['total_toll_value'], 0, ',', '.') : 0 }}</td>
                        <td style="font-weight:bold; border:0.5pt solid #000; padding:2px 4px;">NOVEDAD</td>
                        <td colspan="3" style="border:0.5pt solid #000; padding:2px 4px;">{{ $rd['end_novelty'] ?? '' }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="font-weight:bold; border:0.5pt solid #000; padding:2px 4px;">FIRMA FUNCIONARIO</td>
                        <td style="border:0.5pt solid #000; padding:2px 4px; text-align:center;">
                            @if (!empty($rd['firma_funcionario']))
                                <img src="data:image/png;base64,{{ $rd['firma_funcionario'] }}" style="max-height:30px; max-width:120px;">
                            @endif
                        </td>
                        <td style="font-weight:bold; border:0.5pt solid #000; padding:2px 4px;">FIRMA CONDUCTOR</td>
                        <td colspan="3" style="border:0.5pt solid #000; padding:2px 4px; text-align:center;">
                            @if (!empty($rd['firma_conductor']))
                                <img src="data:image/png;base64,{{ $rd['firma_conductor'] }}" style="max-height:30px; max-width:120px;">
                            @endif
                        </td>
                    </tr>
                </table>
            @endforeach
        </div>
    @endif

    <!-- PIE / FIRMAS -->
    <div class="footer-section">
        <div class="footer-obs">
            <span>OBSERVACIONES:</span> &nbsp; {{ $observaciones ?? '' }}
        </div>

        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td
                    style="width:50%; padding:6px 10px; border-right:0.5pt solid #000; vertical-align:bottom; font-size:7.5pt; text-align: center;">
                    <div style="height: 45px; vertical-align: bottom; margin-bottom: 2px; border: 0.5pt solid #000; padding: 3px; background: #ffffff;">
                        @if (!empty($firma_conductor))
                            <img src="data:image/png;base64,{{ $firma_conductor }}"
                                style="max-height: 38px; max-width: 180px;">
                        @endif
                    </div>
                    <div class="footer-firma-line"></div>
                    FIRMA DEL CONDUCTOR QUE ENTREGA
                </td>
                <td style="width:50%; padding:6px 10px; vertical-align:bottom; font-size:7.5pt; text-align: center;">
                    <div style="height: 45px; vertical-align: bottom; margin-bottom: 2px; border: 0.5pt solid #000; padding: 3px; background: #ffffff;">
                        @if (!empty($firma_recibido))
                            <img src="data:image/png;base64,{{ $firma_recibido }}"
                                style="max-height: 38px; max-width: 180px;">
                        @endif
                    </div>
                    <div class="footer-firma-line"></div>
                    RECIBO Y FIRMA (FUNCIONARIO)
                </td>
            </tr>
        </table>

        <div class="footer-nota">
            Señor conductor, por favor diligencie este formato de manera completa y correcta.
            Los criterios de calificación son rigurosos; asegúrese de registrar la información
            con letra legible y datos exactos.
        </div>
    </div>
    </div>
</body>

</html>
