<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Hoja de Vida – Registro de Mantenimiento de Vehículo</title>
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
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5px;
            color: #000;
            background: #fff;
            padding: 8mm 10mm 8mm 10mm;
        }

        /* HEADER */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .header-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            vertical-align: middle;
        }

        .logo-cell {
            width: 90px;
            text-align: center;
        }

        .logo-text {
            font-size: 11px;
            font-weight: bold;
            font-style: italic;
            letter-spacing: 1px;
        }

        .logo-sub {
            font-size: 6.5px;
            text-transform: uppercase;
        }

        .title-cell {
            text-align: center;
        }

        .doc-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .meta-right {
            width: 180px;
            font-size: 7.5px;
        }

        .meta-right table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-right td {
            border: none;
            padding: 1px 3px;
        }

        .meta-right .meta-label {
            font-weight: bold;
            white-space: nowrap;
        }

        /* CONTROLADO */
        .ctrl-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .ctrl-table td {
            border: 1px solid #000;
            padding: 2px 5px;
            font-size: 8px;
            vertical-align: middle;
        }

        .ctrl-label {
            font-weight: bold;
            width: 90px;
        }

        .ctrl-check {
            width: 30px;
            text-align: center;
            font-weight: bold;
        }

        /* SECTION HEADER */
        .section-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .section-header td {
            border: 1px solid #000;
            font-weight: bold;
            font-size: 8.5px;
            text-align: center;
            padding: 3px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* DATA TABLE */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .data-table th {
            border: 1px solid #000;
            padding: 3px 4px;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            background: #fff;
        }

        .data-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 8.5px;
            vertical-align: middle;
            text-align: center;
        }

        .data-table td.left {
            text-align: left;
        }

        /* MANTENIMIENTOS TABLE */
        .mant-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .mant-table th {
            border: 1px solid #000;
            padding: 3px 3px;
            font-size: 7.5px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .mant-table td {
            border: 1px solid #000;
            padding: 3px 3px;
            font-size: 8px;
            text-align: center;
            vertical-align: middle;
            height: 16px;
        }

        .mant-table td.detail {
            text-align: left;
            font-size: 7.5px;
        }

        .col-d {
            width: 5%;
        }

        .col-m {
            width: 5%;
        }

        .col-a {
            width: 8%;
            font-weight: bold;
            font-size: 9px;
        }

        .col-prev {
            width: 9%;
        }

        .col-corr {
            width: 9%;
        }

        .col-det {
            width: 38%;
        }

        .col-centro {
            width: 14%;
        }

        .col-mec {
            width: 12%;
        }

        /* NOTA FINAL */
        .nota {
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            margin-top: 4px;
            line-height: 1.4;
            border: 1px solid #000;
            padding: 5px 8px;
        }
    </style>
</head>

<body>
    <div class="page">

        <!-- HEADER -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if (!empty($company_logo_base64))
                        <img src="data:image/png;base64,{{ $company_logo_base64 }}"
                            style="max-height: 25px; max-width: 80px; display: block; margin: 0 auto 2px auto;"
                            alt="Logo" />
                        <div class="logo-sub" style="font-size: 5.5px;">{{ $company->business_name ?? 'Empresa' }}</div>
                    @else
                        <div class="logo-text">{{ strtolower($company->business_name ?? 'sturivans') }}</div>
                        <div class="logo-sub">Empresa de Transporte Especial</div>
                    @endif
                </td>
                <td class="title-cell">
                    <div class="doc-title">Hoja de Vida – Registro de Mantenimiento de Vehículo</div>
                </td>
                <td class="meta-right">
                    <table>
                        <tr>
                            <td class="meta-label">Código:</td>
                            <td>GL-FO-08</td>
                            <td class="meta-label">Versión:</td>
                            <td>2</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Vigencia:</td>
                            <td>12/02/2018</td>
                            <td class="meta-label">Página:</td>
                            <td>1 de 1</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- CONTROLADO -->
        <table class="ctrl-table">
            <tr>
                <td class="ctrl-label">Controlado:</td>
                <td style="width:20px; text-align:center; font-weight:bold; font-size:9px;">SI</td>
                <td class="ctrl-check">X</td>
                <td style="width:25px; font-size:8px; font-weight:bold;">NO</td>
                <td></td>
            </tr>
        </table>

        <!-- INFORMACIÓN GENERAL -->
        <table class="section-header">
            <tr>
                <td>Información General</td>
            </tr>
        </table>
        <table class="data-table" style="margin-bottom:0;">
            <tr>
                <th style="width:12%;">Placa</th>
                <th style="width:15%;">Marca</th>
                <th style="width:15%;">Línea</th>
                <th style="width:12%;">Modelo</th>
                <th style="width:12%;">Cilindraje</th>
                <th>Color</th>
            </tr>
            <tr>
                <td><strong>{{ $vehicle->vehicle_license_plate ?? 'N/A' }}</strong></td>
                <td>{{ $vehicle->brand->description ?? ($vehicle->brand->name ?? 'N/A') }}</td>
                <td>{{ $vehicle->line ?? 'N/A' }}</td>
                <td>{{ $vehicle->model ?? 'N/A' }}</td>
                <td>{{ $vehicle->engine_displacement ?? 'N/A' }}</td>
                <td>{{ $vehicle->color ?? 'N/A' }}</td>
            </tr>
        </table>
        <table class="data-table" style="margin-bottom:4px;">
            <tr>
                <th style="width:14%;">Clase</th>
                <th style="width:13%;">Tipo</th>
                <th style="width:22%;">Motor</th>
                <th style="width:24%;">Chasis</th>
                <th style="width:18%;">N° Licencia de Tránsito</th>
                <th>N° Móvil</th>
            </tr>
            <tr>
                <td>{{ $vehicle->vehicleClass->description ?? ($vehicle->vehicleClass->name ?? 'N/A') }}</td>
                <td>{{ $vehicle->body_type ?? 'N/A' }}</td>
                <td>{{ $vehicle->engine_number ?? 'N/A' }}</td>
                <td>{{ $vehicle->chassis_number ?? 'N/A' }}</td>
                <td>{{ $vehicle->transit_license_number ?? 'N/A' }}</td>
                <td>{{ $vehicle->internal_number ?? 'N/A' }}</td>
            </tr>
        </table>

        <!-- DIRECCIÓN - TRANSMISIÓN - SUSPENSIÓN -->
        <table class="section-header">
            <tr>
                <td>Dirección – Transmisión – Suspensión</td>
            </tr>
        </table>
        <table class="data-table" style="margin-bottom:0;">
            <tr>
                <th style="width:25%;">Tipo de Dirección</th>
                <th style="width:25%;">Tipo de Transmisión</th>
                <th style="width:25%;">Número de Velocidades</th>
                <th>Tipo de Rodamientos</th>
            </tr>
            <tr>
                <td>{{ $vehicle->steering_type ?? 'N/A' }}</td>
                <td>{{ $vehicle->transmission_type ?? 'N/A' }}</td>
                <td>{{ $vehicle->number_of_speeds ?? 'N/A' }}</td>
                <td>{{ $vehicle->bearing_type ?? 'N/A' }}</td>
            </tr>
        </table>
        <table class="data-table" style="margin-bottom:4px;">
            <tr>
                <th style="width:25%;">Suspensión Trasera</th>
                <th style="width:25%;">Número de Llantas</th>
                <th style="width:25%;">Dimensión de Rines</th>
                <th>Material de los Rines</th>
            </tr>
            <tr>
                <td>{{ $vehicle->rear_suspension ?? 'N/A' }}</td>
                <td>{{ $vehicle->number_of_tires ?? 'N/A' }}</td>
                <td>{{ $vehicle->rim_size ?? 'N/A' }}</td>
                <td>{{ $vehicle->rim_material ?? 'N/A' }}</td>
            </tr>
        </table>

        <!-- FRENOS -->
        <table class="section-header">
            <tr>
                <td>Frenos</td>
            </tr>
        </table>
        <table class="data-table" style="margin-bottom:4px;">
            <tr>
                <th style="width:50%;">Tipo de Frenos Delanteros</th>
                <th>Tipo de Frenos Traseros</th>
            </tr>
            <tr>
                <td>{{ $vehicle->front_brake_type ?? 'N/A' }}</td>
                <td>{{ $vehicle->rear_brake_type ?? 'N/A' }}</td>
            </tr>
        </table>

        <!-- CARROCERÍA -->
        <table class="section-header">
            <tr>
                <td>Carrocería</td>
            </tr>
        </table>
        <table class="data-table" style="margin-bottom:4px;">
            <tr>
                <th style="width:40%;">Número de Serie</th>
                <th style="width:25%;">Número de Ventanas</th>
                <th>Capacidad de Carga y/o Pasajeros</th>
            </tr>
            <tr>
                <td>{{ $vehicle->serial_number ?? 'N/A' }}</td>
                <td>{{ $vehicle->number_of_windows ?? 'N/A' }}</td>
                <td>{{ $vehicle->seated_passenger_capacity ?? 'N/A' }}</td>
            </tr>
        </table>

        <!-- RELACIÓN DE MANTENIMIENTOS -->
        <table class="section-header">
            <tr>
                <td>Relación de Mantenimientos</td>
            </tr>
        </table>
        <table class="mant-table">
            <tr>
                <th colspan="3">Fecha de Mantenimiento</th>
                <th colspan="2">Tipo de Mantenimiento</th>
                <th class="col-det">Detalle de las Actividades Adelantadas<br>(Intervenciones y Reparaciones Realizadas)
                </th>
                <th class="col-centro">Centro Especializado</th>
                <th class="col-mec">Mecánico Responsable</th>
            </tr>
            <tr>
                <th class="col-d">D</th>
                <th class="col-m">M</th>
                <th class="col-a">A</th>
                <th class="col-prev">Preventivo</th>
                <th class="col-corr">Correctivo</th>
                <th class="col-det"></th>
                <th class="col-centro"></th>
                <th class="col-mec"></th>
            </tr>
            @php
                $rowCount = 0;
            @endphp
            @foreach ($maintenances as $mt)
                @php
                    $mDate = null;
                    try {
                        $mDate = \Carbon\Carbon::parse($mt->maintenance_date);
                    } catch (\Exception $e) {
                    }
                    $isPreventive = strtoupper($mt->maintenance_type) === 'PREVENTIVA';
                    $isCorrective = strtoupper($mt->maintenance_type) === 'CORRECTIVA';
                    $rowCount++;
                @endphp
                <tr>
                    <td>{{ $mDate ? $mDate->format('d') : '' }}</td>
                    <td>{{ $mDate ? $mDate->format('m') : '' }}</td>
                    <td class="col-a">{{ $mDate ? $mDate->format('Y') : '' }}</td>
                    <td>{{ $isPreventive ? 'X' : '' }}</td>
                    <td>{{ $isCorrective ? 'X' : '' }}</td>
                    <td class="detail">{{ $mt->service_description }}</td>
                    <td>{{ $mt->workshop_name }}</td>
                    <td style="font-size:7px;">{{ $mt->mechanic_name }}</td>
                </tr>
            @endforeach
            @for ($i = $rowCount; $i < 15; $i++)
                <tr>
                    <td></td>
                    <td></td>
                    <td class="col-a"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </table>

        <!-- NOTA -->
        <div class="nota">
            Deben registrar todos los mantenimientos del vehículo como cambio de llantas, aceites, despinchadas,
            preventivas, etc.<br>
            Si no le realizan nada en el mes, deben registrar: <em>"En el mes de xxxxx no se le realizó nada al
                vehículo"</em>.
        </div>

    </div>
</body>

</html>
