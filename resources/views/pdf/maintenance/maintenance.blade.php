@extends('pdf.layouts.base')

{{-- Estilos propios del formato GL-FO-08. Solo clases nuevas: no se redefinen las reglas de la base (@page, body, td). --}}
@push('styles')
    .ctl-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 6px;
    }

    .ctl-table td {
    border: 0.5px solid #000;
    padding: 3px 5px;
    vertical-align: middle;
    }

    .ctl-title {
    text-align: center;
    font-weight: bold;
    font-size: 9px;
    text-transform: uppercase;
    color: var(--pdf-banda-texto);
    }

    .ctl-sub {
    text-align: center;
    font-size: 7px;
    color: #334155;
    margin-top: 2px;
    }

    .ctl-meta td {
    padding: 1px 4px;
    font-size: 7.5px;
    }

    .ctl-meta .etiqueta {
    font-weight: bold;
    background-color: var(--pdf-tenue);
    text-transform: uppercase;
    }

    .pdf-seccion {
    background-color: var(--pdf-banda);
    color: var(--pdf-banda-texto);
    font-weight: bold;
    font-size: 9px;
    text-transform: uppercase;
    border: 0.5px solid #000;
    padding: 3px 6px;
    margin-top: 6px;
    }

    .pdf-grilla td {
    padding: 2px 4px;
    font-size: 8px;
    }

    .pdf-grilla .etiqueta {
    font-weight: bold;
    font-size: 7px;
    text-transform: uppercase;
    background-color: var(--pdf-encabezado);
    color: var(--pdf-banda-texto);
    width: 20%;
    }

    .mant-table th {
    background-color: var(--pdf-encabezado);
    color: var(--pdf-banda-texto);
    font-size: 7px;
    font-weight: bold;
    text-transform: uppercase;
    text-align: center;
    padding: 2px;
    }

    .mant-table td {
    padding: 1px 3px;
    font-size: 7.5px;
    text-align: center;
    height: 16px;
    }

    .mant-table .detalle {
    text-align: left;
    }

    .pdf-nota {
    background-color: var(--pdf-tenue);
    border: 0.5px solid #000;
    padding: 4px 6px;
    font-size: 7px;
    text-align: justify;
    margin-top: 6px;
    }
@endpush

@php
    $filas = collect($maintenances ?? [])->values();
    $logo_fondo = $company_logo_fondo_base64 ?? null;
    $nombreEmpresa = $company->business_name ?? '';
@endphp

@section('header')
    <table class="ctl-table">
        <tr>
            <td style="width: 22%; text-align: center;">
                @if (empty($letterhead ?? null) && !empty($company_logo_base64 ?? null))
                    <img src="data:image/png;base64,{{ $company_logo_base64 }}" alt="Logo"
                        style="max-width: 100%; max-height: 40px;">
                @endif
            </td>
            <td style="width: 53%;">
                <div class="ctl-title" style="font-size: 10px; color: #000; margin-top: 2px;">Hoja de Vida – Registro de
                    Mantenimiento de Vehículo</div>
                <div class="ctl-sub">Plan Estratégico de Seguridad Vial (PESV) · Gestión y Mantenimiento de Flota</div>
            </td>
            <td style="width: 25%; padding: 0;">
                <table class="ctl-table ctl-meta" style="margin: 0;">
                    <tr>
                        <td class="etiqueta" style="width: 45%;">Código</td>
                        <td style="text-align: center; font-weight: bold;">GL-FO-08</td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Versión</td>
                        <td style="text-align: center; font-weight: bold;">02</td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Vigencia</td>
                        <td style="text-align: center; font-weight: bold;">12/02/2018</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection

