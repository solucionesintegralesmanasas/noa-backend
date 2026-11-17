@php
    $__fontDir = str_replace('\\', '/', resource_path('fonts/inter'));
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>OPE-F-006 Planilla de Control de Prestación de Servicios</title>
    <style>
        /* ============================================================
       DOMPDF COMPATIBLE - Planilla Oficial NOA Transportes
       Formato Oficial: OPE-F-006 Planilla de Control de Prestación de Servicios
       Normativa: PESV / Decreto 1079 de 2015 / SGC ISO 9001
       Formato: Carta Horizontal (Letter Landscape: 11in x 8.5in)
       CSS 100% Nativo para Laravel DomPDF (Tablas estrictas)
       ============================================================ */

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $__fontDir }}/Inter-Regular.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 500;
            src: url('{{ $__fontDir }}/Inter-Medium.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 600;
            src: url('{{ $__fontDir }}/Inter-SemiBold.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 700;
            src: url('{{ $__fontDir }}/Inter-Bold.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: italic;
            font-weight: 400;
            src: url('{{ $__fontDir }}/Inter-Italic.ttf') format('truetype');
        }

        @page {
            size: letter landscape;
            margin: 6mm 7mm 6mm 7mm;
        }

        /* El selector universal NO debe tocar `margin`: si resetea el margen de
           `html`, DomPDF descarta el margen de @page y la planilla se imprime
           pegada al borde de la hoja (sin área de no impresión). */
        * {
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: 400;
            font-size: 7.4pt;
            line-height: 1.15;
            color: #111827;
            background-color: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td,
        th {
            vertical-align: middle;
            word-wrap: break-word;
        }

        /* ── 0. MEMBRETE ── */
        .letterhead-banner {
            text-align: center;
            margin-bottom: 4px;
        }

        .letterhead-banner img {
            max-width: 100%;
            height: auto;
        }

        /* ── 1. ENCABEZADO INSTITUCIONAL SGC / PESV ── */
        .header-table {
            width: 100%;
            border: 1.4px solid #0f172a;
            border-collapse: collapse;
            margin-bottom: 3.5px;
        }

        .header-table td {
            border: 1px solid #0f172a;
            padding: 3px 6px;
            vertical-align: middle;
        }

        .header-logo-cell {
            width: 16%;
            text-align: center;
            background: #ffffff;
            padding: 2px 4px;
        }

        .header-logo-cell img {
            max-height: 46px;
            max-width: 112px;
            width: auto;
            height: auto;
        }

        .header-title-cell {
            width: 58%;
            text-align: center;
            padding: 3px 8px;
        }

        .header-meta-cell {
            width: 26%;
            padding: 0 !important;
        }

        .company-name {
            font-size: 8.4pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.25px;
            line-height: 1.1;
            white-space: nowrap;
        }

        .doc-main-title {
            font-size: 8.8pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 1px;
            line-height: 1.15;
            white-space: nowrap;
        }

        .doc-sub-title {
            font-size: 6.2pt;
            font-weight: 600;
            color: #262626;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 1px;
            white-space: nowrap;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            border: none !important;
            border-bottom: 1px solid #0f172a !important;
            padding: 1.8px 4px !important;
            font-size: 6.6pt;
        }

        .meta-table tr:last-child td {
            border-bottom: none !important;
        }

        .meta-label {
            font-weight: bold;
            color: #111827;
            width: 48%;
            background-color: #f7f7f7;
            border-right: 1px solid #0f172a !important;
            text-transform: uppercase;
            font-size: 6.3pt;
        }

        .meta-val {
            font-weight: bold;
            color: #000000;
            text-align: center;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-size: 6.8pt;
            text-transform: uppercase;
        }

        /* ── 2. DATOS DEL VEHÍCULO, CLIENTE Y PROYECTO ── */
        .info-table {
            width: 100%;
            border: 1px solid #1f2937;
            border-collapse: collapse;
            margin-bottom: 3.5px;
        }

        .info-table td {
            border: 1px solid #9ca3af;
            padding: 2.4px 6px;
            font-size: 7.2pt;
            line-height: 1.15;
        }

        .info-lbl {
            background-color: #f7f7f7;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            font-size: 6.5pt;
            line-height: 1.05;
            border-right: 1px solid #9ca3af;
        }

        .info-val {
            font-weight: 500;
            color: #000000;
        }

        /* Placa · interno: micro-tabla alineada (base estándar pdf.standard.vehicle-line). */
        .placa-inline-tbl {
            width: auto;
            table-layout: auto;
            border-collapse: collapse;
            margin: 0;
        }

        .placa-inline-tbl td {
            border: none;
            padding: 0;
            vertical-align: middle;
            line-height: 1.1;
        }

        .placa-inline-tbl td.placa-badge {
            background-color: #fef08a;
            color: #854d0e;
            border: 0.6pt solid #ca8a04;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            font-size: 7.3pt;
            padding: 1.2px 5px;
            white-space: nowrap;
        }

        .placa-inline-tbl td.vehiculo-sep {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            font-size: 8pt;
            color: #4b5563;
            padding: 0 4px;
            text-align: center;
            white-space: nowrap;
        }

        .placa-inline-tbl td.vehiculo-interno {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            font-size: 7.2pt;
            color: #111827;
            white-space: nowrap;
        }

        .highlight-client {
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
        }

        /* ── 3. TABLA PRINCIPAL DE RECORRIDOS DIARIOS ── */
        .main-table {
            width: 100%;
            border: 1.2px solid #0f172a;
            border-collapse: collapse;
            margin-top: 2px;
            margin-bottom: 3.5px;
            table-layout: fixed;
            page-break-inside: auto;
        }

        .main-table thead {
            display: table-header-group;
        }

        .main-table tr {
            page-break-inside: avoid;
        }

        .main-table thead th {
            background-color: #f0f0f0;
            color: #000000;
            font-size: 6.7pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 3px 2px;
            border: 1px solid #1f2937;
            text-align: center;
            letter-spacing: 0.15px;
            vertical-align: middle;
        }

        .main-table thead th.sub-th {
            background-color: #e5e5e5;
            color: #000000;
            font-size: 6pt;
            padding: 2px 1px;
        }

        .main-table tbody td {
            border: 1px solid #9ca3af;
            padding: 2.4px 3px;
            font-size: 7pt;
            color: #000000;
            font-weight: 500;
            text-align: center;
            vertical-align: middle;
        }

        .main-table tbody tr:nth-child(even) td {
            background-color: #fafafa;
        }

        .main-table .sin-registros {
            padding: 10px 6px;
            font-size: 7.5pt;
            color: #374151;
            text-align: center;
        }

        /* Anchos de columnas (horizontal: 100% exacto) */
        .col-fecha {
            width: 6%;
        }

        .col-ruta {
            width: 24%;
            text-align: left !important;
            padding-left: 5px !important;
        }

        .col-hora {
            width: 5.5%;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-size: 6.8pt;
        }

        .col-descanso {
            width: 4.5%;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-size: 6.6pt;
        }

        .col-total {
            width: 5%;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
        }

        .col-km {
            width: 5.5%;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-size: 6.8pt;
        }

        .col-km-tot {
            width: 5.5%;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            color: #000000;
        }

        .col-firma {
            width: 13.5%;
        }

        .col-conductor {
            width: 15%;
            text-align: left !important;
            padding-left: 5px !important;
            font-size: 6.9pt;
            text-transform: uppercase;
        }

        .conductor-firma {
            height: 22px;
            text-align: center;
        }

        .conductor-firma img {
            max-height: 21px;
            max-width: 88px;
            display: block;
            margin: 0 auto;
        }

        .conductor-firma .firma-placeholder {
            display: block;
            line-height: 22px;
        }

        .conductor-nombre {
            font-size: 5.7pt;
            line-height: 1.1;
            color: #111827;
        }

        .fecha-num {
            font-weight: bold;
            font-size: 8.2pt;
            color: #000000;
            line-height: 1;
        }

        .fecha-txt {
            font-size: 5.8pt;
            color: #262626;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .tag-finalizada {
            display: inline-block;
            font-size: 5.3pt;
            font-weight: bold;
            background-color: #dcfce7;
            color: #15803d;
            border: 0.5px solid #86efac;
            border-radius: 1.5px;
            padding: 0 2px;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .tag-abierta {
            display: inline-block;
            font-size: 5.3pt;
            font-weight: bold;
            background-color: #fef3c7;
            color: #b45309;
            border: 0.5px solid #fde68a;
            border-radius: 1.5px;
            padding: 0 2px;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .ruta-tit {
            font-weight: bold;
            color: #000000;
            line-height: 1.15;
            font-size: 7.2pt;
        }

        .ruta-sub {
            font-size: 6pt;
            color: #374151;
            margin-top: 1px;
            line-height: 1.2;
        }

        .firma-box-inner {
            height: 28px;
            text-align: center;
            vertical-align: middle;
        }

        .firma-box-inner img {
            max-height: 26px;
            max-width: 95px;
            display: block;
            margin: 0 auto;
        }

        .firma-placeholder {
            font-size: 5.8pt;
            color: #4b5563;
            font-style: italic;
        }

        /* Cierre (totales + observaciones + firmas + nota legal) como una sola
           unidad: DomPDF parte los bloques sueltos y deja la nota legal cortada
           al inicio de una página nueva. */
        .cierre {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .cierre>tbody>tr>td {
            padding: 0;
            border: none;
        }

        /* ── 4. BANDAS DE EVIDENCIA (SPEC-002) ── */
        .banda-evidencia {
            border: 0.75pt solid #b45309;
            background: #fffbeb;
            color: #78350f;
            padding: 3px 7px;
            margin-bottom: 3.5px;
            font-size: 6.5pt;
            line-height: 1.3;
            page-break-inside: avoid;
        }

        .banda-evidencia.banda-excepcion {
            border-color: #991b1b;
            background: #fef2f2;
            color: #7f1d1d;
        }

        .banda-evidencia.banda-certificacion {
            border-color: #0369a1;
            background: #f0f9ff;
            color: #0c4a6e;
        }

        .banda-evidencia-detalle {
            margin-top: 1px;
            padding-left: 6px;
        }

        /* ── 5. RESUMEN DE TOTALES Y AUDITORÍA ── */
        .totals-table {
            width: 100%;
            border: 1px solid #1f2937;
            background-color: #f0f0f0;
            margin-bottom: 3.5px;
            page-break-inside: avoid;
        }

        .totals-table td {
            padding: 3px 7px;
            font-size: 7.1pt;
            color: #000000;
            border: 1px solid #9ca3af;
        }

        /* ── 6. BANDA DE EVIDENCIA OPERATIVA Y OBSERVACIONES ── */
        .obs-section {
            width: 100%;
            border: 1px solid #1f2937;
            border-collapse: collapse;
            margin-bottom: 3.5px;
            page-break-inside: avoid;
        }

        .obs-header {
            background-color: #d8d8d8;
            border-left: 3.5px solid #111827;
            padding: 2.5px 7px;
            font-size: 7pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .obs-body {
            padding: 3.5px 7px;
            font-size: 6.8pt;
            color: #111827;
            min-height: 18px;
            background-color: #ffffff;
            line-height: 1.2;
        }

        /* ── 7. SECCIÓN DE FIRMAS DE RESPONSABILIDAD ── */
        .firmas-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-collapse: collapse;
            margin-bottom: 3.5px;
            page-break-inside: avoid;
        }

        .firmas-table td {
            width: 50%;
            padding: 4px 12px 4px 12px;
            vertical-align: bottom;
            text-align: center;
        }

        .firma-side-left {
            border-right: 1px solid #0f172a;
        }

        .sig-img-container {
            height: 40px;
            margin-bottom: 2px;
            text-align: center;
            vertical-align: bottom;
        }

        .sig-img-container img {
            max-height: 36px;
            max-width: 200px;
        }

        .sig-placeholder {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-size: 6.5pt;
            font-weight: bold;
            color: #4b5563;
            padding-top: 12px;
        }

        .sig-line-bar {
            border-top: 1.2px solid #0f172a;
            padding-top: 2px;
        }

        .sig-title-txt {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
        }

        .sig-sub-txt {
            font-size: 6.2pt;
            color: #262626;
        }

        /* ── 8. PIE LEGAL Y CONTROL SGC ── */
        .legal-notice {
            border: 1px solid #1f2937;
            background-color: #f7f7f7;
            padding: 3.5px 7px;
            font-size: 6pt;
            color: #1f2937;
            text-align: justify;
            line-height: 1.25;
            page-break-inside: avoid;
        }

        .footer-bar-sub {
            margin-top: 3px;
            font-size: 6.2pt;
            color: #262626;
        }
    </style>
</head>

<body>

    @php
        $dias = is_array($dias ?? null) ? $dias : [];
        $totales = $totales ?? [];
        $ultimaFila = $dias !== [] ? end($dias) : [];
        $conductorNombre = $ultimaFila['conductor'] ?? null;
        $totalDias = $totales['dias'] ?? count($dias);
        $diasCerrados = $totales['dias_cerrados'] ?? 0;
        $totalHoras = $totales['horas'] ?? '0:00';
        $totalKm = $totales['km'] ?? 0;
        $logoMime = $logo_mime ?? 'image/png';
    @endphp

    @if (!empty($letterhead ?? null))
        <div class="letterhead-banner">
            <img src="{{ $letterhead }}" alt="Membrete">
        </div>
    @endif

    <!-- 1. ENCABEZADO INSTITUCIONAL SGC / PESV -->
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <!-- Logotipo -->
            <td class="header-logo-cell">
                @if (!empty($logo))
                    <img src="data:{{ $logoMime }};base64,{{ $logo }}" alt="Logo empresa">
                @else
                    <div class="logo-monogram" style="font-weight: bold; font-size: 13pt; color: #111827; line-height: 1; letter-spacing: 0.5px;">
                        {{ $empresa_siglas ?? \App\Services\Pdf\PdfService::siglasEmpresa((string) ($empresa_transportadora ?? '')) }}
                    </div>
                @endif
            </td>

            <!-- Título Central -->
            <td class="header-title-cell">
                <div class="company-name">{{ $empresa_transportadora ?? 'EMPRESA DE TRANSPORTE ESPECIAL' }}
                </div>
                <div class="doc-main-title">PLANILLA DE CONTROL DE PRESTACIÓN DE SERVICIOS</div>
                <div class="doc-sub-title">PLAN ESTRATÉGICO DE SEGURIDAD VIAL (PESV) · CONTROL OPERACIONAL DIARIO
                </div>
            </td>

            <!-- Metadatos de Calidad -->
            <td class="header-meta-cell">
                <table class="meta-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="meta-label">CÓDIGO</td>
                        <td class="meta-val">OPE-F-006</td>
                    </tr>
                    <tr>
                        <td class="meta-label">VERSIÓN</td>
                        <td class="meta-val">002</td>
                    </tr>
                    <tr>
                        <td class="meta-label">FECHA</td>
                        <td class="meta-val">07/08/2025</td>
                    </tr>
                    <tr>
                        <td class="meta-label">PERÍODO</td>
                        <td class="meta-val" style="color: #000000;">{{ $periodo ?? 'N/A' }}</td>
                    </tr>
                    @if (!empty($es_historial))
                        <tr>
                            <td class="meta-label">TIPO</td>
                            <td class="meta-val" style="color: #b45309;">HISTORIAL</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- 2. INFORMACIÓN DEL VEHÍCULO Y CONTRATO -->
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 12%;">Placa Vehículo:</td>
            <td class="info-val" style="width: 20%;">
                @include('pdf.standard.vehicle-line', [
                    'placa' => $placa ?? 'N/A',
                    'numero_interno' => $numero_interno ?? null,
                ])
            </td>
            <td class="info-lbl" style="width: 14%;">Clase de Vehículo:</td>
            <td class="info-val uppercase" style="width: 20%;">{{ $clase ?? 'N/A' }}</td>
            <td class="info-lbl" style="width: 15%;">Funcionario Responsable:</td>
            <td class="info-val uppercase" style="width: 19%;">{{ $area ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Empresa / Cliente:</td>
            <td class="info-val highlight-client" colspan="3">
                {{ $empresa ?? 'N/A' }}
            </td>
            <td class="info-lbl">NIT Cliente:</td>
            <td class="info-val" style="font-weight: bold;">
                {{ $nit ?? 'N/A' }}
            </td>
        </tr>
        <tr>
            <td class="info-lbl">Proyecto / Contrato:</td>
            <td class="info-val" colspan="5">
                <strong style="color: #000000;">{{ $proyecto ?? 'N/A' }}</strong>
                @if (!empty($proyecto_vigencia))
                    &nbsp;·&nbsp; Vigencia: {{ $proyecto_vigencia }}
                @endif
            </td>
        </tr>
    </table>

    <!-- 3. TABLA PRINCIPAL DE RECORRIDOS DIARIOS -->
    <table class="main-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th rowspan="2" class="col-fecha">FECHA</th>
                <th rowspan="2" class="col-ruta">RUTA OPERATIVA DIARIA</th>
                <th rowspan="2" class="col-hora">HORA<br>INICIO</th>
                <th colspan="2" style="width: 9%; background-color: #d8d8d8; color: #000000;">HORAS DESCANSO</th>
                <th rowspan="2" class="col-hora">HORA<br>FINAL</th>
                <th rowspan="2" class="col-total">TOTAL<br>HORAS</th>
                <th rowspan="2" class="col-km">KM<br>INICIAL</th>
                <th rowspan="2" class="col-km">KM<br>FINAL</th>
                <th rowspan="2" class="col-km-tot">KM<br>TOTAL</th>
                <th rowspan="2" class="col-firma">FIRMA FUNCIONARIO<br>/ COORDINADOR</th>
                <th rowspan="2" class="col-conductor">CONDUCTOR ASIGNADO</th>
            </tr>
            <tr>
                <th class="sub-th" style="width: 4.5%;">INICIO</th>
                <th class="sub-th" style="width: 4.5%;">FIN</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($dias as $dia)
                <tr>
                    <td>
                        <div class="fecha-num">{{ $dia['numero'] ?? '' }}</div>
                        <div class="fecha-txt">{{ $dia['fecha_completa'] ?? '' }}</div>
                        @if (!empty($dia['is_closed']))
                            <span class="tag-finalizada">Cerrada</span>
                        @else
                            <span class="tag-abierta">En Ruta</span>
                        @endif
                    </td>
                    <td class="col-ruta">
                        <div class="ruta-tit">{{ $dia['ruta_unica'] ?? ($dia['ruta'] ?? 'Sin ruta definida') }}</div>
                        @if (!empty($dia['detalle_cierre']))
                            <div class="ruta-sub">{{ $dia['detalle_cierre'] }}</div>
                        @endif
                    </td>
                    <td>{{ $dia['hora_inicio'] ?? '' }}</td>
                    <td>{{ $dia['descanso_inicio'] ?? '' }}</td>
                    <td>{{ $dia['descanso_fin'] ?? '' }}</td>
                    <td>{{ $dia['hora_fin'] ?? '' }}</td>
                    <td>{{ $dia['total_hours'] ?? '' }}</td>
                    <td>{{ $dia['km_inicial'] ?? '' }}</td>
                    <td>{{ $dia['km_final'] ?? '' }}</td>
                    <td class="col-km-tot">{{ $dia['km_total'] ?? '' }}</td>
                    <td class="col-firma">
                        <div class="firma-box-inner">
                            @if (!empty($dia['firma_funcionario']))
                                <img src="data:image/png;base64,{{ $dia['firma_funcionario'] }}"
                                    alt="Firma funcionario">
                            @else
                                <span class="firma-placeholder">Sin firma</span>
                            @endif
                        </div>
                    </td>
                    <td class="col-conductor">
                        <div class="conductor-firma">
                            @if (!empty($dia['firma_conductor_recorrido']))
                                <img src="data:image/png;base64,{{ $dia['firma_conductor_recorrido'] }}"
                                    alt="Firma conductor">
                            @else
                                <span class="firma-placeholder">Sin firma</span>
                            @endif
                        </div>
                        <div class="conductor-nombre">{{ $dia['conductor'] ?? '' }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="sin-registros" colspan="12">Sin registros para el período consultado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('pdf.partials.evidence-status')

    <!-- 4. CIERRE: TOTALES, OBSERVACIONES, FIRMAS Y NOTA LEGAL -->
    <table class="cierre" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table class="totals-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 25%; font-weight: bold; color: #0f172a; text-transform: uppercase;">
                Consolidado del Período:
            </td>
            <td style="width: 25%; text-align: center;">
                Total Días Operados: <strong>{{ $totalDias }} Días</strong> ({{ $diasCerrados }} Cerrados)
            </td>
            <td style="width: 25%; text-align: center;">
                Total Horas Servicio: <strong>{{ $totalHoras }}</strong>
            </td>
            <td
                style="width: 25%; text-align: center; color: #000000; font-weight: bold; font-size: 7.3pt;">
                Total Kilómetros: {{ number_format((float) $totalKm, 0, ',', '.') }} Km
            </td>
        </tr>
    </table>

    <!-- 5. OBSERVACIONES Y CONTROL DE EVIDENCIA -->
    <table class="obs-section" cellpadding="0" cellspacing="0">
        <tr>
            <td class="obs-header">Observaciones Operacionales y Cumplimiento de Ruta</td>
        </tr>
        <tr>
            <td class="obs-body">
                {{ $observaciones ?? 'Sin observaciones registradas para el período.' }}
            </td>
        </tr>
    </table>

    <!-- 6. SECCIÓN DE FIRMAS DE RESPONSABILIDAD -->
    <table class="firmas-table" cellpadding="0" cellspacing="0">
        <tr>
            <!-- Firma Conductor Titular -->
            <td class="firma-side-left">
                <div class="sig-img-container">
                    @if (!empty($firma_conductor))
                        <img src="data:image/png;base64,{{ $firma_conductor }}" alt="Firma conductor">
                    @else
                        <div class="sig-placeholder">[ FIRMA PENDIENTE ]</div>
                    @endif
                </div>
                <div class="sig-line-bar">
                    <div class="sig-title-txt">{{ $conductorNombre ?: 'CONDUCTOR RESPONSABLE' }}</div>
                    <div class="sig-sub-txt">Conductor · Entrega conforme de la planilla y registros diarios</div>
                </div>
            </td>

            <!-- Firma Coordinador de Servicios / Cliente -->
            <td>
                <div class="sig-img-container">
                    @if (!empty($firma_recibido))
                        <img src="data:image/png;base64,{{ $firma_recibido }}" alt="Firma coordinador logístico">
                    @else
                        <div class="sig-placeholder">[ CERTIFICACIÓN PENDIENTE ]</div>
                    @endif
                </div>
                <div class="sig-line-bar">
                    <div class="sig-title-txt">COORDINADOR LOGÍSTICO</div>
                    <div class="sig-sub-txt" style="font-style: italic;">Recibo y verificación de servicios prestados
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- 7. NOTA LEGAL PESV Y CONTROL DOCUMENTAL -->
    <div class="legal-notice">
        <strong>CERTIFICACIÓN Y RESPONSABILIDAD PESV:</strong> El presente documento constituye soporte oficial de
        prestación de servicios de transporte público especial y control de jornada laboral / descansos de conductores
        conforme a la <strong>Ley 1503 de 2011</strong>, el <strong>Decreto 1079 de 2015</strong> y la
        <strong>Resolución 20223040040595 de 2022</strong> del Ministerio de Transporte. Los datos de horas y
        kilometrajes corresponden a la telemetría satelital verificada.
    </div>

    <table class="footer-bar-sub" cellpadding="0" cellspacing="0" style="width: 100%;">
        <tr>
            <td style="text-align: left; width: 70%;">
                {{ $empresa_transportadora ?? 'Empresa de Transporte Especial' }} · Sistema de Gestión de
                Operaciones
            </td>
                    <td style="text-align: right; width: 30%; font-weight: 600;">
                        OPE-F-006 · Versión 002
                    </td>
                </tr>
            </table>
            </td>
        </tr>
    </table>

</body>

</html>
