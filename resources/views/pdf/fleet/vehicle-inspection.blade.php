@extends('pdf.layouts.base')

@php
    $title = 'Inspección Preoperacional Diaria - Formato SGFT04 DomPDF';
    $empresa = $record->company ?? ($record->vehicle->company ?? null);
    $vehiculo = $record->vehicle ?? null;
    $conductor = $record->driver ?? null;
    $licencia = ($conductor && method_exists($conductor, 'licenciaActual')) ? $conductor->licenciaActual() : null;
    $fechaInspeccion = ! empty($record->inspection_date)
        ? \Carbon\Carbon::parse($record->inspection_date)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
        : '';
    $kilometraje = isset($record->mileage) ? number_format((int) $record->mileage, 0, ',', '.').' km' : '';
    $nombreEmpresa = $empresa->business_name ?? '';
    $nitEmpresa = $empresa->document_number ?? '';
    $marcaLinea = collect([$vehiculo->brand->description ?? null, $vehiculo->line ?? null])->filter()->implode(' · ');
    if (! empty($vehiculo->model)) {
        $marcaLinea = trim($marcaLinea.' ('.$vehiculo->model.')');
    }
    $resultados = collect($groupedResults ?? []);
    $columnasCategorias = [[], [], []];
    $conteosColumnas = [0, 0, 0];
    foreach ($resultados as $categoria => $items) {
        $indice = array_search(min($conteosColumnas), $conteosColumnas, true);
        $columnasCategorias[$indice][] = $categoria;
        $conteosColumnas[$indice] += count($items) + 2;
    }
    $fallas = $resultados->flatten(1)->filter(fn ($r) => ($r->status ?? '') === 'NO_APROBADO')->values();
    $lineasDano = $fallas
        ->filter(fn ($r) => ! empty($r->observations))
        ->map(fn ($r) => 'Dañ.: '.($r->item->item_name ?? '').' — '.$r->observations)
        ->values();
@endphp

{{-- El formato SGFT04 trae su propio encabezado y no lleva marca de agua; solo el membrete (modo "membrete" de la empresa) se conserva. --}}
@section('watermarks')
    @if (!empty($letterhead ?? null))
        <div id="letterhead-banner">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @endif
@endsection

@section('header')
@endsection