@section('content')
    <div class="pdf-seccion">1. Información General del Vehículo</div>
    <table class="ctl-table pdf-grilla" style="margin-bottom: 0;">
        <tr>
            <td class="etiqueta">Placa</td>
            <td><strong>{{ $vehicle->vehicle_license_plate ?? '' }}</strong></td>
            <td class="etiqueta">Marca</td>
            <td>{{ $vehicle->brand->description ?? '' }}</td>
            <td class="etiqueta">Línea</td>
            <td>{{ $vehicle->line ?? '' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Modelo</td>
            <td>{{ $vehicle->model ?? '' }}</td>
            <td class="etiqueta">Cilindraje</td>
            <td>{{ $vehicle->engine_displacement ?? '' }}</td>
            <td class="etiqueta">Color</td>
            <td>{{ $vehicle->color ?? '' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Clase</td>
            <td>{{ $vehicle->vehicleClass->description ?? '' }}</td>
            <td class="etiqueta">Tipo / Carrocería</td>
            <td>{{ $vehicle->body_type ?? '' }}</td>
            <td class="etiqueta">N° Móvil</td>
            <td>{{ $vehicle->internal_number ?? '' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">N° Motor</td>
            <td>{{ $vehicle->engine_number ?? '' }}</td>
            <td class="etiqueta">N° Chasis / VIN</td>
            <td>{{ $vehicle->chassis_number ?? '' }}</td>
            <td class="etiqueta">N° Licencia Tránsito</td>
            <td>{{ $vehicle->transit_license_number ?? '' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">N° Serie</td>
            <td>{{ $vehicle->serial_number ?? '' }}</td>
            <td class="etiqueta">N° Ventanas</td>
            <td>{{ $vehicle->number_of_windows ?? '' }}</td>
            <td class="etiqueta">Capacidad</td>
            <td>{{ $vehicle->seated_passenger_capacity ?? '' }} pasajeros</td>
        </tr>
    </table>

    <div class="pdf-seccion">2. Dirección – Transmisión – Suspensión – Frenos – Rodamiento</div>
    <table class="ctl-table pdf-grilla" style="margin-bottom: 0;">
        <tr>
            <td class="etiqueta">Dirección</td>
            <td>{{ $vehicle->steering_type ?? '' }}</td>
            <td class="etiqueta">Transmisión</td>
            <td>{{ $vehicle->transmission_type ?? '' }}</td>
            <td class="etiqueta">N° Velocidades</td>
            <td>{{ $vehicle->number_of_speeds ?? '' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Rodamientos</td>
            <td>{{ $vehicle->bearing_type ?? '' }}</td>
            <td class="etiqueta">Suspensión trasera</td>
            <td>{{ $vehicle->rear_suspension ?? '' }}</td>
            <td class="etiqueta">N° Llantas</td>
            <td>{{ $vehicle->number_of_tires ?? '' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Dimensión rines</td>
            <td>{{ $vehicle->rim_size ?? '' }}</td>
            <td class="etiqueta">Material rines</td>
            <td>{{ $vehicle->rim_material ?? '' }}</td>
            <td class="etiqueta">Frenos delanteros</td>
            <td>{{ $vehicle->front_brake_type ?? '' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Frenos traseros</td>
            <td>{{ $vehicle->rear_brake_type ?? '' }}</td>
            <td class="etiqueta"></td>
            <td></td>
            <td class="etiqueta"></td>
            <td></td>
        </tr>
    </table>

    <div class="pdf-seccion">3. Relación Cronológica de Mantenimientos e Intervenciones</div>
    <table class="ctl-table mant-table" style="margin-bottom: 0;">
        <thead>
            <tr>
                <th colspan="3" style="width: 12%;">Fecha</th>
                <th colspan="2" style="width: 14%;">Tipo</th>
                <th style="width: 40%;">Detalle de las actividades adelantadas</th>
                <th style="width: 17%;">Taller autorizado</th>
                <th style="width: 17%;">Mecánico responsable</th>
            </tr>
            <tr>
                <th style="width: 4%;">D</th>
                <th style="width: 4%;">M</th>
                <th style="width: 4%;">A</th>
                <th style="width: 7%;">Prev.</th>
                <th style="width: 7%;">Corr.</th>
                <th>Descripción del trabajo realizado</th>
                <th>Taller</th>
                <th>Firma / C.C.</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < 15; $i++)
                @php
                    $m = $filas[$i] ?? null;
                    $fecha = $m && !empty($m->maintenance_date) ? \Carbon\Carbon::parse($m->maintenance_date) : null;
                @endphp
                <tr>
                    <td>{{ $fecha?->format('d') }}</td>
                    <td>{{ $fecha?->format('m') }}</td>
                    <td class="col-a">{{ $fecha?->format('Y') }}</td>
                    <td>{{ $m && $m->maintenance_type === 'PREVENTIVA' ? 'X' : '' }}</td>
                    <td>{{ $m && $m->maintenance_type === 'CORRECTIVA' ? 'X' : '' }}</td>
                    <td class="detalle">{{ $m->service_description ?? '' }}</td>
                    <td>{{ $m->workshop_name ?? '' }}</td>
                    <td>{{ $m->mechanic_name ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="pdf-nota">
        <strong>Instrucciones de diligenciamiento obligatorio (PESV - Resolución 20223040040595 de 2022):</strong>
        Deben registrarse cronológicamente todos los mantenimientos preventivos y correctivos efectuados al automotor
        (cambio de fluidos, filtros, neumáticos, despinchadas, sistema eléctrico, suspensión y frenos). En caso de que el
        vehículo no presente ninguna intervención técnica en el período mensual, se asentará expresamente: <em>"En el mes de
            [mes/año] no se le realizaron intervenciones al automotor"</em>, avalado con la firma del responsable técnico o
        centro especializado.
    </div>
@endsection
