@php
    $orient = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Prototipo B — Técnico Minimalista (Ahorro de Tinta)</title>
    <style>
        @page {
            size: letter {{ $orient }};
            margin: 9mm 11mm 9mm 9mm;
        }

        * {
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7.8pt;
            line-height: 1.3;
            color: #111827;
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

        /* 1. Encabezado liviano sin bloques oscuros (mínimo gasto de tóner) */
        .header-wrap {
            border-bottom: 1.5pt solid #0f172a;
            padding-bottom: 5px;
            margin-bottom: 7px;
        }

        .brand-mark {
            display: inline-block;
            border: 1.2pt solid #0f172a;
            padding: 3px 6px;
            font-weight: bold;
            font-size: 11pt;
            letter-spacing: 1px;
            color: #0f172a;
        }

        .doc-kicker {
            font-size: 6.4pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #475569;
        }

        .doc-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .doc-company {
            font-size: 7.4pt;
            color: #334155;
            margin-top: 1px;
        }

        .meta-box {
            border: 0.8pt solid #0f172a;
        }

        .meta-box td {
            border-bottom: 0.5pt solid #cbd5e1;
            padding: 2px 5px;
            font-size: 6.6pt;
        }

        .meta-box tr:last-child td {
            border-bottom: none;
        }

        .meta-k {
            color: #475569;
            text-transform: uppercase;
            font-weight: bold;
            width: 44%;
        }

        .meta-v {
            font-family: 'Courier', monospace;
            font-weight: bold;
            text-align: right;
            color: #0f172a;
        }

        /* 2. Ficha de contexto limpia con líneas horizontales */
        .section-heading {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #0f172a;
            border-bottom: 0.8pt solid #94a3b8;
            padding-bottom: 2px;
            margin-bottom: 4px;
        }

        .context-table {
            margin-bottom: 8px;
        }

        .context-table td {
            border-bottom: 0.5pt solid #e2e8f0;
            padding: 3px 4px;
            font-size: 7.3pt;
        }

        .ctx-lbl {
            color: #475569;
            font-size: 6.5pt;
            text-transform: uppercase;
            font-weight: bold;
        }

        .ctx-val {
            color: #000000;
            font-weight: bold;
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

        .placa-inline-tbl td.placa-outline {
            border: 0.8pt solid #0f172a !important;
            padding: 1.2px 5px;
            font-family: 'Courier', monospace;
            font-weight: bold;
            font-size: 7.1pt;
            letter-spacing: 0.4px;
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
            color: #334155;
            white-space: nowrap;
        }

        /* 3. Tabla principal sin rejas pesadas ni fondos oscuros */
        .data-table {
            margin-bottom: 7px;
            page-break-inside: auto;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table tr {
            page-break-inside: avoid;
        }

        .data-table thead th {
            background-color: #f1f5f9;
            color: #0f172a;
            border-top: 1.2pt solid #0f172a;
            border-bottom: 1.4pt solid #0f172a;
            padding: 4px 4px;
            font-size: 6.7pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
        }

        .data-table tbody td {
            border-bottom: 0.5pt solid #cbd5e1;
            padding: 3.6px 4px;
            font-size: 7.2pt;
        }

        .data-table tbody tr:last-child td {
            border-bottom: 1.2pt solid #0f172a;
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
            text-align: center !important;
        }

        .txt-right {
            text-align: right !important;
        }

        /* Badges de alto contraste aptos para B&N y fotocopia */
        .pill {
            display: inline-block;
            padding: 1px 4px;
            font-size: 5.9pt;
            font-weight: bold;
            text-transform: uppercase;
            border: 0.7pt solid #0f172a;
        }

        .pill-solid {
            background-color: #f8fafc;
            border-color: #475569;
            color: #0f172a;
        }

        .pill-warn {
            background-color: #ffffff;
            border-style: dashed;
            border-color: #0f172a;
            color: #0f172a;
        }

        .pill-alert {
            background-color: #fef2f2;
            border-color: #991b1b;
            color: #991b1b;
        }

        /* 4. Aviso discreto */
        .notice-line {
            border: 0.8pt solid #0f172a;
            border-left: 3pt solid #0f172a;
            padding: 4px 7px;
            font-size: 6.8pt;
            margin-bottom: 6px;
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

        .summary-strip {
            border-top: 0.8pt solid #0f172a;
            border-bottom: 0.8pt solid #0f172a;
            background-color: #f8fafc;
            margin-bottom: 6px;
        }

        .summary-strip td {
            padding: 4px 6px;
            font-size: 7.3pt;
            border-right: 0.5pt solid #cbd5e1;
        }

        .summary-strip td:last-child {
            border-right: none;
        }

        .sum-lbl {
            display: block;
            font-size: 5.8pt;
            text-transform: uppercase;
            color: #475569;
        }

        .sum-val {
            font-family: 'Courier', monospace;
            font-weight: bold;
            font-size: 8pt;
            color: #000000;
        }

        .obs-block {
            border-bottom: 0.5pt solid #cbd5e1;
            padding-bottom: 5px;
            margin-bottom: 6px;
            font-size: 7pt;
            color: #1f2937;
        }

        .sign-grid td {
            padding: 4px 8px 2px 0;
            vertical-align: bottom;
        }

        .sign-box {
            border-top: 0.9pt solid #0f172a;
            padding-top: 3px;
            margin-top: 26px;
        }

        .sign-title {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .sign-sub {
            font-size: 6.1pt;
            color: #475569;
        }

        .legal-foot {
            margin-top: 6px;
            padding-top: 4px;
            border-top: 0.5pt solid #94a3b8;
            font-size: 6pt;
            color: #475569;
            text-align: justify;
        }
    </style>
</head>
<body>

    <table class="header-wrap" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 19%; vertical-align: middle; text-align: left; padding-right: 6px;">
                @if (!empty($logo_data_url))
                    <img src="{{ $logo_data_url }}" style="max-height: 54px; max-width: 120px; width: auto; height: auto; display: block;" alt="Logo empresa">
                @else
                    <span class="brand-mark">{{ $empresa_siglas ?? 'EMP' }}</span>
                @endif
            </td>
            <td style="width: 53%; vertical-align: middle; padding-right: 10px;">
                <div class="doc-kicker">{{ $subtitulo_documento }}</div>
                <div class="doc-title">{{ $titulo_documento }}</div>
                <div class="doc-company">{{ $empresa_transportadora }} · NIT {{ $empresa_nit }} · {{ $empresa_ciudad }}</div>
            </td>
            <td style="width: 28%; vertical-align: middle;">
                <table class="meta-box" cellpadding="0" cellspacing="0">
                    <tr><td class="meta-k">Código</td><td class="meta-v">{{ $codigo_sgc }}</td></tr>
                    <tr><td class="meta-k">Versión</td><td class="meta-v">{{ $version_sgc }}</td></tr>
                    <tr><td class="meta-k">Fecha</td><td class="meta-v">{{ $fecha_sgc }}</td></tr>
                    <tr><td class="meta-k">Período</td><td class="meta-v">{{ $periodo }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-heading">Datos de Operación y Contrato</div>
    <table class="context-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="ctx-lbl" style="width: 11%;">Vehículo</td>
            <td class="ctx-val" style="width: 24%; padding-top: 2px; padding-bottom: 2px;">
                <table class="placa-inline-tbl" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="placa-outline">{{ $placa }}</td>
                        <td class="vehiculo-sep">&middot;</td>
                        <td class="vehiculo-interno">{{ $numero_interno }}</td>
                    </tr>
                </table>
            </td>
            <td class="ctx-lbl" style="width: 9%;">Clase</td>
            <td class="ctx-val" style="width: 26%;">{{ $clase_vehiculo }}</td>
            <td class="ctx-lbl" style="width: 9%;">Sede</td>
            <td class="ctx-val" style="width: 21%;">{{ $sede }}</td>
        </tr>
        <tr>
            <td class="ctx-lbl">Cliente</td>
            <td class="ctx-val" colspan="3">{{ $cliente }}</td>
            <td class="ctx-lbl">NIT</td>
            <td class="ctx-val mono">{{ $cliente_nit }}</td>
        </tr>
        <tr>
            <td class="ctx-lbl">Proyecto</td>
            <td class="ctx-val" colspan="3">{{ $proyecto }}</td>
            <td class="ctx-lbl">Vigencia</td>
            <td class="ctx-val mono">{{ $vigencia }}</td>
        </tr>
    </table>

    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 10%;" class="txt-center">Fecha</th>
                <th style="width: 31%;">Ruta / Actividad</th>
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
                        <span class="mono" style="font-weight: bold;">{{ $f['fecha'] }}</span><br>
                        <span style="font-size: 5.8pt; color: #64748b;">{{ $f['dia_semana'] }}</span>
                    </td>
                    <td>
                        <div style="font-weight: bold; color: #000;">{{ $f['ruta'] }}</div>
                        <div style="font-size: 6pt; color: #475569;">{{ $f['funcionario'] }}</div>
                    </td>
                    <td class="txt-center mono">{{ $f['hora_inicio'] }}</td>
                    <td class="txt-center mono" style="font-size: 6.4pt;">{{ $f['descanso'] }}</td>
                    <td class="txt-center mono">{{ $f['hora_fin'] }}</td>
                    <td class="num">{{ $f['total_horas'] }}</td>
                    <td class="num">{{ $f['km_total'] }}</td>
                    <td class="txt-center">
                        @if ($f['estado'] === 'CERRADA')
                            <span class="pill pill-solid">Cerrada</span>
                        @elseif ($f['estado'] === 'OBSERVACIÓN')
                            <span class="pill pill-warn">Novedad</span>
                        @else
                            <span class="pill pill-alert">Pendiente</span>
                        @endif
                    </td>
                    <td style="font-size: 6.4pt;">{{ $f['conductor'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="notice-line">
        <strong>PROTOTIPO B (TÉCNICO MINIMALISTA / B&amp;N):</strong> {{ $alerta }}
    </div>

    <table class="cierre" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table class="summary-strip" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 22%;">
                            <span class="sum-lbl">Días Operados</span>
                            <span class="sum-val">{{ $totales['dias'] }} ({{ $totales['cerrados'] }} cerrados)</span>
                        </td>
                        <td style="width: 22%;">
                            <span class="sum-lbl">Total Horas</span>
                            <span class="sum-val">{{ $totales['horas'] }} h</span>
                        </td>
                        <td style="width: 28%;">
                            <span class="sum-lbl">Kilometraje Total</span>
                            <span class="sum-val">{{ number_format($totales['km'], 0, ',', '.') }} km</span>
                        </td>
                        <td style="width: 28%;">
                            <span class="sum-lbl">Peajes ({{ $totales['peajes'] }})</span>
                            <span class="sum-val">{{ $totales['valor_peajes'] }}</span>
                        </td>
                    </tr>
                </table>

                <div class="obs-block">
                    <strong>OBSERVACIONES:</strong> {{ $observaciones }}
                </div>

                <table class="sign-grid" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 41%;">
                            <div style="font-size: 6.5pt; font-family: 'Courier', monospace; color: #64748b;">[ Firma digital registrada ]</div>
                            <div class="sign-box">
                                <div class="sign-title">{{ $conductor_pie }}</div>
                                <div class="sign-sub">Conductor responsable · Entrega</div>
                            </div>
                        </td>
                        <td style="width: 41%;">
                            <div style="font-size: 6.5pt; font-family: 'Courier', monospace; color: #64748b;">[ Certificación pendiente ]</div>
                            <div class="sign-box">
                                <div class="sign-title">{{ $coordinador_pie }}</div>
                                <div class="sign-sub">Coordinador logístico · Recibo</div>
                            </div>
                        </td>
                        <td style="width: 18%; text-align: right; padding-right: 0;">
                            <img src="data:image/png;base64,{{ $qr_png_base64 }}" style="width: 48px; height: 48px;" alt="QR">
                        </td>
                    </tr>
                </table>

                <div class="legal-foot">
                    <strong>SOPORTE NORMATIVO PESV:</strong> Ley 1503 de 2011, Decreto 1079 de 2015 y Resolución 20223040040595 de 2022 del Ministerio de Transporte. · Prototipo B (Técnico Minimalista) · {{ $codigo_sgc }} v{{ $version_sgc }}.
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