{{-- Estilos del formato SGFT04 (sin cambios). Van en crudo porque la base los inyecta dentro de su <style>. --}}
@push('styles')
        /* Solo CSS que DomPDF reconoce (con avisos activos una propiedad ajena tumba el render). */
        .sheet {
            width: auto;
            margin: 0 auto;
            background: #ffffff;
            padding: 8mm 10mm;
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

        /* 1. ENCABEZADO INSTITUCIONAL SGC / PESV */
        .header-table {
            width: 100%;
            border: 1.5px solid #0f172a;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .header-table td {
            border: 1px solid #0f172a;
            padding: 3px 5px;
            vertical-align: middle;
        }

        .logo-cell {
            width: 20%;
            text-align: center;
            background: #ffffff;
            padding: 4px;
        }

        .title-cell {
            width: 55%;
            text-align: center;
            padding: 3px 6px;
        }

        .company-name {
            font-size: 8.2pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }

        .doc-main-title {
            font-size: 9.3pt;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 2px;
            line-height: 1.15;
        }

        .doc-sub-title {
            font-size: 6.8pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-top: 1px;
        }

        .meta-cell {
            width: 25%;
            padding: 0 !important;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            border: none !important;
            border-bottom: 1px solid #0f172a !important;
            padding: 2.2px 5px !important;
            font-size: 7.2pt;
        }

        .meta-table tr:last-child td {
            border-bottom: none !important;
        }

        .meta-label {
            font-weight: bold;
            color: #334155;
            width: 48%;
            background-color: #f8fafc;
            border-right: 1px solid #0f172a !important;
            text-transform: uppercase;
            font-size: 6.8pt;
        }

        .meta-value {
            font-weight: bold;
            color: #0f172a;
            text-align: center;
            font-family: 'Courier', monospace;
            font-size: 7.5pt;
        }

        /* 2. BANDAS DE SECCIÓN */
        .section-banner {
            width: 100%;
            border: 1px solid #0f172a;
            background-color: #e2e8f0;
            border-left: 5px solid #1e3a8a;
            margin-top: 4px;
            margin-bottom: 0;
        }

        .section-banner td {
            padding: 2.8px 8px;
            font-size: 7.5pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        /* 3. BLOQUE DATOS DE VEHÍCULO Y DIAGRAMA DE DAÑOS */
        .info-grid-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .info-cell-left {
            width: 50%;
            vertical-align: top;
            border-right: 1px solid #0f172a;
            padding: 0;
        }

        .info-cell-right {
            width: 50%;
            vertical-align: top;
            padding: 4px 6px;
            background-color: #f8fafc;
        }

        .details-subtable {
            width: 100%;
            border-collapse: collapse;
        }

        .details-subtable td {
            padding: 2.2px 6px;
            font-size: 7.3pt;
            border-bottom: 1px solid #e2e8f0;
        }

        .details-subtable tr:last-child td {
            border-bottom: none;
        }

        .field-lbl {
            width: 38%;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            font-size: 6.8pt;
            background-color: #f8fafc;
            border-right: 1px solid #e2e8f0;
        }

        .field-val {
            font-weight: 600;
            color: #0f172a;
        }

        .placa-badge {
            background-color: #fef08a;
            color: #854d0e;
            font-family: 'Courier', monospace;
            font-size: 8.5pt;
            font-weight: 800;
            padding: 0.5px 5px;
            border-radius: 2px;
            border: 1px solid #facc15;
            letter-spacing: 0.5px;
        }

        .mono-cell {
            font-family: 'Courier', monospace;
            font-weight: bold;
        }

        .photo-damage-box {
            border: 1px dashed #94a3b8;
            border-radius: 3px;
            background-color: #ffffff;
            height: 72px;
            text-align: center;
            vertical-align: middle;
            display: table;
            width: 100%;
        }

        .photo-inner {
            display: table-cell;
            vertical-align: middle;
            padding: 4px;
        }

        .hint-text {
            font-size: 6.6pt;
            color: #64748b;
            margin-bottom: 3px;
            font-style: italic;
        }

        .damage-lines-table {
            width: 100%;
            margin-top: 4px;
            border-collapse: collapse;
        }

        .damage-lines-table td {
            border-bottom: 1px dotted #94a3b8;
            height: 12px;
            font-size: 6.8pt;
            color: #334155;
        }

        /* 4. REVISIÓN DE PUNTOS CLAVE (CHECKLIST EN 3 COLUMNAS) */
        .checklist-outer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            margin-bottom: 4px;
        }

        .checklist-col {
            width: 33.333%;
            vertical-align: top;
            padding: 0 2px;
        }

        .checklist-col:first-child {
            padding-left: 0;
        }

        .checklist-col:last-child {
            padding-right: 0;
        }

        .cat-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .cat-th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-size: 6.7pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2.2px 4px;
            border: 1px solid #94a3b8;
            letter-spacing: 0.2px;
        }

        .cat-th-ok {
            width: 15%;
            text-align: center;
            background-color: #f1f5f9;
            border: 1px solid #94a3b8;
            font-size: 6.7pt;
            font-weight: bold;
        }

        .cat-table td {
            border: 1px solid #cbd5e1;
            padding: 1.6px 4px;
            font-size: 6.9pt;
            line-height: 1.15;
        }

        .item-name {
            color: #1e293b;
        }

        .check-cell {
            text-align: center;
            width: 15%;
            padding: 1px !important;
        }

        .answer-box {
            width: 11px;
            height: 11px;
            border: 1px solid #0f172a;
            display: inline-block;
            text-align: center;
            line-height: 10px;
            font-size: 7.5pt;
            font-weight: bold;
            /* Arial (base 14) no trae los glifos de visto/equis; DejaVu Sans sí. */
            font-family: 'DejaVu Sans', sans-serif;
            color: #15803d;
            background-color: #ffffff;
            border-radius: 1px;
        }

        /* 5. OBSERVACIONES */
        .obs-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-collapse: collapse;
            margin-top: 3px;
            margin-bottom: 4px;
        }

        .obs-th {
            background-color: #e2e8f0;
            border-left: 5px solid #1e3a8a;
            color: #0f172a;
            font-size: 7.2pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2.5px 8px;
            text-align: left;
        }

        .obs-content {
            border: 1px solid #0f172a;
            border-top: none;
            height: 32px;
            padding: 3px 6px;
            font-size: 7.2pt;
            color: #334155;
            vertical-align: top;
            background-color: #ffffff;
            line-height: 1.3;
        }

        /* 6. FIRMAS DE RESPONSABILIDAD PESV */
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            page-break-inside: avoid;
        }

        .sig-cell {
            width: 33.333%;
            padding: 2px 10px;
            vertical-align: bottom;
            text-align: center;
        }

        .sig-space {
            height: 28px;
            text-align: center;
            vertical-align: bottom;
        }

        .sig-line {
            border-top: 1.2px solid #0f172a;
            padding-top: 2px;
            text-align: center;
        }

        .sig-name {
            font-size: 7.2pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
        }

        .sig-role {
            font-size: 6.6pt;
            color: #475569;
        }

        /* 7. PIE DE PÁGINA */
        .footer-bar {
            margin-top: 4px;
            border-top: 1px solid #cbd5e1;
            padding-top: 2px;
            font-size: 6.4pt;
            color: #64748b;
        }
        /* Revierte, solo en lo que la base cambia, los valores que el formato SGFT04 usa por defecto. */
        body {
            margin: 0;
        }

        td {
            border: 0;
            padding: 0;
        }

        strong {
            font-size: inherit;
        }

        .header-table td {
            width: auto;
            text-align: left;
        }

        .header-table .logo-cell {
            width: 20%;
            text-align: center;
        }

        .header-table .title-cell {
            width: 55%;
            text-align: center;
        }

        .header-table .meta-cell {
            width: 25%;
        }

        .header-table .meta-label {
            width: 48%;
        }

        .header-table .meta-value {
            text-align: center;
        }
