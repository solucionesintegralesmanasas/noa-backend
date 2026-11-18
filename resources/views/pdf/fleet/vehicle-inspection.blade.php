@php
    $orientation = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
    $title = 'Inspección Preoperacional Diaria - Formato SGFT04 DomPDF';
    $empresa = $record->company ?? ($record->vehicle->company ?? null);
    $vehiculo = $record->vehicle ?? null;
    $conductor = $record->driver ?? null;
    $licencia = ($conductor && method_exists($conductor, 'licenciaActual')) ? $conductor->licenciaActual() : null;
    $fechaInspeccion = ! empty($record->inspection_date)
        ? \Carbon\Carbon::parse($record->inspection_date)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
        : '';
    $kilometraje = isset($record->mileage) ? number_format((int) $record->mileage, 0, ',', '.') . ' km' : '';
    $nombreEmpresa = $empresa->business_name ?? '';
    $nitEmpresa = $empresa->document_number ?? '';
    $logoDataUrl = (empty($letterhead ?? null) && empty($ocultar_marca ?? false))
        ? (! empty($data['logo'] ?? null) ? 'data:image/png;base64,' . $data['logo'] : ($logo_data_url ?? null))
        : null;
    $empresaSiglas = $empresa_siglas ?? \App\Services\Pdf\PdfService::siglasEmpresa((string) $nombreEmpresa);
    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null, $orientation);
    $sinMarcaVisual = ! empty($letterhead ?? null) || ! empty($ocultar_marca ?? false);
    $marcaLinea = collect([$vehiculo->brand->description ?? null, $vehiculo->line ?? null])->filter()->implode(' · ');
    if (! empty($vehiculo->model)) {
        $marcaLinea = trim($marcaLinea . ' (' . $vehiculo->model . ')');
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
        ->map(fn ($r) => 'Dañ.: ' . ($r->item->item_name ?? '') . ' — ' . $r->observations)
        ->values();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $title }}</title>
    @include('pdf.standard.styles', ['orientation' => $orientation, 'pdfMargen' => 'denso'])
    <style>
        @if ($esHojaMembretadaCompleta)
        @page {
            size: letter {{ $orientation }};
            margin: 33.5mm 10mm 30mm 10mm;
        }

        body {
            background-color: transparent;
        }

        .cat-table td {
            padding: 1px 4px;
            font-size: 6.4pt;
            line-height: 1.08;
        }

        .details-subtable td {
            padding: 1.8px 5px;
        }
        @endif

        #letterhead-page-bg {
            position: fixed;
            top: -33.5mm;
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

        #letterhead-banner {
            text-align: center;
            margin-bottom: 5px;
        }

        #letterhead-banner img {
            max-width: 100%;
            max-height: 2.2cm;
        }

        .header-table {
            margin-bottom: 4px;
        }

        .section-banner {
            margin-top: 4px;
        }

        .section-banner td {
            padding: 2.5px 6px;
            font-size: 7pt;
        }

        .info-grid-table {
            width: 100%;
            border: 1px solid #1f2937;
            border-top: none;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .info-cell-left {
            width: 50%;
            vertical-align: top;
            border-right: 1px solid #1f2937;
            padding: 0;
        }

        .info-cell-right {
            width: 50%;
            vertical-align: top;
            padding: 4px 6px;
            background-color: var(--pdf-tenue);
        }

        .details-subtable {
            width: 100%;
            border-collapse: collapse;
        }

        .details-subtable td {
            padding: 2.1px 5.5px;
            font-size: 7pt;
            line-height: 1.2;
            border-bottom: 0.7px solid #9ca3af;
        }

        .details-subtable tr:last-child td {
            border-bottom: none;
        }

        .field-lbl {
            width: 37%;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            font-size: 6.4pt;
            background-color: var(--pdf-tenue);
            border-right: 0.7px solid #9ca3af;
        }

        .field-val {
            font-weight: 500;
            color: #000000;
        }

        .placa-txt {
            font-weight: bold;
            font-size: 8.2pt;
            color: #000000;
        }

        .interno-txt {
            font-weight: bold;
            font-size: 7.2pt;
            color: #111827;
        }

        .mono-cell {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
        }

        .hint-text {
            font-size: 6.4pt;
            color: #262626;
            margin-bottom: 3px;
            font-style: italic;
        }

        .photo-damage-box {
            border: 1px dashed #9ca3af;
            background-color: #ffffff;
            height: 66px;
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

        .damage-lines-table {
            width: 100%;
            margin-top: 3px;
            border-collapse: collapse;
        }

        .damage-lines-table td {
            border-bottom: 1px dotted #9ca3af;
            height: 11px;
            font-size: 6.6pt;
            color: #111827;
        }

        /* Checklist compacto en 3 columnas */
        .checklist-outer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
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
            border: 1px solid #1f2937;
            border-collapse: collapse;
            margin-bottom: 3.5px;
        }

        .cat-th {
            background-color: var(--pdf-encabezado);
            color: var(--pdf-banda-texto);
            font-size: 6.4pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2px 4px;
            border: 0.7px solid #9ca3af;
            border-bottom: 1px solid #1f2937;
            text-align: left;
            letter-spacing: 0.15px;
        }

        .cat-th-ok {
            width: 15%;
            text-align: center;
            background-color: var(--pdf-encabezado);
            color: var(--pdf-banda-texto);
            border: 0.7px solid #9ca3af;
            border-bottom: 1px solid #1f2937;
            font-size: 6.4pt;
            font-weight: bold;
        }

        .cat-table td {
            border: 0.6px solid #9ca3af;
            padding: 1.4px 4px;
            font-size: 6.7pt;
            line-height: 1.12;
        }

        .item-name {
            color: #111827;
        }

        .check-cell {
            text-align: center;
            width: 15%;
            padding: 1px !important;
        }

        .answer-box {
            width: 10.5px;
            height: 10.5px;
            border: 0.9px solid #1f2937;
            display: inline-block;
            text-align: center;
            line-height: 10px;
            font-size: 7.2pt;
            font-weight: bold;
            font-family: 'DejaVu Sans', sans-serif;
            color: #166534;
            background-color: #ffffff;
        }

        .obs-table {
            margin-top: 2px;
            margin-bottom: 4px;
        }

        .obs-body {
            min-height: 24px;
        }

        .firmas-table {
            margin-bottom: 0;
            page-break-inside: avoid;
        }

        .firmas-table .sig-space {
            height: 24px;
            padding-top: 0;
        }

        .uppercase {
            text-transform: uppercase;
        }
    </style>
</head>
<body class="{{ ! empty($letterhead ?? null) ? 'with-letterhead' : '' }}">

    @if (! empty($letterhead ?? null) && $esHojaMembretadaCompleta)
        <div id="letterhead-page-bg">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @elseif (! empty($letterhead ?? null))
        <div id="letterhead-banner">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @endif

    @include('pdf.standard.header', [
        'logo_data_url' => $logoDataUrl,
        'ocultar_celda_logo' => $sinMarcaVisual,
        'empresa_siglas' => $empresaSiglas,
        'empresa_transportadora' => $nombreEmpresa,
        'empresa_detalle' => $nitEmpresa !== '' ? 'NIT ' . $nitEmpresa : null,
        'titulo_documento' => 'INSPECCIÓN PREOPERACIONAL DIARIA DE VEHÍCULOS',
        'subtitulo_documento' => 'PLAN ESTRATÉGICO DE SEGURIDAD VIAL (PESV) · CONTROL Y MANTENIMIENTO',
        'meta_sgc' => [
            'CÓDIGO' => 'SGFT04',
            'VERSIÓN' => '01',
            'VIGENCIA' => '16/10/2026',
            'PÁGINA' => '1 de 1',
        ],
    ])

    {{-- 1 y 2. DATOS DEL VEHÍCULO / CONDUCTOR Y REGISTRO FOTOGRÁFICO --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 50%;">1. Datos del Vehículo y Conductor</td>
            <td style="width: 50%; border-left: 1px solid #1f2937;">2. Registro de Inspección Física del Automotor</td>
        </tr>
    </table>
    <table class="info-grid-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-cell-left">
                <table class="details-subtable" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="field-lbl">Fecha Inspección:</td>
                        <td class="field-val">{{ $fechaInspeccion }}</td>
                    </tr>
                    <tr>
                        <td class="field-lbl">Placa / Interno:</td>
                        <td class="field-val">
                            <strong class="placa-txt">{{ $vehiculo->vehicle_license_plate ?? '' }}</strong>
                            @if (! empty($vehiculo->internal_number))
                                <span style="color: #4b5563;"> · </span>
                                <strong class="interno-txt">INT-{{ $vehiculo->internal_number }}</strong>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="field-lbl">Marca / Línea:</td>
                        <td class="field-val uppercase">{{ $marcaLinea }}</td>
                    </tr>
                    <tr>
                        <td class="field-lbl">Kilometraje:</td>
                        <td class="field-val mono-cell">{{ $kilometraje }}</td>
                    </tr>
                    <tr>
                        <td class="field-lbl">Conductor Titular:</td>
                        <td class="field-val uppercase">{{ $conductor->full_name ?? '' }}<br><span style="font-size: 6.3pt; color: #262626; font-weight: 400;">Documento {{ $conductor->document_number ?? '' }}</span></td>
                    </tr>
                    <tr>
                        <td class="field-lbl">N° Licencia / Cat.:</td>
                        <td class="field-val"><span class="mono-cell">{{ $licencia->number ?? '' }}</span> &nbsp; <strong>Cat. {{ $licencia->category ?? '' }}</strong></td>
                    </tr>
                    <tr>
                        <td class="field-lbl">Inspector Resp.:</td>
                        <td class="field-val uppercase">{{ $record->inspector_name ?? '' }}</td>
                    </tr>
                </table>
            </td>
            <td class="info-cell-right">
                <div class="hint-text">Encierre cualquier daño observado en un círculo o detalle visualmente el estado exterior:</div>
                <div class="photo-damage-box">
                    <div class="photo-inner">
                        <div style="font-size: 6.6pt; font-weight: bold; color: #111827; text-transform: uppercase;">
                            Espacio para Fotografía del Vehículo / Diagrama
                        </div>
                        <div style="font-size: 5.8pt; color: #262626;">Fotografía digital o diligenciamiento de daños</div>
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

    {{-- 3. REVISIÓN DE PUNTOS CLAVE --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
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
                        @endphp
                        <table class="cat-table" cellpadding="0" cellspacing="0">
                            <tr>
                                <th class="cat-th">{{ mb_strtoupper($categoria) }} <span style="font-weight: normal; color: #262626;">({{ $aprobados }}/{{ $aplicables->count() }} OK)</span></th>
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

    {{-- 4. OBSERVACIONES --}}
    <table class="obs-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="obs-head">4. Observaciones y Hallazgos Registrados (Describir cualquier condición anormal o compromiso de mantenimiento)</td>
        </tr>
        <tr>
            <td class="obs-body">{{ $record->notes ?? ($record->observations ?? '') }}&nbsp;</td>
        </tr>
    </table>

    {{-- 5. FIRMAS DE RESPONSABILIDAD PESV --}}
    <table class="firmas-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 33.333%;">
                <div class="sig-space"></div>
                <div class="sig-line">
                    <div class="sig-name">{{ $conductor->full_name ?? '' }}</div>
                    <div class="sig-role">Firma del Conductor Titular · Documento {{ $conductor->document_number ?? '' }}</div>
                </div>
            </td>
            <td style="width: 33.333%;">
                <div class="sig-space">
                    @if (! empty($data['firma'] ?? null))
                        <img src="data:image/png;base64,{{ $data['firma'] }}" alt="Firma del inspector" style="max-height: 22px;">
                    @endif
                </div>
                <div class="sig-line">
                    <div class="sig-name">{{ $record->inspector_name ?? '' }}</div>
                    <div class="sig-role">Firma del Inspector</div>
                </div>
            </td>
            <td style="width: 33.333%;">
                <div class="sig-space">
                    @if (! empty($data['firma_coordinador'] ?? null))
                        <img src="data:image/png;base64,{{ $data['firma_coordinador'] }}" alt="Firma del coordinador" style="max-height: 22px;">
                    @endif
                </div>
                <div class="sig-line">
                    <div class="sig-name">&nbsp;</div>
                    <div class="sig-role">Coordinador HSEQ y PESV · Revisado y Aprobado</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
