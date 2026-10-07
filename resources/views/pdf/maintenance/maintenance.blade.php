@extends('pdf.layouts.base')

{{-- Estilos propios del formato GL-FO-08. Van en crudo porque la base los inyecta dentro de su <style>. --}}
@push('styles')
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9.5px;
        padding: 8mm 10mm 8mm 10mm;
    }

    table {
        table-layout: auto;
    }

    /* La base fija .header-table td en 33.33%: se revierte a auto para que
       manden .logo-cell (90px) y .meta-right (180px) y quede parejo. */
    .header-table th,
    .header-table td {
        width: auto;
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

    /* Paleta del documento: navy para bandas, slate claro para etiquetas,
       blanco para zonas diligenciables. Alto contraste apto para impresión B/N. */
    .doc-title {
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .doc-sub {
        font-size: 7.5px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #334155;
        margin-top: 1px;
    }

    .meta-right {
        width: 180px;
        font-size: 8px;
        background-color: var(--pdf-tenue);
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

    /* SECTION HEADER: banda del estándar (slate claro) con texto navy. */
    .section-header {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }

    .section-header td {
        border: 1px solid var(--pdf-banda-texto);
        background-color: var(--pdf-banda);
        color: var(--pdf-banda-texto);
        font-weight: bold;
        font-size: 9.5px;
        text-align: center;
        padding: 3px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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
        font-size: 8.5px;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        background-color: var(--pdf-encabezado);
        color: var(--pdf-banda-texto);
    }

    .data-table td {
        border: 1px solid #000;
        padding: 3px 5px;
        font-size: 9px;
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

    /* Encabezado de mantenimientos con el estándar de banda claro. */
    .mant-table th {
        border: 1px solid var(--pdf-banda-texto);
        padding: 3px 3px;
        font-size: 8px;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        vertical-align: middle;
        background-color: var(--pdf-banda);
        color: var(--pdf-banda-texto);
    }

    .mant-table td {
        border: 1px solid #000;
        padding: 3px 3px;
        font-size: 8.5px;
        text-align: center;
        vertical-align: middle;
        height: 16px;
    }

    .mant-table td.detail {
        text-align: left;
        font-size: 8px;
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

    /* NOTA FINAL: recuadro con acento lateral del estándar. */
    .nota {
        font-size: 8.5px;
        text-align: center;
        margin-top: 4px;
        line-height: 1.4;
        border: 1px solid #000;
        border-left: 4px solid var(--pdf-banda);
        background-color: var(--pdf-tenue);
        padding: 5px 8px;
    }
@endpush

{{-- Marca de agua con el logo en alta (logo_fondo): el de 400px se pixela al 70% del ancho.
     Mismo patrón del parcial de la base: se suprime en modo limpio y con membrete. --}}
@section('watermarks')
    @php
        $fondo = $company_logo_fondo_base64 ?? null;
    @endphp
    @if (empty($ocultar_marca ?? false) && empty($letterhead ?? null) && !empty($fondo))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $fondo }}" alt="Watermark">
        </div>
    @endif
    @if (!empty($letterhead ?? null))
        <div id="letterhead-banner">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @endif
@endsection

{{-- Encabezado propio GL-FO-08: reemplaza el de 3 logos de la base. --}}
@section('header')
    <table class="header-table">
        <tr>
            {{-- Con membrete el logo ya va arriba: se omite la celda para no duplicarlo. --}}
            @if (empty($letterhead ?? null))
                <td class="logo-cell">
                    @if (!empty($company_logo_base64))
                        <img src="data:image/png;base64,{{ $company_logo_base64 }}"
                            style="max-height: 25px; max-width: 80px; display: block; margin: 0 auto;"
                            alt="Logo" />
                    @endif
                </td>
            @endif
            <td class="title-cell">
                <div class="doc-title">Hoja de Vida – Registro de Mantenimiento de Vehículo</div>
                <div class="doc-sub">Documento controlado del sistema de gestión</div>
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
@endsection

@section('content')
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
                <td style="font-size:7.5px;">{{ $mt->mechanic_name }}</td>
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
@endsection
