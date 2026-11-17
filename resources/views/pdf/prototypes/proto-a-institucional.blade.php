@php
    $orient = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Prototipo A — Institucional SGC Refinado</title>
    <style>
        @page {
            size: letter {{ $orient }};
            margin: 8mm 10mm 8mm 8mm;
        }

        * {
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7.5pt;
            line-height: 1.25;
            color: #0f172a;
            background-color: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td, th {
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* 1. Encabezado Institucional SGC */
        .header-table {
            border: 1.3px solid #0f172a;
            margin-bottom: 4px;
        }

        .header-table td {
            border: 1px solid #0f172a;
            padding: 3px 6px;
        }

        .header-logo-cell {
            width: 19%;
            text-align: center;
            background-color: #ffffff;
            padding: 2px 4px !important;
        }

        .header-logo-img {
            max-height: 54px;
            max-width: 122px;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .logo-monogram {
            font-weight: bold;
            font-size: 13pt;
            color: #1e3a8a;
            line-height: 1;
            letter-spacing: 0.5px;
        }

        .logo-caption {
            font-size: 5.5pt;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .header-title-cell {
            width: 55%;
            text-align: center;
            padding: 4px 8px;
        }

        .company-name {
            font-size: 8.4pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.25px;
        }

        .company-sub {
            font-size: 6.4pt;
            color: #475569;
            margin-top: 1px;
        }

        .doc-main-title {
            font-size: 9.2pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 3px;
            letter-spacing: 0.3px;
        }

        .doc-sub-title {
            font-size: 6.2pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .header-meta-cell {
            width: 26%;
            padding: 0 !important;
        }

        .meta-table td {
            border: none !important;
            border-bottom: 0.8px solid #0f172a !important;
            padding: 2px 5px !important;
            font-size: 6.6pt;
        }

        .meta-table tr:last-child td {
            border-bottom: none !important;
        }

        .meta-label {
            width: 44%;
            background-color: #f8fafc;
            border-right: 0.8px solid #0f172a !important;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
        }

        .meta-val {
            font-family: 'Courier', monospace;
            font-weight: bold;
            color: #0f172a;
            text-align: center;
        }

        /* 2. Banda de sección */
        .section-banner {
            border: 1px solid #0f172a;
            border-left: 4px solid #1e3a8a;
            background-color: #e2e8f0;
            margin-top: 4px;
            margin-bottom: 0;
        }

        .section-banner td {
            padding: 2.5px 6px;
            font-size: 7pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* 3. Ficha de contexto */
        .info-table {
            border: 1px solid #0f172a;
            border-top: none;
            margin-bottom: 5px;
        }

        .info-table td {
            border: 0.7px solid #cbd5e1;
            padding: 2.6px 5px;
            font-size: 7.1pt;
            line-height: 1.25;
            vertical-align: middle;
        }

        .info-lbl {
            background-color: #f8fafc;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            font-size: 6.3pt;
            white-space: nowrap;
        }

        .info-val {
            color: #0f172a;
        }

        .placa-inline-tbl {
            width: auto !important;
            table-layout: auto !important;
            border-collapse: collapse;
            margin: 0;
        }

        .placa-inline-tbl td {
            border: none;
            padding: 0;
            vertical-align: middle !important;
            line-height: 1.1;
        }

        .placa-inline-tbl td.placa-badge {
            background-color: #fef08a;
            color: #854d0e;
            border: 0.6pt solid #ca8a04 !important;
            font-family: 'Courier', monospace;
            font-weight: bold;
            font-size: 7.1pt;
            padding: 1.2px 5px;
            white-space: nowrap;
        }

        .placa-inline-tbl td.vehiculo-sep {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            font-size: 8pt;
            color: #64748b;
            padding: 0 4px;
            text-align: center;
            white-space: nowrap;
        }

        .placa-inline-tbl td.vehiculo-interno {
            font-family: 'Courier', monospace;
            font-weight: bold;
            font-size: 7pt;
            color: #1e3a8a;
            white-space: nowrap;
        }

        /* 4. Tabla de registros */
        .main-table {
            border: 1.2px solid #0f172a;
            margin-bottom: 5px;
            page-break-inside: auto;
        }

        .main-table thead {
            display: table-header-group;
        }

        .main-table tr {
            page-break-inside: avoid;
        }

        .main-table thead th {
            background-color: #1e3a8a;
            color: #ffffff;
            border: 0.8px solid #0f172a;
            padding: 3.2px 3px;
            font-size: 6.6pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }

        .main-table tbody td {
            border: 0.6px solid #cbd5e1;
            padding: 3px 4px;
            font-size: 7pt;
        }

        .main-table tbody tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .mono {
            font-family: 'Courier', monospace;
        }

        .num {
            font-family: 'Courier', monospace;
            text-align: right;
            font-weight: bold;
        }

        .txt-center {
            text-align: center;
        }

        .tag {
            display: inline-block;
            padding: 1px 4px;
            font-size: 5.8pt;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 2px;
        }

        .tag-ok {
            background-color: #dcfce7;
            color: #166534;
            border: 0.6px solid #86efac;
        }

        .tag-warn {
            background-color: #fef3c7;
            color: #92400e;
            border: 0.6px solid #fcd34d;
        }

        .tag-alert {
            background-color: #fee2e2;
            color: #991b1b;
            border: 0.6px solid #fca5a5;
        }

        /* 5. Banda de alerta */
        .banda-alerta {
            border: 0.8px solid #b45309;
            border-left: 3.5px solid #b45309;
            background-color: #fffbeb;
            color: #78350f;
            padding: 3.5px 7px;
            font-size: 6.7pt;
            margin-bottom: 4px;
            page-break-inside: avoid;
        }

        /* 6. Cierre indivisible */
        .cierre {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .cierre > tbody > tr > td {
            padding: 0;
            border: none;
        }

        .totals-table {
            border: 1px solid #0f172a;
            background-color: #f1f5f9;
            margin-bottom: 4px;
        }

        .totals-table td {
            border: 0.7px solid #cbd5e1;
            padding: 3.5px 6px;
            font-size: 7.2pt;
            text-align: center;
        }

        .obs-table {
            border: 1px solid #0f172a;
            margin-bottom: 4px;
        }

        .obs-head {
            background-color: #e2e8f0;
            border-left: 4px solid #1e3a8a;
            border-bottom: 0.8px solid #0f172a;
            padding: 2.5px 6px;
            font-size: 6.8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .obs-body {
            padding: 4px 6px;
            font-size: 6.9pt;
            color: #334155;
        }

        .firmas-table {
            border: 1px solid #0f172a;
            margin-bottom: 4px;
        }

        .firmas-table td {
            padding: 5px 10px;
            vertical-align: bottom;
            text-align: center;
            border-right: 0.8px solid #0f172a;
        }

        .firmas-table td:last-child {
            border-right: none;
        }

        .sig-space {
            height: 30px;
            font-family: 'Courier', monospace;
            font-size: 6.4pt;
            color: #94a3b8;
            padding-top: 12px;
        }

        .sig-line {
            border-top: 1px solid #0f172a;
            padding-top: 2px;
        }

        .sig-name {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .sig-role {
            font-size: 6.2pt;
            color: #475569;
        }

        .legal-box {
            border: 1px solid #0f172a;
            background-color: #f8fafc;
            padding: 3.5px 6px;
            font-size: 6pt;
            color: #475569;
            text-align: justify;
        }

        .footer-meta {
            margin-top: 3px;
            font-size: 6.2pt;
            color: #64748b;
        }
    </style>
</head>
<body>

    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="header-logo-cell">
                @if (!empty($logo_data_url))
                    <img src="{{ $logo_data_url }}" class="header-logo-img" alt="Logo empresa">
                @else
                    <div class="logo-monogram">{{ $empresa_siglas ?? 'EMP' }}</div>
                    <div class="logo-caption">Sin logotipo</div>
                @endif
            </td>
            <td class="header-title-cell">
                <div class="company-name">{{ $empresa_transportadora }}</div>
                <div class="company-sub">NIT {{ $empresa_nit }} · {{ $empresa_ciudad }}</div>
                <div class="doc-main-title">{{ $titulo_documento }}</div>
                <div class="doc-sub-title">{{ $subtitulo_documento }}</div>
            </td>
            <td class="header-meta-cell">
                <table class="meta-table" cellpadding="0" cellspacing="0">
                    <tr><td class="meta-label">CÓDIGO</td><td class="meta-val">{{ $codigo_sgc }}</td></tr>
                    <tr><td class="meta-label">VERSIÓN</td><td class="meta-val">{{ $version_sgc }}</td></tr>
                    <tr><td class="meta-label">FECHA</td><td class="meta-val">{{ $fecha_sgc }}</td></tr>
                    <tr><td class="meta-label">PERÍODO</td><td class="meta-val" style="color: #1e3a8a;">{{ $periodo }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>1. Identificación del Vehículo, Contrato y Operación</td></tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 11%;">Vehículo:</td>
            <td class="info-val" style="width: 24%; padding-top: 1.5px; padding-bottom: 1.5px;">
                <table class="placa-inline-tbl" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="placa-badge">{{ $placa }}</td>
                        <td class="vehiculo-sep">&middot;</td>
                        <td class="vehiculo-interno">{{ $numero_interno }}</td>
                    </tr>
                </table>
            </td>
            <td class="info-lbl" style="width: 9%;">Clase:</td>
            <td class="info-val" style="width: 26%;">{{ $clase_vehiculo }}</td>
            <td class="info-lbl" style="width: 9%;">Sede:</td>
            <td class="info-val" style="width: 21%;">{{ $sede }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Cliente:</td>
            <td class="info-val" colspan="3" style="font-weight: bold; color: #1e3a8a;">{{ $cliente }}</td>
            <td class="info-lbl">NIT:</td>
            <td class="info-val mono" style="font-weight: bold;">{{ $cliente_nit }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Proyecto:</td>
            <td class="info-val" colspan="3"><strong>{{ $proyecto }}</strong></td>
            <td class="info-lbl">Vigencia:</td>
            <td class="info-val mono">{{ $vigencia }}</td>
        </tr>
    </table>

    <table class="main-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 10%;">Fecha</th>
                <th style="width: 30%;">Ruta / Descripción Operativa</th>
                <th style="width: 7%;">Inicio</th>
                <th style="width: 11%;">Descanso</th>
                <th style="width: 7%;">Fin</th>
                <th style="width: 7%;">Horas</th>
                <th style="width: 8%;">Km Total</th>
                <th style="width: 10%;">Estado</th>
                <th style="width: 10%;">Conductor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $f)
                <tr>
                    <td class="txt-center">
                        <div class="mono" style="font-weight: bold;">{{ $f['fecha'] }}</div>
                        <div style="font-size: 5.8pt; color: #64748b;">{{ $f['dia_semana'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight: bold; color: #0f172a;">{{ $f['ruta'] }}</div>
                        <div style="font-size: 5.8pt; color: #64748b;">Resp.: {{ $f['funcionario'] }}</div>
                    </td>
                    <td class="txt-center mono">{{ $f['hora_inicio'] }}</td>
                    <td class="txt-center mono" style="font-size: 6.3pt;">{{ $f['descanso'] }}</td>
                    <td class="txt-center mono">{{ $f['hora_fin'] }}</td>
                    <td class="num">{{ $f['total_horas'] }}</td>
                    <td class="num" style="color: #1e3a8a;">{{ $f['km_total'] }} km</td>
                    <td class="txt-center">
                        @if ($f['estado'] === 'CERRADA')
                            <span class="tag tag-ok">Cerrada</span>
                        @elseif ($f['estado'] === 'OBSERVACIÓN')
                            <span class="tag tag-warn">Novedad</span>
                        @else
                            <span class="tag tag-alert">Pendiente</span>
                        @endif
                    </td>
                    <td style="font-size: 6.3pt;">{{ $f['conductor'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="banda-alerta">
        <strong>PROTOTIPO A (INSTITUCIONAL SGC):</strong> {{ $alerta }}
    </div>

    <table class="cierre" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table class="totals-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 22%; font-weight: bold; text-align: left; text-transform: uppercase;">Consolidado</td>
                        <td style="width: 20%;">Días: <strong>{{ $totales['dias'] }}</strong> ({{ $totales['cerrados'] }} cerr.)</td>
                        <td style="width: 18%;">Horas: <strong class="mono">{{ $totales['horas'] }}</strong></td>
                        <td style="width: 20%;">Recorrido: <strong class="mono" style="color: #1e3a8a;">{{ number_format($totales['km'], 0, ',', '.') }} km</strong></td>
                        <td style="width: 20%;">Peajes ({{ $totales['peajes'] }}): <strong class="mono">{{ $totales['valor_peajes'] }}</strong></td>
                    </tr>
                </table>

                <table class="obs-table" cellpadding="0" cellspacing="0">
                    <tr><td class="obs-head">Observaciones y Control Operacional</td></tr>
                    <tr><td class="obs-body">{{ $observaciones }}</td></tr>
                </table>

                <table class="firmas-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 40%;">
                            <div class="sig-space">[ FIRMA DIGITAL VERIFICADA ]</div>
                            <div class="sig-line">
                                <div class="sig-name">{{ $conductor_pie }}</div>
                                <div class="sig-role">Conductor · Entrega conforme</div>
                            </div>
                        </td>
                        <td style="width: 40%;">
                            <div class="sig-space">[ CERTIFICACIÓN PENDIENTE ]</div>
                            <div class="sig-line">
                                <div class="sig-name">{{ $coordinador_pie }}</div>
                                <div class="sig-role">Coordinador Logístico · Verificación</div>
                            </div>
                        </td>
                        <td style="width: 20%; vertical-align: middle;">
                            <img src="data:image/png;base64,{{ $qr_png_base64 }}" style="width: 46px; height: 46px;" alt="QR">
                            <div style="font-size: 5.5pt; color: #475569; margin-top: 1px;">Validar documento</div>
                        </td>
                    </tr>
                </table>

                <div class="legal-box">
                    <strong>CERTIFICACIÓN PESV:</strong> Documento generado conforme a la Ley 1503 de 2011, Decreto 1079 de 2015 y Resolución 20223040040595 de 2022 del Ministerio de Transporte.
                </div>

                <table class="footer-meta" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 70%; text-align: left;">{{ $empresa_transportadora }} · Prototipo A (Institucional SGC Refinado)</td>
                        <td style="width: 30%; text-align: right;" class="mono">{{ $codigo_sgc }} · v{{ $version_sgc }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
