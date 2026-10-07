@extends('pdf.layouts.base')

{{-- Estilos propios del formato SGFT04. Van en crudo porque la base los inyecta dentro de su <style>. --}}
@push('styles')
    /* Formato ajustado a una sola hoja: márgenes y bloques compactos. */
    body {
        margin-top: 0.6cm;
        margin-bottom: 1cm;
        margin-left: 1cm;
        margin-right: 1cm;
        font-size: 10px;
    }

    table {
        table-layout: auto;
        margin-bottom: 3px;
    }

    th,
    td {
        border: 1px solid var(--pdf-borde);
        padding: 1px 2px;
    }

    th {
        background-color: var(--pdf-encabezado);
        text-align: center;
        font-weight: bold;
        color: var(--pdf-banda-texto);
    }

    /* La base fija .header-table td en 33.33%: se revierte a auto para que
       manden los anchos del SGFT04 (15/12/53/10/10) y quede parejo. */
    .header-table th,
    .header-table td {
        width: auto;
        padding: 2px;
        font-size: 10px;
    }

    /* Marca de agua: se usa la de la base (centrada, 70%, fija por página). */

    .section-title {
        background-color: var(--pdf-banda);
        color: var(--pdf-banda-texto);
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
        padding: 2px 4px;
        margin-bottom: 0;
        border: 1px solid var(--pdf-banda-texto);
    }

    /* Casilla de respuesta: X para lo respondido, vacía para lo no contestado. */
    .answer-box {
        width: 11px;
        height: 11px;
        border: 1px solid #333;
        display: inline-block;
        margin: 0 auto;
        text-align: center;
        font-size: 9px;
        font-weight: bold;
        line-height: 11px;
    }

    /* Categoría con fallas: encabezado en rojo oscuro sobre rosa claro. */
    .cat-fail th {
        background-color: var(--pdf-alerta);
        color: var(--pdf-alerta-texto);
    }

    tr.row-fail td {
        background-color: var(--pdf-alerta-fila);
    }

    tr.row-fail td.item-name {
        font-weight: bold;
    }

    /* Espacio para fotografía del daño: marco diligenciable a mano o con foto digital. */
    .photo-frame {
        border: 1px dashed #888;
        border-radius: 4px;
        background-color: var(--pdf-diligenciable);
        height: 54px;
        text-align: center;
        vertical-align: middle;
        color: #999;
        font-size: 10px;
        padding: 4px;
    }

    .photo-frame img {
        max-width: 100%;
        max-height: 46px;
    }

    .damage-line {
        border-bottom: 1px dotted #888;
        height: 11px;
    }

    .info-table td {
        border: none;
        padding: 1px 2px;
    }

    .info-table b {
        color: var(--pdf-banda-texto);
    }

    .hint {
        font-size: 9px;
        color: #555;
        margin-bottom: 2px;
    }
@endpush

{{-- Sin sección 'watermarks' propia: se usa la default de la base
     (pdf.layouts.partials.watermarks), que ya prioriza logo_fondo,
     suprime con membrete/modo limpio y nunca deja un img roto. --}}

{{-- Encabezado propio SGFT04: reemplaza el de 3 logos de la base. --}}
@section('header')
    <table class="header-table" style="margin-bottom: 2px;">
        <tr>
            <td rowspan="2" width="15%" class="text-center" style="vertical-align: middle; padding: 1px;">
                @if (!empty($data['logo']))
                    <img src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo"
                        style="max-height: 25px; max-width: 100px;">
                @else
                    <span style="color: #999; font-size: 10px;">SIN LOGO</span>
                @endif
            </td>
            <td width="53%" colspan="2" class="text-center"
                style="font-size: 10px; font-weight: bold; padding: 1px;">PRESTACIÓN
                DE LOS SERVICIOS GENERALES</td>
            <th width="1%"
                style="background-color: var(--pdf-banda); color: var(--pdf-banda-texto); padding: 1px 4px; font-size: 10px; white-space: nowrap;">
                CÓDIGO
            </th>
            <td width="1%" class="text-center"
                style="font-size: 10px; font-weight: bold; padding: 1px 4px; white-space: nowrap;">SGFT04</td>
        </tr>
        <tr>
            <td colspan="2" class="text-center" style="font-size: 10px; font-weight: bold; padding: 1px;">INSPECCIÓN
                PREOPERACIONAL
                DIARIA DE VEHÍCULOS</td>
            <th width="1%"
                style="background-color: var(--pdf-banda); color: var(--pdf-banda-texto); padding: 1px 4px; font-size: 10px; white-space: nowrap;">
                VERSIÓN</th>
            <td width="1%" class="text-center" style="font-size: 10px; padding: 1px 4px; white-space: nowrap;">1</td>
        </tr>
    </table>
