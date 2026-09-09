<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Ficha Técnica - {{ $vehicle->vehicle_license_plate }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: letter;
            margin: 0cm 0cm;
        }

        body {
            margin-top: 1.5cm;
            margin-bottom: 1.0cm;
            margin-left: 1.5cm;
            margin-right: 1.5cm;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #000;
            background: #fff;
        }

        #watermark {
            position: fixed;
            top: 25%;
            left: 15%;
            width: 70%;
            height: auto;
            z-index: -1000;
        }

        #watermark img {
            width: 100%;
            height: auto;
            opacity: 0.15;
        }

        .membrete-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }

        .membrete-table td {
            width: 33.33%;
            vertical-align: middle;
            text-align: center;
            border: none;
            padding: 2px;
        }

        .logo-header {
            max-width: 100%;
            height: auto;
            max-height: 45px;
            display: inline-block;
        }

        .main-container {
            width: 100%;
            border: 1.2px solid #000;
            border-radius: 12px;
            overflow: hidden;
            border-collapse: separate;
            margin-top: 10px;
        }

        .qr-section-container {
            display: table;
            width: 100%;
        }

        .qr-box {
            display: table-cell;
            width: 20%;
            padding: 6px;
            text-align: center;
            vertical-align: middle;
            border-right: 0.5px solid #000;
        }

        .instruction-box {
            display: table-cell;
            width: 80%;
            padding: 8px 12px;
            vertical-align: middle;
            text-align: left;
            font-size: 11px;
            line-height: 1.4;
        }

        img.qr {
            width: 60px;
            height: 60px;
            display: block;
            margin: 0 auto;
        }

        .vertical-legend {
            position: absolute;
            top: 50%;
            right: -180px;
            width: 400px;
            transform: rotate(-90deg);
            font-size: 8px;
            color: #777;
            text-align: center;
        }

        .page {
            width: 100%;
        }

        /* HEADER */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .header-table td {
            border: 0.5px solid #000;
            padding: 3px 5px;
            vertical-align: middle;
        }

        .title-cell {
            text-align: center;
        }

        .title-cell .doc-title {
            font-size: 11px;
            font-weight: bold;
        }

        .title-cell .responsible {
            font-size: 8px;
            margin-top: 2px;
        }

        .meta-cell {
            width: 120px;
            font-size: 8px;
        }

        .meta-cell div {
            margin-bottom: 2px;
        }

        /* SECTION LABEL */
        .section-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .section-header td {
            border: 0.5px solid #000;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
            padding: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #f5f5f5;
        }

        /* DATA TABLE */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .data-table td {
            border: 0.5px solid #000;
            padding: 3px 5px;
            vertical-align: middle;
            font-size: 9px;
        }

        .data-table .label {
            font-weight: bold;
            width: 120px;
            white-space: nowrap;
        }

        .data-table .value {
            width: auto;
        }

        /* POLIZAS / DOCS TABLE */
        .docs-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .docs-table th {
            border: 0.5px solid #000;
            padding: 3px 5px;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            background-color: #f9f9f9;
        }

        .docs-table td {
            border: 0.5px solid #000;
            padding: 3px 5px;
            font-size: 9px;
            text-align: center;
            vertical-align: middle;
        }

        .docs-table .doc-label {
            font-weight: bold;
            text-align: left;
            padding-left: 5px;
        }

        .vigente {
            font-weight: bold;
            color: #155724;
        }

        .vencido {
            font-weight: bold;
            color: #721c24;
        }
    </style>
</head>