@endpush

@section('content')
    <div class="sheet">
        <!-- 1. ENCABEZADO INSTITUCIONAL SGC / PESV -->
        <table class="header-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="logo-cell">
                    @if (!empty($data['logo'] ?? null))
                        <img src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo" style="max-width: 100%; max-height: 40px;">
                    @endif
                </td>
                <td class="title-cell">
                    <div class="company-name">{{ $nombreEmpresa }}</div>
                    <div class="doc-main-title">INSPECCIÓN PREOPERACIONAL DIARIA DE VEHÍCULOS</div>
                    <div class="doc-sub-title">PLAN ESTRATÉGICO DE SEGURIDAD VIAL (PESV) · CONTROL Y MANTENIMIENTO</div>
                </td>
                <td class="meta-cell">
                    <table class="meta-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="meta-label">CÓDIGO</td>
                            <td class="meta-value">SGFT04</td>
                        </tr>
                        <tr>
                            <td class="meta-label">VERSIÓN</td>
                            <td class="meta-value">01</td>
                        </tr>
                        <tr>
                            <td class="meta-label">VIGENCIA</td>
                            <td class="meta-value">16/10/2026</td>
                        </tr>
                        <tr>
                            <td class="meta-label">PÁGINA</td>
                            <td class="meta-value">1 de 1</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- 2. DATOS DEL VEHÍCULO, CONDUCTOR Y REGISTRO FOTOGRÁFICO DE DAÑOS -->
        <table class="section-banner" cellpadding="0" cellspacing="0" style="margin-bottom: 6px;">
            <tr>
                <td style="width: 50%;">1. Datos del Vehículo y Conductor</td>
                <td style="width: 50%; border-left: 1px solid #0f172a;">2. Registro de Inspección Física del Automotor
                </td>
            </tr>
        </table>

        <table class="info-grid-table" cellpadding="0" cellspacing="0">
            <tr>
                <!-- Columna Izquierda: Datos Operativos -->
                <td class="info-cell-left">
                    <table class="details-subtable" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="field-lbl">Fecha Inspección:</td>
                            <td class="field-val">{{ $fechaInspeccion }}</td>
                        </tr>
                        <tr>
                            <td class="field-lbl">Placa Vehículo:</td>
                            <td class="field-val"><span class="placa-badge">{{ $vehiculo->vehicle_license_plate ?? '' }}</span> &nbsp; <span
                                    style="font-size: 6.8pt; color: #475569;">Móvil: <strong>{{ $vehiculo->internal_number ?? '' }}</strong></span></td>
                        </tr>
                        <tr>
                            <td class="field-lbl">Marca / Línea:</td>
                            <td class="field-val">{{ $marcaLinea }}</td>
                        </tr>
                        <tr>
                            <td class="field-lbl">Kilometraje Actual:</td>
                            <td class="field-val"><span class="mono-cell" style="color: #1e3a8a;">{{ $kilometraje }}</span></td>
                        </tr>
                        <tr>
                            <td class="field-lbl">Conductor Titular:</td>
                            <td class="field-val uppercase">{{ $conductor->full_name ?? '' }}<br><span
                                    style="font-size: 6.6pt; color: #475569;">Documento {{ $conductor->document_number ?? '' }}</span></td>
                        </tr>
                        <tr>
                            <td class="field-lbl">N° Licencia / Cat.:</td>
                            <td class="field-val"><span class="mono-cell">{{ $licencia->number ?? '' }}</span> &nbsp; <strong>Cat. {{ $licencia->category ?? '' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="field-lbl">Inspector Resp.:</td>
                            <td class="field-val uppercase" style="color: #0f172a;">{{ $record->inspector_name ?? '' }}</td>
                        </tr>
                    </table>
                </td>

                <!-- Columna Derecha: Recuadro Fotográfico y Daños -->
                <td class="info-cell-right">
                    <div class="hint-text">Encierre cualquier daño observado en un círculo o detalle visualmente el
                        estado exterior:</div>
                    <div class="photo-damage-box">
                        <div class="photo-inner">
                            <div
                                style="font-size: 6.8pt; font-weight: bold; color: #334155; text-transform: uppercase;">
                                Espacio para Fotografía del Vehículo / Diagrama
                            </div>
                            <div style="font-size: 5.8pt; color: #94a3b8;">Fotografía digital o diligenciamiento de
                                daños</div>
                        </div>
                    </div>
                    <table class="damage-lines-table" cellpadding="0" cellspacing="0">
                        @for ($i = 0; $i < 2; $i++)
                            <tr>
                                <td>{{ $lineasDano[$i] ?? '' }}&nbsp;</td>
                            </tr>
                        @endfor
                    </table>
                </td>
            </tr>
        </table>

        <!-- 3. REVISIÓN DE PUNTOS CLAVE (CHECKLIST MULTICOLUMNA) -->
        <table class="section-banner" cellpadding="0" cellspacing="0" style="margin-top: 4px; margin-bottom: 6px;">
            <tr>
                <td>3. Revisión de Puntos Clave de Seguridad Vial y Operatividad</td>
            </tr>
        </table>

        <table class="checklist-outer-table" cellpadding="0" cellspacing="0">
            <tr>
                @foreach ($columnasCategorias as $columna)
                    <td class="checklist-col">
                        @foreach ($columna as $categoria)
                            @php
                                $items = $resultados[$categoria];
                                $aplicables = collect($items)->filter(fn ($r) => ($r->status ?? '') !== 'NO_APLICA');
                                $aprobados = $aplicables->filter(fn ($r) => ($r->status ?? '') === 'APROBADO')->count();
                                $conFalla = $aplicables->contains(fn ($r) => ($r->status ?? '') === 'NO_APROBADO');
                            @endphp
                            <table class="cat-table" cellpadding="0" cellspacing="0">
                                <tr>
                                    <th class="cat-th">{{ mb_strtoupper($categoria) }} <span
                                            style="font-weight: normal; color: #475569;">({{ $aprobados }}/{{ $aplicables->count() }} OK)</span></th>
                                    <th class="cat-th-ok">OK</th>
                                </tr>
                                @foreach ($items as $resultado)
                                    @php($estado = $resultado->status ?? '')
                                    <tr>
                                        <td class="item-name">{{ $resultado->item->item_name ?? '' }}</td>
                                        <td class="check-cell"><span class="answer-box">{{ $estado === 'APROBADO' ? '✓' : ($estado === 'NO_APROBADO' ? '✕' : '') }}</span></td>
                                    </tr>
                                @endforeach
                            </table>
                        @endforeach
                    </td>
                @endforeach
            </tr>
        </table>

        <!-- 4. OBSERVACIONES -->
        <table class="obs-table" cellpadding="0" cellspacing="0">
            <tr>
                <th class="obs-th">4. Observaciones y Hallazgos Registrados (Describir cualquier condición anormal o
                    compromiso de mantenimiento)</th>
            </tr>
            <tr>
                <td class="obs-content">
                    {{ $record->notes ?? '' }}
                </td>
            </tr>
        </table>

        <!-- 5. FIRMAS DE RESPONSABILIDAD PESV -->
        <table class="signatures-table" cellpadding="0" cellspacing="0">
            <tr>
                <!-- Conductor -->
                <td class="sig-cell">
                    <div class="sig-space"></div>
                    <div class="sig-line">
                        <div class="sig-name">{{ $conductor->full_name ?? '' }}</div>
                        <div class="sig-role">Firma del Conductor Titular · Documento {{ $conductor->document_number ?? '' }}</div>
                    </div>
                </td>

                <!-- Inspector / Coordinador PESV -->
                <td class="sig-cell">
                    <div class="sig-space">
                        @if (!empty($data['firma'] ?? null))
                            <img src="data:image/png;base64,{{ $data['firma'] }}" alt="Firma del inspector" style="max-height: 26px;">
                        @endif
                    </div>
                    <div class="sig-line">
                        <div class="sig-name">{{ $record->inspector_name ?? '' }}</div>
                        <div class="sig-role">Firma del Inspector</div>
                    </div>
                </td>

                <!-- Coordinador HSEQ / PESV -->
                <td class="sig-cell">
                    <div class="sig-space">
                        @if (!empty($data['firma_coordinador'] ?? null))
                            <img src="data:image/png;base64,{{ $data['firma_coordinador'] }}" alt="Firma del coordinador" style="max-height: 26px;">
                        @endif
                    </div>
                    <div class="sig-line">
                        <div class="sig-name">&nbsp;</div>
                        <div class="sig-role">Coordinador HSEQ y PESV · Revisado y Aprobado</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- 6. PIE DE PÁGINA -->
        <div class="footer-bar">
            <table cellpadding="0" cellspacing="0" style="width: 100%;">
                <tr>
                    <td style="width: 72%; text-align: left;">
                        {{ $nombreEmpresa }} · NIT {{ $nitEmpresa }} · Registro Diario de Control Preoperacional Flota
                    </td>
                    <td style="width: 28%; text-align: right; font-family: 'Courier', monospace;">
                        SGFT04 · Versión 01 · Pág. 1 de 1
                    </td>
                </tr>
            </table>
        </div>
    </div>
@endsection
