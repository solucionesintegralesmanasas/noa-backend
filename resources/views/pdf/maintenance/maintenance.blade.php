@php
    $orientation = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
    $filas = collect($maintenances ?? [])->values();
    $logo_fondo = $company_logo_fondo_base64 ?? ($logo_fondo ?? null);
    $nombreEmpresa = $company->business_name ?? '';
    $nitEmpresa = $company->document_number ?? '';
    $logoDataUrl = $logo_data_url ?? (! empty($company_logo_base64 ?? null)
        ? 'data:image/png;base64,' . $company_logo_base64
        : null);
    $empresaSiglas = $empresa_siglas ?? \App\Services\Pdf\PdfService::siglasEmpresa((string) $nombreEmpresa);
    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
    $sinMarcaVisual = ! empty($letterhead ?? null) || ! empty($ocultar_marca ?? false);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $title ?? 'Hoja de Vida – Mantenimiento' }}</title>
    @include('pdf.standard.styles', ['orientation' => $orientation, 'pdfMargen' => 'denso'])
    <style>
        @if ($esHojaMembretadaCompleta)
        @page {
            size: letter {{ $orientation }};
            margin: 34mm 10mm 32mm 10mm;
        }

        body {
            background-color: transparent;
        }
        @endif

        #letterhead-page-bg {
            position: fixed;
            top: -34mm;
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
        #watermark {
            position: fixed;
            top: 26%;
            left: 15%;
            width: 70%;
            z-index: -1000;
        }

        #watermark img {
            width: 100%;
            height: auto;
            opacity: 0.12;
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
            margin-bottom: 5px;
        }

        .section-banner {
            margin-top: 5px;
        }

        .info-table {
            margin-bottom: 0;
        }

        .info-table td {
            padding: 3px 5px;
            font-size: 7pt;
        }

        .info-lbl {
            width: 14%;
            font-size: 6.3pt;
        }

        .mant-table {
            border-top: none;
            margin-bottom: 5px;
        }

        .mant-table thead th {
            padding: 2.5px 3px;
            font-size: 6.4pt;
        }

        .mant-table tbody td {
            padding: 2.2px 3.5px;
            font-size: 6.8pt;
            text-align: center;
            height: 15px;
        }

        .mant-table tbody td.detalle {
            text-align: left;
        }

        .uppercase {
            text-transform: uppercase;
        }
    </style>
