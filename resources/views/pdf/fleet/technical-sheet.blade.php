@extends('pdf.layouts.base')

{{-- Estilos propios de la ficha técnica. Van en crudo porque la base los inyecta dentro de su <style>. --}}
@push('styles')
    body {
        margin-top: 1cm;
        margin-bottom: 1cm;
        margin-left: 1cm;
        margin-right: 1cm;
        font-size: 10px;
    }

    table {
        table-layout: auto;
    }

    /* La base fija .header-table td en 33.33%: se revierte a auto. */
    .header-table th,
    .header-table td {
        width: auto;
    }

    .main-container {
        width: 100%;
        border: 1.2px solid #000;
        border-radius: 12px;
        overflow: hidden;
        border-collapse: separate;
    }

    td {
        border: 0.5px solid #000;
        padding: 6px 8px;
        vertical-align: middle;
        word-wrap: break-word;
    }

    .label {
        font-weight: bold;
        font-size: 10px;
        text-transform: uppercase;
        background-color: var(--pdf-encabezado);
        color: var(--pdf-banda-texto);
        width: 20%;
    }

    .value {
        font-size: 11px;
    }

    h3 {
        font-size: 14px;
        margin: 5px 0;
        text-transform: uppercase;
        color: var(--pdf-banda-texto);
    }

    .section-header {
        background-color: var(--pdf-banda);
        color: var(--pdf-banda-texto);
        font-weight: bold;
        text-align: center;
        padding: 5px;
        text-transform: uppercase;
        border: 1px solid var(--pdf-banda-texto);
    }

    /* Celdas de código/versión del encabezado: tenues y compactas. */
    .meta-kv td {
        border: none;
        border-bottom: 0.5px solid #000;
        padding: 2px 5px;
        font-size: 8px;
    }

    .meta-kv .k {
        font-weight: bold;
        border-right: 0.5px solid #000;
        background-color: var(--pdf-tenue);
    }

    .badge {
        display: inline-block;
        padding: 2px 5px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 9px;
    }

    .badge-success {
        background-color: #DCFCE7;
        color: #14532D;
    }

    .badge-danger {
        background-color: var(--pdf-alerta);
        color: var(--pdf-alerta-texto);
    }

    .qr-section-container {
        display: table;
        width: 100%;
        border-top: 1.2px solid #000;
    }

    .qr-box {
        display: table-cell;
        width: 20%;
        padding: 10px;
        text-align: center;
        vertical-align: middle;
        border-right: 0.5px solid #000;
    }

    .instruction-box {
        display: table-cell;
        width: 80%;
        padding: 15px;
        vertical-align: middle;
        text-align: left;
        font-size: 10px;
        line-height: 1.4;
    }

    img.qr {
        width: 85px;
        height: 85px;
        display: block;
        margin: 0 auto;
    }

    .footer-legal {
        margin-top: 10px;
        font-size: 9px;
        text-align: center;
        font-style: italic;
        color: #666;
    }
@endpush

{{-- Sin sección 'watermarks' propia: usa la default de la base (logo_fondo,
     suprime con membrete/modo limpio y nunca deja un img roto). --}}

{{-- Encabezado propio JG-VEH-001: logo, título y código/fecha/versión. --}}
@section('header')
    <table class="header-table" style="margin-bottom: 4px;">
        <tr>
            <td rowspan="2" style="width: 25%; text-align: center; vertical-align: middle;">
                @if (isset($data['logo']) && $data['logo'])
                    <img class="logo-header" src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo">
                @endif
            </td>
            <td style="width: 50%; text-align: center; vertical-align: middle;">
                <h3 style="margin: 0;">FICHA TÉCNICA DE VEHÍCULO</h3>
                <div style="font-size: 9px; text-transform: uppercase; color: var(--pdf-banda-texto);">
                    Placa: {{ $vehicle->vehicle_license_plate ?? 'N/A' }}
                </div>
            </td>
            <td style="width: 25%; padding: 0; vertical-align: middle;">
                <table class="meta-kv" style="width: 100%;">
                    <tr>
                        <td class="k">CÓDIGO</td>
                        <td>JG-VEH-001</td>
                    </tr>
                    <tr>
                        <td class="k">FECHA</td>
                        <td>{{ strtoupper(now()->translatedFormat('F Y')) }}</td>
                    </tr>
                    <tr>
                        <td class="k">VERSIÓN</td>
                        <td>1.0</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection

