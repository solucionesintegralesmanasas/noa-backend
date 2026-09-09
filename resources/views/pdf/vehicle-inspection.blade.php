<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Inspección Preoperacional</title>
    <style>
        @page {
            size: letter;
            margin: 0cm 0cm;
        }

        /**
        * Define los márgenes reales del contenido de tu PDF
        * Aquí arreglarás los márgenes del encabezado y pie de página
        * De tu imagen de fondo.
        **/
        body {
            margin-top: 1cm;
            margin-bottom: 2.2cm;
            margin-left: 1.2cm;
            margin-right: 1.2cm;
            font-family: Arial, sans-serif;
            font-size: 9px;
            padding: 0;
            color: #000;
            background-color: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        th,
        td {
            border: 1px solid #555;
            padding: 2px 3px;
        }

        th {
            background-color: #f0f0f0;
            text-align: center;
            font-weight: bold;
            color: #222;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .header-table th,
        .header-table td {
            padding: 2px;
            font-size: 10px;
        }

        .check-box {
            width: 7px;
            height: 7px;
            border: 1px solid #444;
            display: inline-block;
            margin: 0 auto;
            border-radius: 1px;
        }

        .check-box.checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        #watermark {
            position: absolute;
            top: 20%;
            left: 25%;
            width: 50%;
            height: auto;
            z-index: -1000;
            opacity: 0.1;
        }

        #watermark img {
            width: 100%;
            height: auto;
        }

        .section-title {
            background-color: #2C3E50;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2px;
            margin-bottom: 0;
            border: 1px solid #2C3E50;
        }

        .info-table td {
            border: none;
            padding: 1px 3px;
        }

        .info-table b {
            color: #2C3E50;
        }
    </style>
</head>

<body>

    @if (!empty($data['logo']))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $data['logo'] }}" alt="Watermark">
        </div>
    @endif

    <table class="header-table" style="margin-bottom: 4px;">
        <tr>
            <td rowspan="2" width="15%" class="text-center" style="vertical-align: middle; padding: 1px;">
                @if (!empty($data['logo']))
                    <img src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo"
                        style="max-height: 25px; max-width: 100px;">
                @else
                    <span style="color: #999; font-size: 9px;">SIN LOGO</span>
                @endif
            </td>
            <th width="12%" style="background-color: #2C3E50; color: white; padding: 1px; font-size: 9px;">PROCESO
            </th>
            <td width="53%" class="text-center" style="font-size: 9px; font-weight: bold; padding: 1px;">PRESTACIÓN
                DE LOS SERVICIOS GENERALES</td>
            <th width="10%" style="background-color: #2C3E50; color: white; padding: 1px; font-size: 9px;">CÓDIGO
            </th>
            <td width="10%" class="text-center" style="font-size: 9px; font-weight: bold; padding: 1px;">SGFT04</td>
        </tr>
        <tr>
            <th style="background-color: #2C3E50; color: white; padding: 1px; font-size: 9px;">FORMATO</th>
            <td class="text-center" style="font-size: 9px; font-weight: bold; padding: 1px;">INSPECCIÓN PREOPERACIONAL
                DIARIA DE VEHÍCULOS</td>
            <th style="background-color: #2C3E50; color: white; padding: 1px; font-size: 9px;">VERSIÓN</th>
            <td class="text-center" style="font-size: 9px; padding: 1px;">1</td>
        </tr>
    </table>

    <table>
        <tr>
            <th width="35%" class="text-center section-title">DATOS DEL VEHÍCULO Y CONDUCTOR</th>
            <th width="65%" class="text-center section-title" colspan="2">DESCRIPCIÓN DE DAÑOS OBSERVADOS</th>
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
                        $license = $record->driver ? $record->driver->driverLicenses->first() : null;
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
            <td width="65%" colspan="2" class="text-center" style="vertical-align: top; padding: 2px;">
                <div style="font-size: 9px; margin-bottom: 2px; color: #555;">Encierre cualquier daño observado en un
                    círculo y describa brevemente</div>
                <!-- Placholder de carros -->
                <div style="margin-top: 4px; display: flex; justify-content: space-around; width: 100%;">
                    <div
                        style="display: inline-block; width: 45%; height: 60px; border: 1px dashed #aaa; line-height: 60px; color: #999; border-radius: 4px; background-color: #fafafa; font-size: 10px;">
                        [ Frente / Trasero ]</div>
                    <div
                        style="display: inline-block; width: 45%; height: 60px; border: 1px dashed #aaa; line-height: 60px; color: #999; border-radius: 4px; background-color: #fafafa; font-size: 10px;">
                        [ Laterales ]</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title" style="text-align: center; margin-bottom: 2px;">REVISIÓN DE PUNTOS CLAVE</div>
    <table style="border:none; width:100%; margin-bottom: 4px;">
        <tr>
            @foreach ($chunks as $chunkCats)
                <td style="vertical-align: top; border:none; padding: 0 4px; width: 50%;">
                    @foreach ($chunkCats as $cat)
                        <table style="width:100%; border: 1px solid #555; margin-bottom: 4px;">
                            <tr>
                                <th width="85%"
                                    style="font-size: 8px; padding: 2px; background-color: #e9ecef; text-align: left;">
                                    {{ strtoupper($cat) }}</th>
                                <th width="15%" title="Estado"
                                    style="font-size: 8px; padding: 2px; background-color: #e9ecef; text-align: center;">
                                    OK</th>
                            </tr>
                            @foreach ($groupedResults[$cat] as $res)
                                <tr>
                                    <td style="font-size:7.5px; line-height:1.1; padding: 2px;">
                                        {{ $res->item->item_name ?? '' }}</td>
                                    <td class="text-center" style="padding: 1px;">
                                        <div
                                            class="check-box {{ $res->status === 'APROBADO' || $res->is_selected ? 'checked' : '' }}">
                                        </div>
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
                style="height: 70px; border-bottom: 1px solid #999; border-top: none; border-left: 1px solid #555; border-right: 1px solid #555; font-size: 10px; padding: 6px 8px; vertical-align: top; line-height: 1.4;">
                {{ $record->notes }}</td>
        </tr>
    </table>

    <table style="width: 100%; margin-top: 15px; border: none;">
        <tr>
            <td style="width: 50%; text-align: center; border: none; vertical-align: bottom;">
                @if (!empty($data['firma']))
                    <img src="data:image/png;base64,{{ $data['firma'] }}" alt="Firma Inspector"
                        style="max-height: 40px;">
                    <br>
                @else
                    <br><br><br>
                @endif
                ________________________________________<br>
                <b>Firma del Inspector / Conductor</b><br>
                <span style="color: #555; font-size: 9px;">{{ $record->inspector_name }}</span>
            </td>
            <td style="width: 50%; text-align: center; border: none; vertical-align: bottom;">
                @if (!empty($data['firma_coordinador']))
                    <img src="data:image/png;base64,{{ $data['firma_coordinador'] }}" alt="Firma Coordinador"
                        style="max-height: 40px;">
                    <br>
                @else
                    <br><br><br>
                @endif
                ________________________________________<br>
                <b>Firma Coordinador HSEQ / Operaciones</b><br>
                <span style="color: #555; font-size: 9px;">Revisado y Aprobado</span>
            </td>
        </tr>
    </table>

</body>

</html>