<body>
    @if (!empty($data['logo']))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $data['logo'] }}" />
        </div>
    @endif

    <div class="page">

        <div class="vertical-legend">
            Generado por NOA Transportes | Fecha: {{ date('d/m/Y H:i') }} | Página 1 de 1
        </div>

        <!-- MEMBRETE -->
        <table class="header-table">
            <tr>
                <td style="width: 140px; text-align: center; vertical-align: middle; padding: 5px; border-right: none;">
                    @if (!empty($data['logo']))
                        <img class="logo-header" src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo">
                    @endif
                </td>
                <td class="title-cell" style="border-left: none;">
                    <div class="doc-title" style="font-size: 13px;">FICHA TÉCNICA DEL VEHÍCULO</div>
                    <div class="responsible">Responsable: JEFE OPERATIVO</div>
                </td>
            </tr>
        </table>

        <!-- DATOS DEL VEHÍCULO -->
        <table class="section-header">
            <tr>
                <td>Datos del Vehículo</td>
            </tr>
        </table>

        <table class="data-table">
            <tr>
                <td class="label">PLACA</td>
                <td class="value"><strong>{{ $vehicle->vehicle_license_plate ?? '' }}</strong></td>
                <td class="label">NÚMERO INTERNO</td>
                <td class="value">{{ $vehicle->internal_number ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">CLASE</td>
                <td class="value">{{ $vehicle->vehicle_class->description ?? '' }}</td>
                <td class="label">MARCA</td>
                <td class="value">{{ $vehicle->brand->description ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">LÍNEA</td>
                <td class="value">{{ $vehicle->line ?? '' }}</td>
                <td class="label">MODELO</td>
                <td class="value">{{ $vehicle->model ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">CILINDRAJE</td>
                <td class="value">{{ $vehicle->engine_displacement ? $vehicle->engine_displacement . ' cc' : '' }}
                </td>
                <td class="label">No. EJES</td>
                <td class="value">{{ $vehicle->number_of_axles ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">NÚMERO DE MOTOR</td>
                <td class="value">{{ $vehicle->engine_number ?? '' }}</td>
                <td class="label">NÚMERO DE CHASIS</td>
                <td class="value">{{ $vehicle->chassis_number ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">TIPO DE CARROCERÍA</td>
                <td class="value">{{ $vehicle->body_type ?? '' }}</td>
                <td class="label">COMBUSTIBLE</td>
                <td class="value">{{ $vehicle->fuel_type ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">COLOR</td>
                <td class="value">{{ $vehicle->color ?? '' }}</td>
                <td class="label">CAPACIDAD PSJ</td>
                <td class="value">{{ $vehicle->seated_passenger_capacity ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">CAPACIDAD CARGA</td>
                <td class="value">{{ $vehicle->load_capacity ?? '' }}</td>
                <td class="label">PUERTAS</td>
                <td class="value">{{ $vehicle->doors ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">FECHA DE MATRÍCULA</td>
                <td class="value">
                    {{ $vehicle->registration_date ? \Carbon\Carbon::parse($vehicle->registration_date)->format('d/m/Y') : '' }}
                </td>
                <td class="label">ORGANISMO DE TRÁNSITO</td>
                <td class="value">{{ $vehicle->transit_authority ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">No. LICENCIA TRÁNSITO</td>
                <td class="value">{{ $vehicle->transit_license_number ?? '' }}</td>
                <td class="label">VIN</td>
                <td class="value">{{ $vehicle->vin_number ?? '' }}</td>
            </tr>
        </table>

        <!-- DATOS DE LA EMPRESA AFILIADA -->
        <table class="section-header">
            <tr>
                <td>Datos de la Empresa Afiliada</td>
            </tr>
        </table>
        <table class="data-table" style="margin-bottom:4px;">
            <tr>
                <td class="label">VINCULADO A</td>
                <td class="value">{{ $vehicle->company->business_name ?? '' }}</td>
                <td class="label">NIT</td>
                <td class="value">{{ $vehicle->company->document_number ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">SERVICIO</td>
                <td class="value">{{ $vehicle->type_of_service ?? 'PÚBLICO' }}</td>
                <td class="label">TIPO DE AFILIACIÓN</td>
                <td class="value">
                    {{ $vehicle->business_collaboration_agreements && $vehicle->business_collaboration_agreements->isNotEmpty() ? 'CONVENIO' : 'DIRECTA' }}
                </td>
            </tr>
            <tr>
                <td class="label">MODALIDAD</td>
                <td class="value">ESPECIAL</td>
                <td class="label">RADIO DE ACCIÓN</td>
                <td class="value">NACIONAL</td>
            </tr>
        </table>

        <!-- TARJETA DE OPERACIÓN -->
        <table class="section-header">
            <tr>
                <td>Tarjeta de Operación</td>
            </tr>
        </table>
        <table class="docs-table" style="margin-bottom:4px;">
            <tr>
                <th style="text-align:left; padding-left:5px;">Número</th>
                <th>Fecha Inicio Vigencia</th>
                <th>Fec. Vencimiento</th>
                <th>Estado</th>
                <th>Días Vencimiento</th>
            </tr>
            @php
                $to = $vehicle->operation_cards->first();
            @endphp
            @if ($to)
                @php
                    $daysTo = \Carbon\Carbon::parse($to->expiration_date)->diffInDays(now(), false);
                    $daysTo = $daysTo < 0 ? abs($daysTo) : -$daysTo;
                @endphp
                <tr>
                    <td class="doc-label">{{ $to->operating_card_number }}</td>
                    <td>{{ \Carbon\Carbon::parse($to->issue_date)->format('d/m/Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($to->expiration_date)->format('d/m/Y') }}</td>
                    <td class="{{ $daysTo >= 0 ? 'vigente' : 'vencido' }}">{{ $daysTo >= 0 ? 'VIGENTE' : 'VENCIDO' }}
                    </td>
                    <td>{{ $daysTo }}</td>
                </tr>
            @else
                <tr>
                    <td colspan="5">No registra</td>
                </tr>
            @endif
        </table>

        @php
            // Filtrar documentos
            $docs = $vehicle->vehicle_documents;
            $soat = $docs->filter(fn($d) => stripos($d->document_type, 'SOAT') !== false)->first();
            $rtm = $docs
                ->filter(
                    fn($d) => stripos($d->document_type, 'RTM') !== false ||
                        stripos($d->document_type, 'REVISION') !== false,
                )
                ->first();
            $rcc = $docs
                ->filter(
                    fn($d) => stripos($d->document_type, 'RCC') !== false ||
                        stripos($d->document_type, 'CONTRACTUAL') !== false,
                )
                ->first();
            $rce = $docs
                ->filter(
                    fn($d) => stripos($d->document_type, 'RCE') !== false ||
                        stripos($d->document_type, 'EXTRACONTRACTUAL') !== false,
                )
                ->first();
            $todoRiesgo = $docs->filter(fn($d) => stripos($d->document_type, 'TODO RIESGO') !== false)->first();
        @endphp

        <!-- SOAT -->
        <table class="section-header">
            <tr>
                <td>Seguro Obligatorio / SOAT</td>
            </tr>
        </table>
        <table class="docs-table" style="margin-bottom:4px;">
            <tr>
                <th style="text-align:left; padding-left:5px;">Número</th>
                <th>Fecha Inicio Vigencia</th>
                <th>Fec. Vencimiento</th>
                <th>Estado</th>
                <th>Días Vencimiento</th>
            </tr>
            @if ($soat)
                @php
                    $daysSoat = \Carbon\Carbon::parse($soat->expiry_date)->diffInDays(now(), false);
                    $daysSoat = $daysSoat < 0 ? abs($daysSoat) : -$daysSoat;
                @endphp
                <tr>
                    <td class="doc-label">{{ $soat->policy_number }}</td>
                    <td>{{ $soat->issue_date ? \Carbon\Carbon::parse($soat->issue_date)->format('d/m/Y') : '' }}</td>
                    <td>{{ \Carbon\Carbon::parse($soat->expiry_date)->format('d/m/Y') }}</td>
                    <td class="{{ $daysSoat >= 0 ? 'vigente' : 'vencido' }}">
                        {{ $daysSoat >= 0 ? 'VIGENTE' : 'VENCIDO' }}</td>
                    <td>{{ $daysSoat }}</td>
                </tr>
            @else
                <tr>
                    <td colspan="5">No registra</td>
                </tr>
            @endif
        </table>

        <!-- REVISIÓN TÉCNICO MECÁNICA -->
        <table class="section-header">
            <tr>
                <td>Revisión Técnico Mecánica y de Emisiones Contaminantes</td>
            </tr>
        </table>
        <table class="docs-table" style="margin-bottom:4px;">
            <tr>
                <th style="text-align:left; padding-left:5px;">Número</th>
                <th>Fecha Inicio Vigencia</th>
                <th>Fec. Vencimiento</th>
                <th>Estado</th>
                <th>Días Vencimiento</th>
            </tr>
            @if ($rtm)
                @php
                    $daysRtm = \Carbon\Carbon::parse($rtm->expiry_date)->diffInDays(now(), false);
                    $daysRtm = $daysRtm < 0 ? abs($daysRtm) : -$daysRtm;
                @endphp
                <tr>
                    <td class="doc-label">{{ $rtm->policy_number }}</td>
                    <td>{{ $rtm->issue_date ? \Carbon\Carbon::parse($rtm->issue_date)->format('d/m/Y') : '' }}</td>
                    <td>{{ \Carbon\Carbon::parse($rtm->expiry_date)->format('d/m/Y') }}</td>
                    <td class="{{ $daysRtm >= 0 ? 'vigente' : 'vencido' }}">
                        {{ $daysRtm >= 0 ? 'VIGENTE' : 'VENCIDO' }}</td>
                    <td>{{ $daysRtm }}</td>
                </tr>
            @else
                <tr>
                    <td colspan="5">No registra</td>
                </tr>
            @endif
        </table>

        <!-- PÓLIZAS RCC Y RCE -->
        <table class="section-header">
            <tr>
                <td>Pólizas RCC y RCE</td>
            </tr>
        </table>
        <table class="docs-table" style="margin-bottom:4px;">
            <tr>
                <th style="width:50px; text-align:left; padding-left:5px;">Tipo</th>
                <th style="text-align:left; padding-left:5px;">Número</th>
                <th>Fecha Inicio Vigencia</th>
                <th>Fec. Vencimiento</th>
                <th>Estado</th>
                <th>Días Vencimiento</th>
            </tr>
            @if ($rcc)
                @php
                    $daysRcc = \Carbon\Carbon::parse($rcc->expiry_date)->diffInDays(now(), false);
                    $daysRcc = $daysRcc < 0 ? abs($daysRcc) : -$daysRcc;
                @endphp
                <tr>
                    <td class="doc-label">RCC</td>
                    <td class="doc-label">{{ $rcc->policy_number }}</td>
                    <td>{{ $rcc->issue_date ? \Carbon\Carbon::parse($rcc->issue_date)->format('d/m/Y') : '' }}</td>
                    <td>{{ \Carbon\Carbon::parse($rcc->expiry_date)->format('d/m/Y') }}</td>
                    <td class="{{ $daysRcc >= 0 ? 'vigente' : 'vencido' }}">
                        {{ $daysRcc >= 0 ? 'VIGENTE' : 'VENCIDO' }}</td>
                    <td>{{ $daysRcc }}</td>
                </tr>
            @endif
            @if ($rce)
                @php
                    $daysRce = \Carbon\Carbon::parse($rce->expiry_date)->diffInDays(now(), false);
                    $daysRce = $daysRce < 0 ? abs($daysRce) : -$daysRce;
                @endphp
                <tr>
                    <td class="doc-label">RCE</td>
                    <td class="doc-label">{{ $rce->policy_number }}</td>
                    <td>{{ $rce->issue_date ? \Carbon\Carbon::parse($rce->issue_date)->format('d/m/Y') : '' }}</td>
                    <td>{{ \Carbon\Carbon::parse($rce->expiry_date)->format('d/m/Y') }}</td>
                    <td class="{{ $daysRce >= 0 ? 'vigente' : 'vencido' }}">
                        {{ $daysRce >= 0 ? 'VIGENTE' : 'VENCIDO' }}</td>
                    <td>{{ $daysRce }}</td>
                </tr>
            @endif
            @if (!$rcc && !$rce)
                <tr>
                    <td colspan="6">No registra</td>
                </tr>
            @endif
        </table>

        @php
            $convenio = $vehicle->business_collaboration_agreements
                ? $vehicle->business_collaboration_agreements->first()
                : null;
        @endphp
        @if ($convenio)
            <!-- INFORMACIÓN DEL CONVENIO -->
            <table class="section-header">
                <tr>
                    <td>Información del Convenio</td>
                </tr>
            </table>
            <table class="docs-table" style="margin-bottom:4px;">
                <tr>
                    <th style="text-align:left; padding-left:5px;">Nro. Convenio</th>
                    <th>Empresa Contratante</th>
                    <th>Fec. Vencimiento</th>
                    <th>Estado</th>
                    <th>Días Vencimiento</th>
                </tr>
                @php
                    $daysConv = \Carbon\Carbon::parse($convenio->expiry_date)->diffInDays(now(), false);
                    $daysConv = $daysConv < 0 ? abs($daysConv) : -$daysConv;
                @endphp
                <tr>
                    <td class="doc-label">{{ $convenio->agreement_internal_id }}</td>
                    <td>{{ $convenio->contracting_entity_name }}</td>
                    <td>{{ $convenio->expiry_date ? \Carbon\Carbon::parse($convenio->expiry_date)->format('d/m/Y') : '' }}
                    </td>
                    <td class="{{ $daysConv >= 0 ? 'vigente' : 'vencido' }}">
                        {{ $daysConv >= 0 ? 'VIGENTE' : 'VENCIDO' }}</td>
                    <td>{{ $daysConv }}</td>
                </tr>
            </table>
        @endif

        <!-- PROPIETARIO -->
        <table class="section-header">
            <tr>
                <td>Datos del Propietario</td>
            </tr>
        </table>
        <table class="data-table" style="margin-bottom:4px;">
            <tr>
                <td class="label">TIPO DE VEHÍCULO</td>
                <td class="value">{{ $vehicle->vehicle_class->description ?? '' }}</td>
                <td class="label"></td>
                <td class="value"></td>
            </tr>
            <tr>
                <td class="label">NOMBRES Y/O RAZÓN SOCIAL</td>
                <td class="value">
                    {{ $vehicle->third_party->first_name ?? '' }} {{ $vehicle->third_party->last_name ?? '' }}
                    {{ $vehicle->third_party->company_name ?? '' }}
                </td>
                <td class="label">IDENTIFICACIÓN</td>
                <td class="value">
                    {{ $vehicle->third_party->type_of_document->prefix ?? '' }}
                    {{ $vehicle->third_party->document_number ?? 'N/A' }}
                </td>
            </tr>
            <tr>
                <td class="label">DIRECCIÓN</td>
                <td class="value">{{ $vehicle->third_party->address ?? 'N/A' }}</td>
                <td class="label">CIUDAD</td>
                <td class="value">{{ $vehicle->third_party->municipality->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">TELÉFONO</td>
                <td class="value">{{ $vehicle->third_party->phone ?? 'N/A' }}</td>
                <td class="label"></td>
                <td class="value"></td>
            </tr>
        </table>

        <div class="main-container">
            <div class="qr-section-container">
                <div class="qr-box">
                    @if (isset($data['qrcode']) && $data['qrcode'])
                        <img class="qr" src="data:image/svg+xml;base64,{{ $data['qrcode'] }}" alt="QR">
                    @endif
                </div>
                <div class="instruction-box">
                    Para verificar este documento, por favor leer el código QR por medio de la cámara de su
                    dispositivo y/o la aplicación correspondiente.
                </div>
            </div>
        </div>

        {{--   <!-- DATOS DEL CONDUCTOR -->
        <table class="section-header">
            <tr>
                <td>Datos del Conductor</td>
            </tr>
        </table>
        <table class="docs-table" style="margin-bottom:4px;">
            <tr>
                <th style="text-align:left; padding-left:5px;">Nombres y Apellidos</th>
                <th>Nro. Licencia</th>
                <th>Fecha Vencimiento</th>
                <th>Categoría</th>
            </tr>
            <tr>
                <td class="doc-label" style="text-align:left;"></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </table>

        <!-- ESTADO -->
        <table class="estado-table">
            <tr>
                <td style="width:100px;" class="label"><strong>ESTADO</strong></td>
                <td style="width:60px;">BUENO &nbsp;{!! $vehicle->is_active ? '<span class="check-mark">X</span>' : '' !!}</td>
                <td style="width:60px;">REGULAR</td>
                <td style="width:60px;">MALO &nbsp;{!! !$vehicle->is_active ? '<span class="check-mark">X</span>' : '' !!}</td>
            </tr>
        </table>

        <!-- OBSERVACIONES -->
        <table class="obs-table">
            <tr>
                <td class="label"><strong>OBSERVACIONES</strong></td>
                <td></td>
            </tr>
        </table> --}}

    </div>
</body>

</html>
