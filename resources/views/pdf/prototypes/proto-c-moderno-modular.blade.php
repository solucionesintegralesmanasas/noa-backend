@php
    $orient = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Prototipo C — Modular Moderno (Pantalla y Papel)</title>
    <style>
        @page {
            size: letter {{ $orient }};
            margin: 9mm 10mm 9mm 9mm;
        }

        * {
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7.6pt;
            line-height: 1.28;
            color: #1e293b;
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

        /* 1. Encabezado modular estilo tarjeta ejecutiva (alineado con facturación) */
        .top-header {
            border-bottom: 1.8pt solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 7px;
        }

        .monogram-box {
            width: 38px;
            height: 38px;
            background-color: #0f172a;
            color: #ffffff;
            text-align: center;
            border-radius: 5px;
        }

        .monogram-main {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 1px;
            color: #ffffff;
        }

        .monogram-sub {
            font-size: 5pt;
            color: #93c5fd;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .org-name {
            font-size: 9.5pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }

        .org-meta {
            font-size: 6.8pt;
            color: #475569;
            margin-top: 1px;
        }

        .doc-title-banner {
            font-size: 8pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .doc-card {
            border: 1.2pt solid #0f172a;
            border-radius: 4px;
            overflow: hidden;
        }

        .doc-card-head {
            background-color: #0f172a;
            color: #ffffff;
            padding: 3px 7px;
        }

        .doc-card-code {
            font-family: 'Courier', monospace;
            font-size: 7.5pt;
            font-weight: bold;
            color: #fcd34d;
        }

        .doc-card-body td {
            padding: 2px 7px;
            font-size: 6.6pt;
            border-bottom: 0.5pt solid #e2e8f0;
        }

        .doc-card-body tr:last-child td {
            border-bottom: none;
        }

        /* 2. Tarjetas de contexto con micro-etiqueta arriba del dato */
        .field-grid {
            border: 0.8pt solid #cbd5e1;
            margin-bottom: 6px;
        }

        .field-grid td {
            border: 0.6pt solid #e2e8f0;
            padding: 3.5px 6px;
            vertical-align: top;
            background-color: #ffffff;
        }

        .micro-lbl {
            display: block;
            font-size: 5.7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748b;
            margin-bottom: 1.5px;
        }

        .micro-val {
            font-size: 7.4pt;
            font-weight: bold;
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

        .placa-inline-tbl td.plate-chip {
            background-color: #fef08a;
            color: #713f12;
            border: 0.6pt solid #eab308 !important;
            font-family: 'Courier', monospace;
            font-weight: bold;
            font-size: 7.2pt;
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
            color: #475569;
            white-space: nowrap;
        }

        /* 3. Banda de KPIs */
        .kpi-grid {
            margin-bottom: 7px;
        }

        .kpi-grid td {
            border: 0.7pt solid #cbd5e1;
            border-top: 2pt solid #1e3a8a;
            background-color: #f8fafc;
            padding: 3.5px 6px;
        }

        .kpi-num {
            font-family: 'Courier', monospace;
            font-size: 9pt;
            font-weight: bold;
            color: #0f172a;
        }

        /* 4. Tabla principal limpia (sin líneas verticales internas) */
        .mod-table {
            border: 0.9pt solid #cbd5e1;
            margin-bottom: 6px;
            page-break-inside: auto;
        }

        .mod-table thead {
            display: table-header-group;
        }

        .mod-table tr {
            page-break-inside: avoid;
        }

        .mod-table thead th {
            background-color: #0f172a;
            color: #ffffff;
            padding: 4px 5px;
            font-size: 6.4pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-align: left;
        }

        .mod-table tbody td {
            border-bottom: 0.6pt solid #e2e8f0;
            padding: 3.4px 5px;
            font-size: 7pt;
        }

        .mod-table tbody tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .mono {
            font-family: 'Courier', monospace;
        }

        .txt-center {
            text-align: center !important;
        }

        .txt-right {
            text-align: right !important;
        }

        .badge {
            display: inline-block;
            padding: 1.2px 5px;
            font-size: 5.8pt;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 8px;
        }

        .badge-ok {
            background-color: #dcfce7;
            color: #166534;
            border: 0.5pt solid #86efac;
        }

        .badge-warn {
            background-color: #fef3c7;
            color: #92400e;
            border: 0.5pt solid #fcd34d;
        }

        .badge-alert {
            background-color: #fee2e2;
            color: #991b1b;
            border: 0.5pt solid #fca5a5;
        }

        .callout {
            background-color: #fffbeb;
            border: 0.7pt solid #fcd34d;
            border-left: 3pt solid #d97706;
            color: #78350f;
            padding: 4px 7px;
            font-size: 6.8pt;
            margin-bottom: 5px;
            page-break-inside: avoid;
        }

        /* 5. Cierre indivisible */
        .cierre {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .cierre > tbody > tr > td {
            padding: 0;
            border: none;
        }

        .closure-card {
            border: 0.8pt solid #cbd5e1;
            margin-bottom: 5px;
        }

        .closure-card td {
            padding: 5px 8px;
            vertical-align: top;
            border-right: 0.6pt solid #e2e8f0;
        }

        .closure-card td:last-child {
            border-right: none;
        }

        .sig-placeholder {
            height: 26px;
            padding-top: 10px;
            font-family: 'Courier', monospace;
            font-size: 6.2pt;
            color: #94a3b8;
            text-align: center;
        }

        .sig-bar {
            border-top: 0.9pt solid #0f172a;
            padding-top: 2.5px;
            text-align: center;
        }

        .legal-strip {
            background-color: #f8fafc;
            border: 0.6pt solid #cbd5e1;
            padding: 4px 7px;
            font-size: 6pt;
            color: #475569;
        }
    </style>
</head>
<body>

    <table class="top-header" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 18%; vertical-align: middle; text-align: left; padding-right: 6px;">
                @if (!empty($logo_data_url))
                    <img src="{{ $logo_data_url }}" style="max-height: 54px; max-width: 118px; width: auto; height: auto; display: block;" alt="Logo empresa">
                @else
                    <table class="monogram-box" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                <div class="monogram-main">{{ $empresa_siglas ?? 'EMP' }}</div>
                                <div class="monogram-sub">NOA</div>
                            </td>
                        </tr>
                    </table>
                @endif
            </td>
            <td style="width: 52%; padding-left: 6px; vertical-align: middle;">
                <div class="org-name">{{ $empresa_transportadora }}</div>
                <div class="org-meta">NIT {{ $empresa_nit }} · {{ $empresa_ciudad }} · {{ $subtitulo_documento }}</div>
                <div class="doc-title-banner">{{ $titulo_documento }}</div>
            </td>
            <td style="width: 30%; vertical-align: middle;">
                <table class="doc-card" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="doc-card-head" colspan="2">
                            <span style="font-size: 6pt; text-transform: uppercase; color: #cbd5e1;">FORMATO</span>
                            <span class="doc-card-code" style="float: right;">{{ $codigo_sgc }}</span>
                        </td>
                    </tr>
                    <tbody class="doc-card-body">
                        <tr><td style="color: #64748b;">Versión / Emisión</td><td class="mono txt-right" style="font-weight: bold;">v{{ $version_sgc }} · {{ $fecha_sgc }}</td></tr>
                        <tr><td style="color: #64748b;">Período</td><td class="mono txt-right" style="font-weight: bold; color: #1e3a8a;">{{ $periodo }}</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="field-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 24%;">
                <span class="micro-lbl">Vehículo Asignado</span>
                <table class="placa-inline-tbl" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="plate-chip">{{ $placa }}</td>
                        <td class="vehiculo-sep">&middot;</td>
                        <td class="vehiculo-interno">{{ $numero_interno }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 26%;">
                <span class="micro-lbl">Clase de Vehículo</span>
                <span class="micro-val">{{ $clase_vehiculo }}</span>
            </td>
            <td style="width: 25%;">
                <span class="micro-lbl">Sede Operativa</span>
                <span class="micro-val">{{ $sede }}</span>
            </td>
            <td style="width: 25%;">
                <span class="micro-lbl">Vigencia Contrato</span>
                <span class="micro-val mono">{{ $vigencia }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="micro-lbl">Empresa / Cliente Contratante</span>
                <span class="micro-val" style="color: #1e3a8a;">{{ $cliente }}</span>
                <span class="mono" style="font-size: 6.5pt; color: #64748b;"> (NIT {{ $cliente_nit }})</span>
            </td>
            <td colspan="2">
                <span class="micro-lbl">Proyecto / Frente de Servicio</span>
                <span class="micro-val">{{ $proyecto }}</span>
            </td>
        </tr>
    </table>

    <table class="kpi-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 25%;">
                <span class="micro-lbl">Días Operados</span>
                <span class="kpi-num">{{ $totales['dias'] }}</span>
                <span style="font-size: 6.2pt; color: #64748b;">({{ $totales['cerrados'] }} cerrados)</span>
            </td>
            <td style="width: 25%;">
                <span class="micro-lbl">Total Horas</span>
                <span class="kpi-num">{{ $totales['horas'] }} h</span>
            </td>
            <td style="width: 25%;">
                <span class="micro-lbl">Kilometraje</span>
                <span class="kpi-num" style="color: #1e3a8a;">{{ number_format($totales['km'], 0, ',', '.') }} km</span>
            </td>
            <td style="width: 25%;">
                <span class="micro-lbl">Peajes ({{ $totales['peajes'] }})</span>
                <span class="kpi-num">{{ $totales['valor_peajes'] }}</span>
            </td>
        </tr>
    </table>

    <table class="mod-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 10%;" class="txt-center">Fecha</th>
                <th style="width: 31%;">Ruta y Responsable</th>
                <th style="width: 7%;" class="txt-center">Inicio</th>
                <th style="width: 11%;" class="txt-center">Descanso</th>
                <th style="width: 7%;" class="txt-center">Fin</th>
                <th style="width: 7%;" class="txt-right">Horas</th>
                <th style="width: 8%;" class="txt-right">Km</th>
                <th style="width: 9%;" class="txt-center">Estado</th>
                <th style="width: 10%;">Conductor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $f)
                <tr>
                    <td class="txt-center">
                        <div class="mono" style="font-weight: bold;">{{ $f['fecha'] }}</div>
                        <div style="font-size: 5.7pt; color: #64748b;">{{ $f['dia_semana'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight: bold; color: #0f172a;">{{ $f['ruta'] }}</div>
                        <div style="font-size: 5.9pt; color: #64748b;">{{ $f['funcionario'] }}</div>
                    </td>
                    <td class="txt-center mono">{{ $f['hora_inicio'] }}</td>
                    <td class="txt-center mono" style="font-size: 6.3pt;">{{ $f['descanso'] }}</td>
                    <td class="txt-center mono">{{ $f['hora_fin'] }}</td>
                    <td class="txt-right mono" style="font-weight: bold;">{{ $f['total_horas'] }}</td>
                    <td class="txt-right mono" style="font-weight: bold; color: #1e3a8a;">{{ $f['km_total'] }}</td>
                    <td class="txt-center">
                        @if ($f['estado'] === 'CERRADA')
                            <span class="badge badge-ok">Cerrada</span>
                        @elseif ($f['estado'] === 'OBSERVACIÓN')
                            <span class="badge badge-warn">Novedad</span>
                        @else
                            <span class="badge badge-alert">Pendiente</span>
                        @endif
                    </td>
                    <td style="font-size: 6.3pt;">{{ $f['conductor'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="callout">
        <strong>PROTOTIPO C (MODULAR MODERNO):</strong> {{ $alerta }}
    </div>

    <table class="cierre" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table class="closure-card" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 100%; background-color: #f8fafc; border-bottom: 0.6pt solid #e2e8f0;" colspan="3">
                            <span class="micro-lbl">Observaciones Operacionales</span>
                            <span style="font-size: 6.9pt; color: #334155;">{{ $observaciones }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 41%;">
                            <div class="sig-placeholder">[ Firma digital verificada ]</div>
                            <div class="sig-bar">
                                <div style="font-size: 7pt; font-weight: bold;">{{ $conductor_pie }}</div>
                                <div style="font-size: 6pt; color: #64748b;">Conductor · Entrega conforme</div>
                            </div>
                        </td>
                        <td style="width: 41%;">
                            <div class="sig-placeholder">[ Certificación pendiente ]</div>
                            <div class="sig-bar">
                                <div style="font-size: 7pt; font-weight: bold;">{{ $coordinador_pie }}</div>
                                <div style="font-size: 6pt; color: #64748b;">Coordinador Logístico · Recibo</div>
                            </div>
                        </td>
                        <td style="width: 18%; text-align: center; vertical-align: middle;">
                            <img src="data:image/png;base64,{{ $qr_png_base64 }}" style="width: 46px; height: 46px;" alt="QR">
                            <div style="font-size: 5.5pt; color: #64748b; margin-top: 1px;">Validar QR</div>
                        </td>
                    </tr>
                </table>

                <table class="legal-strip" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 78%; text-align: justify;">
                            <strong>CERTIFICACIÓN PESV:</strong> Ley 1503 de 2011, Decreto 1079 de 2015 y Resolución 20223040040595 de 2022 del Ministerio de Transporte.
                        </td>
                        <td style="width: 22%; text-align: right;" class="mono">
                            Prototipo C · {{ $codigo_sgc }} v{{ $version_sgc }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