</head>
<body class="{{ ! empty($letterhead ?? null) ? 'with-letterhead' : '' }}">

    @if (empty($ocultar_marca ?? false) && empty($letterhead ?? null) && ! empty($logo_fondo))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $logo_fondo }}" alt="Marca de agua" />
        </div>
    @endif

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
        'logo_data_url' => $sinMarcaVisual ? null : $logoDataUrl,
        'ocultar_celda_logo' => $sinMarcaVisual,
        'empresa_siglas' => $empresaSiglas,
        'empresa_transportadora' => $nombreEmpresa,
        'empresa_detalle' => $nitEmpresa !== '' ? 'NIT ' . $nitEmpresa : null,
        'titulo_documento' => 'HOJA DE VIDA – REGISTRO DE MANTENIMIENTO DE VEHÍCULO',
        'subtitulo_documento' => 'PLAN ESTRATÉGICO DE SEGURIDAD VIAL (PESV) · GESTIÓN Y MANTENIMIENTO DE FLOTA',
        'meta_sgc' => [
            'CÓDIGO' => 'GL-FO-08',
            'VERSIÓN' => '02',
            'VIGENCIA' => '12/02/2018',
        ],
    ])

    {{-- 1. INFORMACIÓN GENERAL DEL VEHÍCULO --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>1. Información General del Vehículo</td></tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl">Placa:</td>
            <td class="info-val" style="width: 19%;"><strong>{{ $vehicle->vehicle_license_plate ?? '' }}</strong></td>
            <td class="info-lbl">Marca:</td>
            <td class="info-val uppercase" style="width: 19%;">{{ $vehicle->brand->description ?? '' }}</td>
            <td class="info-lbl">Línea:</td>
            <td class="info-val uppercase" style="width: 20%;">{{ $vehicle->line ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Modelo:</td>
            <td class="info-val mono">{{ $vehicle->model ?? '' }}</td>
            <td class="info-lbl">Cilindraje:</td>
            <td class="info-val mono">{{ $vehicle->engine_displacement ?? '' }}</td>
            <td class="info-lbl">Color:</td>
            <td class="info-val uppercase">{{ $vehicle->color ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Clase:</td>
            <td class="info-val uppercase">{{ $vehicle->vehicleClass->description ?? '' }}</td>
            <td class="info-lbl">Carrocería:</td>
            <td class="info-val uppercase">{{ $vehicle->body_type ?? '' }}</td>
            <td class="info-lbl">N° Móvil:</td>
            <td class="info-val mono">{{ $vehicle->internal_number ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">N° Motor:</td>
            <td class="info-val mono">{{ $vehicle->engine_number ?? '' }}</td>
            <td class="info-lbl">N° Chasis / VIN:</td>
            <td class="info-val mono">{{ $vehicle->chassis_number ?? '' }}</td>
            <td class="info-lbl">Lic. Tránsito:</td>
            <td class="info-val mono">{{ $vehicle->transit_license_number ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">N° Serie:</td>
            <td class="info-val mono">{{ $vehicle->serial_number ?? '' }}</td>
            <td class="info-lbl">N° Ventanas:</td>
            <td class="info-val txt-center mono">{{ $vehicle->number_of_windows ?? '' }}</td>
            <td class="info-lbl">Capacidad:</td>
            <td class="info-val">{{ $vehicle->seated_passenger_capacity ?? '' }} pasajeros</td>
        </tr>
    </table>

    {{-- 2. SISTEMAS DEL AUTOMOTOR --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>2. Dirección – Transmisión – Suspensión – Frenos – Rodamiento</td></tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl">Dirección:</td>
            <td class="info-val uppercase">{{ $vehicle->steering_type ?? '' }}</td>
            <td class="info-lbl">Transmisión:</td>
            <td class="info-val uppercase">{{ $vehicle->transmission_type ?? '' }}</td>
            <td class="info-lbl">N° Velocidades:</td>
            <td class="info-val txt-center mono">{{ $vehicle->number_of_speeds ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Rodamientos:</td>
            <td class="info-val uppercase">{{ $vehicle->bearing_type ?? '' }}</td>
            <td class="info-lbl">Susp. Trasera:</td>
            <td class="info-val uppercase">{{ $vehicle->rear_suspension ?? '' }}</td>
            <td class="info-lbl">N° Llantas:</td>
            <td class="info-val txt-center mono">{{ $vehicle->number_of_tires ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Dim. Rines:</td>
            <td class="info-val mono">{{ $vehicle->rim_size ?? '' }}</td>
            <td class="info-lbl">Material Rines:</td>
            <td class="info-val uppercase">{{ $vehicle->rim_material ?? '' }}</td>
            <td class="info-lbl">Frenos Del.:</td>
            <td class="info-val uppercase">{{ $vehicle->front_brake_type ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Frenos Tras.:</td>
            <td class="info-val uppercase">{{ $vehicle->rear_brake_type ?? '' }}</td>
            <td class="info-lbl"></td>
            <td class="info-val"></td>
            <td class="info-lbl"></td>
            <td class="info-val"></td>
        </tr>
    </table>

    {{-- 3. RELACIÓN CRONOLÓGICA DE MANTENIMIENTOS --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>3. Relación Cronológica de Mantenimientos e Intervenciones</td></tr>
    </table>
    <table class="main-table mant-table" cellpadding="0" cellspacing="0">
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
                    $fecha = $m && ! empty($m->maintenance_date) ? \Carbon\Carbon::parse($m->maintenance_date) : null;
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

    <div class="legal-box">
        <strong>Instrucciones de diligenciamiento obligatorio (PESV - Resolución 20223040040595 de 2022):</strong>
        Deben registrarse cronológicamente todos los mantenimientos preventivos y correctivos efectuados al automotor
        (cambio de fluidos, filtros, neumáticos, despinchadas, sistema eléctrico, suspensión y frenos). En caso de que el
        vehículo no presente ninguna intervención técnica en el período mensual, se asentará expresamente: <em>"En el mes de
            [mes/año] no se le realizaron intervenciones al automotor"</em>, avalado con la firma del responsable técnico o
        centro especializado.
    </div>

</body>
</html>