@section('content')
    <div class="main-container">
        <table>
            <tr>
                <td colspan="4" class="section-header">Identificación del Vehículo</td>
            </tr>
            <tr>
                <td class="label">Placa</td>
                <td class="value text-center" style="font-size: 14px; font-weight: bold;">
                    {{ $vehicle->vehicle_license_plate }}</td>
                <td class="label">N° Interno</td>
                <td class="value text-center">{{ $vehicle->internal_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Marca</td>
                <td class="value">{{ $vehicle->brand->description ?? 'N/A' }}</td>
                <td class="label">Línea</td>
                <td class="value">{{ $vehicle->line ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Modelo</td>
                <td class="value">{{ $vehicle->model ?? 'N/A' }}</td>
                <td class="label">Color</td>
                <td class="value">{{ $vehicle->color ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Clase</td>
                <td class="value">{{ $vehicle->vehicle_class->description ?? 'N/A' }}</td>
                <td class="label">Carrocería</td>
                <td class="value">{{ $vehicle->body_type ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Combustible</td>
                <td class="value">{{ $vehicle->fuel_type ?? 'N/A' }}</td>
                <td class="label">Estado</td>
                <td class="value text-center">
                    <span class="badge {{ $vehicle->status ? 'badge-success' : 'badge-danger' }}">
                        {{ $vehicle->status ? 'ACTIVO' : 'INACTIVO' }}
                    </span>
                </td>
            </tr>

            <tr>
                <td colspan="4" class="section-header">Especificaciones Técnicas</td>
            </tr>
            <tr>
                <td class="label">N° Motor</td>
                <td class="value" colspan="3">{{ $vehicle->engine_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">N° Chasis</td>
                <td class="value" colspan="3">{{ $vehicle->chassis_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">VIN / Serial</td>
                <td class="value" colspan="3">{{ $vehicle->vin_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Cilindrada</td>
                <td class="value">{{ $vehicle->engine_displacement ?? 'N/A' }} CC</td>
                <td class="label">N° Ejes</td>
                <td class="value">{{ $vehicle->number_of_axles ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Capacidad Psj</td>
                <td class="value">{{ $vehicle->passenger_capacity ?? 'N/A' }} Total</td>
                <td class="label">Psj Sentados</td>
                <td class="value">{{ $vehicle->seated_passenger_capacity ?? 'N/A' }}</td>
            </tr>

            <tr>
                <td colspan="4" class="section-header">Información del Propietario</td>
            </tr>
            <tr>
                <td class="label">Nombre/Razón Social</td>
                <td class="value" colspan="3">
                    {{ $vehicle->third_party->first_name ?? '' }} {{ $vehicle->third_party->last_name ?? '' }}
                    {{ $vehicle->third_party->trade_name ?? '' }}
                </td>
            </tr>
            <tr>
                <td class="label">Identificación</td>
                <td class="value" colspan="3">
                    {{ $vehicle->third_party->type_of_document->prefix ?? '' }}
                    {{ $vehicle->third_party->document_number ?? 'N/A' }}
                </td>
            </tr>

            <tr>
                <td colspan="4" class="section-header">Documentación y Vencimientos</td>
            </tr>
            <tr class="text-center">
                <td class="label">Tipo de Documento</td>
                <td class="label">N° Referencia</td>
                <td class="label">Vencimiento</td>
                <td class="label">Estado</td>
            </tr>
            @forelse($vehicle->vehicle_documents as $doc)
                <tr>
                    <td>{{ $doc->document_type }}</td>
                    <td class="text-center">{{ $doc->policy_number }}</td>
                    <td class="text-center">
                        {{ \Carbon\Carbon::parse($doc->expiry_date)->format('d/m/Y') }}
                    </td>
                    <td class="text-center">
                        @if (\Carbon\Carbon::parse($doc->expiry_date)->isPast())
                            <span class="badge badge-danger">VENCIDO</span>
                        @else
                            <span class="badge badge-success">VIGENTE</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">Sin documentos registrados</td>
                </tr>
            @endforelse

            <tr>
                <td colspan="4" class="section-header">Tarjetas de Operación</td>
            </tr>
            <tr class="text-center">
                <td class="label">Número</td>
                <td class="label">Empresa Afiliada</td>
                <td class="label">Vencimiento</td>
                <td class="label">Estado</td>
            </tr>
            @forelse($vehicle->operation_cards as $card)
                <tr>
                    <td class="text-center">{{ $card->operating_card_number }}</td>
                    <td>{{ $card->affiliated_company }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($card->expiration_date)->format('d/m/Y') }}</td>
                    <td class="text-center">
                        @if (\Carbon\Carbon::parse($card->expiration_date)->isPast())
                            <span class="badge badge-danger">VENCIDO</span>
                        @else
                            <span class="badge badge-success">VIGENTE</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">Sin tarjetas de operación registradas</td>
                </tr>
            @endforelse
        </table>

        <div class="qr-section-container">
            @include('pdf.layouts.partials.qr-block', [
                'qr' => !empty($data['qrcode'] ?? null) ? 'data:image/svg+xml;base64,'.$data['qrcode'] : null,
                'instruction' => '<strong>VALIDACIÓN ELECTRÓNICA:</strong><br>Este documento cuenta con un código QR para su validación en línea. Para verificar la autenticidad y el estado actual de la documentación de este vehículo, escanee el código con la cámara de su dispositivo móvil. La información mostrada en el sistema de la empresa prevalecerá sobre este documento impreso.',
            ])
        </div>
    </div>

    <div class="footer-legal">
        Este documento es una representación informativa de los datos del vehículo contenidos en el sistema NOA
        Transportes.
    </div>
@endsection