@endsection

@section('content')
    <table>
        <tr>
            <th width="38%" class="text-center section-title">DATOS DEL VEHÍCULO Y CONDUCTOR</th>
            <th width="62%" class="text-center section-title">FOTO DEL VEHÍCULO INSPECCIONADO</th>
        </tr>
        <tr>
            <td style="padding: 0; vertical-align: top; border-right: 1px solid #555;">
                <table class="info-table" style="width:100%; margin:0; font-size: 9px;">
                    <tr>
                        <td style="width:40%;"><b>FECHA:</b></td>
                        <td>{{ \Carbon\Carbon::parse($record->inspection_date)->translatedFormat('d \d\e F \d\e Y') }}
                        </td>
                    </tr>
                    <tr>
                        <td><b>VEHÍCULO:</b></td>
                        <td>{{ $record->vehicle->vehicle_license_plate ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><b>MODELO:</b></td>
                        <td>{{ $record->vehicle->model ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><b>MARCA:</b></td>
                        <td>{{ $record->vehicle->brand->description ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><b>KILOMETRAJE:</b></td>
                        <td>{{ number_format($record->mileage ?? 0, 0, ',', '.') }} km</td>
                    </tr>
                    <tr>
                        <td><b>CONDUCTOR:</b></td>
                        <td>{{ trim(($record->driver->first_name ?? '') . ' ' . ($record->driver->last_name ?? '')) }}
                            @if (!empty($record->driver->document_number))
                                - CC: {{ $record->driver->document_number }}
                            @endif
                        </td>
                    </tr>
                    @php
                        $license = $record->driver ? $record->driver->licenciaActual() : null;
                    @endphp
                    <tr>
                        <td><b>N° LICENCIA:</b></td>
                        <td>{{ $license->number ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><b>CATEGORÍA:</b></td>
                        <td>{{ $license->category ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><b>INSPECTOR:</b></td>
                        <td>{{ $record->inspector_name }}</td>
                    </tr>
                </table>
            </td>
            <td style="vertical-align: top; padding: 2px;">
                <div class="hint">Encierre cualquier daño observado en un círculo y descríbalo brevemente</div>
                <table style="width:100%; margin:0;">
                    <tr>
                        <td class="photo-frame">
                            @if (!empty($data['foto_dano'] ?? null))
                                <img src="{{ $data['foto_dano'] }}" alt="Fotografía del vehículo">
                            @else
                                ESPACIO PARA FOTOGRAFÍA DEL VEHÍCULO<br>
                                <span style="font-size: 9px;">Pegue aquí la foto del vehículo inspeccionado</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 4px 0 0;">
                            <div class="damage-line">&nbsp;</div>
                        </td>
                    </tr>
                    @for ($i = 0; $i < 2; $i++)
                        <tr>
                            <td style="border: none; padding: 0;">
                                <div class="damage-line">&nbsp;</div>
                            </td>
                        </tr>
                    @endfor
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title" style="text-align: center; margin-bottom: 2px;">REVISIÓN DE PUNTOS CLAVE</div>
    <table style="border:none; width:100%; margin-bottom: 2px;">
        <tr>
            @php
                $anchoColumna = (int) floor(100 / max(count($chunks), 1));
            @endphp
            @foreach ($chunks as $chunkCats)
                <td style="vertical-align: top; border:none; padding: 0 4px; width: {{ $anchoColumna }}%;">
                    @foreach ($chunkCats as $cat)
                        @php
                            $items = collect($groupedResults[$cat] ?? []);
                            $okCat = $items->filter(fn($r) => $r->status === 'APROBADO' || !empty($r->is_selected))->count();
                            $failCat = $items->where('status', 'NO_APROBADO')->count();
                        @endphp
                        <table style="width:100%; border: 1px solid #555; margin-bottom: 2px;"
                            @class(['cat-fail' => $failCat > 0])>
                            <tr>
                                <th width="85%"
                                    style="font-size: 9px; padding: 2px; background-color: var(--pdf-encabezado); text-align: left;">
                                    {{ strtoupper($cat) }}
                                    <span style="font-weight: normal;">({{ $okCat }}/{{ $items->count() }} OK)</span>
                                    @if ($failCat > 0)
                                        <span style="font-weight: bold;">· REQUIERE ATENCIÓN</span>
                                    @endif
                                </th>
                                <th width="15%" title="Estado"
                                    style="font-size: 9px; padding: 2px; background-color: var(--pdf-encabezado); text-align: center;">
                                    OK</th>
                            </tr>
                            @foreach ($items as $res)
                                <tr @class(['row-fail' => $res->status === 'NO_APROBADO'])>
                                    <td class="item-name" style="font-size:8.5px; line-height:1.15; padding: 1px 2px;">
                                        {{ $res->item->item_name ?? '' }}</td>
                                    <td class="text-center" style="padding: 1px;">
                                        <span
                                            class="answer-box">{{ $res->status === 'APROBADO' || !empty($res->is_selected) ? 'X' : '&nbsp;' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @endforeach
                </td>
            @endforeach
        </tr>
    </table>

    <table style="margin-top: 8px;">
        <tr>
            <th class="text-left section-title" style="padding: 4px 6px;">OBSERVACIONES (Describir cualquier condición
                anormal observada con la fecha)</th>
        </tr>
        <tr>
            <td
                style="height: 34px; border-bottom: 1px solid #999; border-top: none; border-left: 1px solid #555; border-right: 1px solid #555; font-size: 11px; padding: 4px 6px; vertical-align: top; line-height: 1.4;">
                {{ $record->notes }}</td>
        </tr>
    </table>

    {{-- Firmas a la misma altura: fila de imágenes con alto fijo y fila de renglones separada. --}}
    <table style="width: 100%; margin-top: 4px; border: none;">
        <tr>
            <td style="width: 50%; text-align: center; border: none; vertical-align: bottom; height: 32px;">
                @if (!empty($data['firma']))
                    <img src="data:image/png;base64,{{ $data['firma'] }}" alt="Firma Inspector"
                        style="max-height: 30px;">
                @endif
            </td>
            <td style="width: 50%; text-align: center; border: none; vertical-align: bottom; height: 32px;">
                @if (!empty($data['firma_coordinador']))
                    <img src="data:image/png;base64,{{ $data['firma_coordinador'] }}" alt="Firma Coordinador"
                        style="max-height: 30px;">
                @endif
            </td>
        </tr>
        <tr>
            <td style="width: 50%; text-align: center; border: none; vertical-align: top;">
                ________________________________________<br>
                <b>Firma del Inspector / Conductor</b><br>
                <span style="color: #555; font-size: 10px;">{{ $record->inspector_name }}</span>
            </td>
            <td style="width: 50%; text-align: center; border: none; vertical-align: top;">
                ________________________________________<br>
                <b>Firma Coordinador HSEQ / Operaciones</b><br>
                <span style="color: #555; font-size: 10px;">Revisado y Aprobado</span>
            </td>
        </tr>
    </table>
@endsection
