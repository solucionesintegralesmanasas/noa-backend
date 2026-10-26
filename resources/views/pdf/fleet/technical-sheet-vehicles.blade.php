@extends('pdf.layouts.base')

@push('styles')
    <style>
        /* Ajustes específicos para Ficha Técnica Vehicular DomPDF (Hoja Carta Vertical) */
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7.2pt;
            line-height: 1.2;
            color: #0f172a;
            margin-top: 0.5cm !important;
            margin-bottom: 0.5cm !important;
            margin-left: 0.7cm !important;
            margin-right: 0.7cm !important;
            padding: 0 !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0;
            padding: 0;
        }

        td, th {
            vertical-align: middle;
            word-wrap: break-word;
        }

        /* 1. ENCABEZADO INSTITUCIONAL SGC / PESV (JG-VEH-001) */
        .tech-header-table {
            width: 100%;
            border: 1.2px solid #0f172a;
            border-collapse: collapse;
            margin-bottom: 3px;
        }

        .tech-header-table td {
            border: 1px solid #0f172a;
            padding: 2px 4px;
            vertical-align: middle;
        }

        .logo-box {
            width: 22%;
            text-align: center;
            background-color: #ffffff;
            padding: 3px;
        }

        .techsheet-logo {
            max-width: 120px;
            max-height: 42px;
            display: inline-block;
        }

        .logo-fallback-title {
            font-size: 13pt;
            font-weight: 900;
            color: #1e3a8a;
            line-height: 1;
        }

        .logo-fallback-sub {
            font-size: 5.5pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .title-box {
            width: 53%;
            text-align: center;
            padding: 2px 4px;
        }

        .company-header-name {
            font-size: 8pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            line-height: 1.1;
        }

        .doc-header-title {
            font-size: 9pt;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 1px;
            line-height: 1.1;
        }

        .doc-header-subtitle {
            font-size: 6.2pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 1px;
        }

        .meta-box {
            width: 25%;
            padding: 0 !important;
        }

        .meta-subtable {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-subtable td {
            border: none !important;
            border-bottom: 0.8px solid #0f172a !important;
            padding: 1.8px 4px !important;
            font-size: 6.8pt;
        }

        .meta-subtable tr:last-child td {
            border-bottom: none !important;
        }

        .meta-label {
            font-weight: bold;
            color: #334155;
            width: 48%;
            background-color: var(--pdf-tenue);
            border-right: 0.8px solid #0f172a !important;
            text-transform: uppercase;
            font-size: 6.4pt;
        }

        .meta-val {
            font-weight: bold;
            color: #0f172a;
            text-align: center;
            font-family: 'Courier', monospace;
            font-size: 7pt;
        }

        /* 2. BANDAS DE SECCIÓN */
        .section-banner {
            width: 100%;
            border: 1px solid #0f172a;
            background-color: var(--pdf-banda);
            border-left: 4px solid #1e3a8a;
            margin-top: 3px;
            margin-bottom: 0;
        }

        .section-banner td {
            padding: 2px 5px;
            font-size: 7.2pt;
            font-weight: 800;
            color: var(--pdf-banda-texto);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: none;
        }

        /* 3. TABLAS DE DATOS TÉCNICOS */
        .data-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-top: none;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .data-table td {
            border: 0.6px solid #cbd5e1;
            padding: 2.2px 4.5px;
            font-size: 7.1pt;
            line-height: 1.2;
        }

        .lbl {
            background-color: var(--pdf-tenue);
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            font-size: 6.6pt;
            border-right: 0.6px solid #cbd5e1;
        }

        .val {
            font-weight: 500;
            color: #0f172a;
        }

        .mono {
            font-family: 'Courier', monospace;
            font-weight: bold;
        }

        .placa-badge {
            background-color: #fef08a;
            color: #854d0e;
            font-family: 'Courier', monospace;
            font-size: 8.5pt;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 2px;
            border: 1px solid #facc15;
            letter-spacing: 0.6px;
            display: inline-block;
        }

        .badge-active {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: bold;
            padding: 1px 5px;
            font-size: 6.5pt;
            border: 1px solid #86efac;
            text-transform: uppercase;
            border-radius: 2px;
            display: inline-block;
        }

        .badge-inactive {
            background-color: #fee2e2;
            color: #b91c1c;
            font-weight: bold;
            padding: 1px 5px;
            font-size: 6.5pt;
            border: 1px solid #fca5a5;
            text-transform: uppercase;
            border-radius: 2px;
            display: inline-block;
        }

        /* TABLAS GRID DE LISTADOS */
        .grid-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-top: none;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .grid-table th {
            background-color: var(--pdf-encabezado);
            color: #1e293b;
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2px 4px;
            border: 0.6px solid #94a3b8;
            text-align: center;
        }

        .grid-table td {
            border: 0.6px solid #cbd5e1;
            padding: 2px 4px;
            font-size: 6.9pt;
            text-align: center;
        }

        /* SECCIÓN VALIDACIÓN QR Y RESEÑA PESV */
        .qr-footer-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-collapse: collapse;
            margin-top: 3px;
            background-color: #ffffff;
        }

        .qr-footer-table td {
            padding: 4px 6px;
            vertical-align: middle;
        }

        .qr-box-cell {
            width: 16%;
            text-align: center;
            border-right: 0.8px solid #cbd5e1;
            background-color: var(--pdf-tenue);
        }

        .qr-text-cell {
            width: 84%;
            font-size: 6.6pt;
            line-height: 1.3;
            color: #334155;
            text-align: justify;
        }

        .text-left {
            text-align: left !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .uppercase {
            text-transform: uppercase;
        }
    </style>
@endpush

{{-- Encabezado institucional exclusivo de la Ficha Técnica (JG-VEH-001) --}}
@section('header')
    @php
        $companyName = $vehicle->company->business_name ?? 'TRANSPORTES ESPECIALES SIN BARRERAS S.A.S.';
        $companyNit = $vehicle->company->document_number ?? '901.234.567-8';
    @endphp
    <table cellpadding="0" cellspacing="0" class="tech-header-table">
        <tbody>
            <tr>
                <td class="logo-box">
                    @if (!empty($data['logo']))
                        <img src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo" class="techsheet-logo">
                    @else
                        <div class="logo-fallback-title">SB</div>
                        <div class="logo-fallback-sub">Transportes Especiales</div>
                    @endif
                </td>
                <td class="title-box">
                    <div class="company-header-name">{{ $companyName }}</div>
                    <div class="doc-header-title">FICHA TÉCNICA DEL VEHÍCULO</div>
                    <div class="doc-header-subtitle">PLAN ESTRATÉGICO DE SEGURIDAD VIAL (PESV) · HOJA DE VIDA AUTOMOTOR</div>
                </td>
                <td class="meta-box">
                    <table cellpadding="0" cellspacing="0" class="meta-subtable">
                        <tbody>
                            <tr>
                                <td class="meta-label">CÓDIGO</td>
                                <td class="meta-val">JG-VEH-001</td>
                            </tr>
                            <tr>
                                <td class="meta-label">VERSIÓN</td>
                                <td class="meta-val">1.0</td>
                            </tr>
                            <tr>
                                <td class="meta-label">VIGENCIA</td>
                                <td class="meta-val">{{ strtoupper(now()->translatedFormat('M Y')) }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">PÁGINA</td>
                                <td class="meta-val">1 de 1</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
@endsection

@section('content')
    @php
        $isActive = (isset($vehicle->is_active) ? $vehicle->is_active : ($vehicle->status ?? true));
        $brandName = $vehicle->brand->description ?? ($vehicle->brand_name ?? 'N/A');
        $className = $vehicle->vehicle_class->description ?? ($vehicle->vehicle_class_name ?? 'N/A');
        $tp = $vehicle->third_party ?? null;
        $tpName = $tp ? (trim(($tp->first_name ?? '') . ' ' . ($tp->last_name ?? '')) ?: ($tp->company_name ?? 'N/A')) : 'N/A';
        $tpDocType = $tp->type_of_document->prefix ?? 'CC';
        $tpDocNum = $tp->document_number ?? 'N/A';
        $tpAddress = $tp->address ?? 'N/A';
        $tpCity = $tp->municipality->name ?? null;
        $tpPhone = $tp->phone ?? null;
    @endphp

    <!-- 1. IDENTIFICACIÓN Y ESTADO OPERATIVO DEL VEHÍCULO -->
    <table cellpadding="0" cellspacing="0" class="section-banner">
        <tbody>
            <tr>
                <td>1. Identificación y Estado Operativo del Vehículo</td>
            </tr>
        </tbody>
    </table>
    <table cellpadding="0" cellspacing="0" class="data-table">
        <tbody>
            <tr>
                <td class="lbl" style="width: 15%;">Placa:</td>
                <td class="val" style="width: 20%;">
                    <span class="placa-badge">{{ $vehicle->vehicle_license_plate ?? 'N/A' }}</span>
                </td>
                <td class="lbl" style="width: 15%;">N° Interno:</td>
                <td class="val text-center" style="width: 18%;">
                    <strong style="color: #1e3a8a; font-size: 8pt;">{{ $vehicle->internal_number ?? 'N/A' }}</strong>
                </td>
                <td class="lbl" style="width: 14%;">Estado:</td>
                <td class="val text-center" style="width: 18%;">
                    @if ($isActive)
                        <span class="badge-active">● ACTIVO</span>
                    @else
                        <span class="badge-inactive">● INACTIVO</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="lbl">Marca:</td>
                <td class="val uppercase">{{ $brandName }}</td>
                <td class="lbl">Línea:</td>
                <td class="val uppercase">{{ $vehicle->line ?? 'N/A' }}</td>
                <td class="lbl">Modelo (Año):</td>
                <td class="val text-center mono">{{ $vehicle->model ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="lbl">Clase de Vehículo:</td>
                <td class="val uppercase">{{ $className }}</td>
                <td class="lbl">Carrocería:</td>
                <td class="val uppercase">{{ $vehicle->body_type ?? 'N/A' }}</td>
                <td class="lbl">Color:</td>
                <td class="val uppercase">{{ $vehicle->color ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="lbl">Combustible:</td>
                <td class="val uppercase">{{ $vehicle->fuel_type ?? 'N/A' }}</td>
                <td class="lbl">Cilindrada Motor:</td>
                <td class="val mono">{{ !empty($vehicle->engine_displacement) ? $vehicle->engine_displacement . ' CC' : 'N/A' }}</td>
                <td class="lbl">N° de Ejes:</td>
                <td class="val text-center mono">{{ !empty($vehicle->number_of_axles) ? $vehicle->number_of_axles . ' EJES' : 'N/A' }}</td>
            </tr>
        </tbody>
    </table>

    <!-- 2. ESPECIFICACIONES TÉCNICAS Y CAPACIDAD TRANSPORTADORA -->
    <table cellpadding="0" cellspacing="0" class="section-banner">
        <tbody>
            <tr>
                <td>2. Especificaciones Técnicas y Capacidad Transportadora</td>
            </tr>
        </tbody>
    </table>
    <table cellpadding="0" cellspacing="0" class="data-table">
        <tbody>
            <tr>
                <td class="lbl" style="width: 18%;">N° de Motor:</td>
                <td class="val mono" style="width: 32%;">{{ $vehicle->engine_number ?? 'N/A' }}</td>
                <td class="lbl" style="width: 18%;">N° Chasis:</td>
                <td class="val mono" style="width: 32%;">{{ $vehicle->chassis_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="lbl">VIN / Serial Chasis:</td>
                <td class="val mono">{{ $vehicle->vin_number ?? ($vehicle->serial_number ?? 'N/A') }}</td>
                <td class="lbl">Licencia de Tránsito:</td>
                <td class="val mono">
                    {{ $vehicle->transit_license_number ?? 'N/A' }}
                    @if (!empty($vehicle->transit_authority))
                        ({{ $vehicle->transit_authority }})
                    @endif
                </td>
            </tr>
            <tr>
                <td class="lbl">Capacidad Pasajeros:</td>
                <td class="val">
                    <strong>{{ $vehicle->passenger_capacity ?? 'N/A' }} Pasajeros</strong>
                    @if (!empty($vehicle->seated_passenger_capacity))
                        <span style="color: #64748b;">({{ $vehicle->seated_passenger_capacity }} Sentados)</span>
                    @endif
                </td>
                <td class="lbl">Modalidad de Servicio:</td>
                <td class="val">
                    <strong style="color: #1e3a8a;">{{ $vehicle->type_of_service ?? 'SERVICIO PÚBLICO ESPECIAL' }}</strong>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- 3. INFORMACIÓN DEL PROPIETARIO O TENEDOR REGISTRADO -->
    <table cellpadding="0" cellspacing="0" class="section-banner">
        <tbody>
            <tr>
                <td>3. Información del Propietario o Tenedor Registrado</td>
            </tr>
        </tbody>
    </table>
    <table cellpadding="0" cellspacing="0" class="data-table">
        <tbody>
            <tr>
                <td class="lbl" style="width: 22%;">Nombre / Razón Social:</td>
                <td class="val uppercase" style="width: 45%; color: #1e3a8a; font-weight: 700;">
                    {{ $tpName }}
                </td>
                <td class="lbl" style="width: 13%;">Documento:</td>
                <td class="val mono" style="width: 20%;">
                    {{ $tpDocType }} {{ $tpDocNum }}
                </td>
            </tr>
            <tr>
                <td class="lbl">Dirección y Contacto:</td>
                <td class="val" colspan="3">
                    {{ $tpAddress }}
                    @if ($tpCity)
                        , {{ $tpCity }}
                    @endif
                    @if ($tpPhone)
                        · Tel/Cel: {{ $tpPhone }}
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <!-- 4. DOCUMENTACIÓN REGLAMENTARIA Y PÓLIZAS OBLIGATORIAS (PESV) -->
    <table cellpadding="0" cellspacing="0" class="section-banner">
        <tbody>
            <tr>
                <td>4. Documentación Reglamentaria y Pólizas Obligatorias (PESV)</td>
            </tr>
        </tbody>
    </table>
    <table cellpadding="0" cellspacing="0" class="grid-table">
        <thead>
            <tr>
                <th style="width: 32%;">Tipo de Documento</th>
                <th style="width: 26%;">N° Póliza / Certificado</th>
                <th style="width: 24%;">Entidad Aseguradora / CDA</th>
                <th style="width: 10%;">Vencimiento</th>
                <th style="width: 8%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @if (!empty($vehicle->vehicle_documents) && count($vehicle->vehicle_documents) > 0)
                @foreach ($vehicle->vehicle_documents as $doc)
                    @php
                        $isDocExpired = !empty($doc->expiry_date) && \Carbon\Carbon::parse($doc->expiry_date)->isPast();
                        $expiryDocFormatted = !empty($doc->expiry_date) ? \Carbon\Carbon::parse($doc->expiry_date)->format('d/m/Y') : 'N/A';
                    @endphp
                    <tr>
                        <td class="text-left font-bold" style="padding-left: 5px;">{{ $doc->document_type ?? 'N/A' }}</td>
                        <td class="mono">{{ $doc->policy_number ?? 'N/A' }}</td>
                        <td class="uppercase">{{ $doc->issuing_entity ?? 'N/A' }}</td>
                        <td class="mono font-bold">{{ $expiryDocFormatted }}</td>
                        <td>
                            @if ($isDocExpired)
                                <span class="badge-inactive">VENCIDO</span>
                            @else
                                <span class="badge-active">VIGENTE</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" style="color: #64748b; font-style: italic; padding: 4px;">Sin documentos reglamentarios registrados</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- 5. TARJETA(S) DE OPERACIÓN - MINISTERIO DE TRANSPORTE -->
    <table cellpadding="0" cellspacing="0" class="section-banner">
        <tbody>
            <tr>
                <td>5. Tarjeta(s) de Operación - Ministerio de Transporte</td>
            </tr>
        </tbody>
    </table>
    <table cellpadding="0" cellspacing="0" class="grid-table">
        <thead>
            <tr>
                <th style="width: 24%;">N° Tarjeta de Operación</th>
                <th style="width: 44%;">Empresa de Transporte Habilitada</th>
                <th style="width: 12%;">Expedición</th>
                <th style="width: 12%;">Vencimiento</th>
                <th style="width: 8%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @if (!empty($vehicle->operation_cards) && count($vehicle->operation_cards) > 0)
                @foreach ($vehicle->operation_cards as $card)
                    @php
                        $isCardExpired = !empty($card->expiration_date) && \Carbon\Carbon::parse($card->expiration_date)->isPast();
                        $expFormatted = !empty($card->expiration_date) ? \Carbon\Carbon::parse($card->expiration_date)->format('d/m/Y') : 'N/A';
                        $issueFormatted = !empty($card->issue_date) ? \Carbon\Carbon::parse($card->issue_date)->format('d/m/Y') : 'N/A';
                        $affCompany = $card->affiliated_company ?? ($vehicle->company->business_name ?? 'N/A');
                    @endphp
                    <tr>
                        <td class="mono font-bold" style="color: #1e3a8a;">{{ $card->operating_card_number ?? 'N/A' }}</td>
                        <td class="text-left uppercase font-bold" style="padding-left: 5px;">{{ $affCompany }}</td>
                        <td class="mono">{{ $issueFormatted }}</td>
                        <td class="mono font-bold">{{ $expFormatted }}</td>
                        <td>
                            @if ($isCardExpired)
                                <span class="badge-inactive">VENCIDA</span>
                            @else
                                <span class="badge-active">VIGENTE</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" style="color: #64748b; font-style: italic; padding: 4px;">Sin tarjetas de operación registradas</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- 6. CONVENIO DE COLABORACIÓN EMPRESARIAL -->
    <table cellpadding="0" cellspacing="0" class="section-banner">
        <tbody>
            <tr>
                <td>6. Convenio de Colaboración Empresarial</td>
            </tr>
        </tbody>
    </table>
    @if (!empty($vehicle->business_collaboration_agreements) && count($vehicle->business_collaboration_agreements) > 0)
        @foreach ($vehicle->business_collaboration_agreements as $agreement)
            <table cellpadding="0" cellspacing="0" class="data-table">
                <tbody>
                    <tr>
                        <td class="lbl" style="width: 22%;">Empresa Aliada / Colaboradora:</td>
                        <td class="val uppercase font-bold" style="width: 45%; color: #0f172a;">
                            {{ $agreement->contracting_entity_name ?? 'N/A' }}
                        </td>
                        <td class="lbl" style="width: 13%;">N° de Convenio:</td>
                        <td class="val mono font-bold" style="width: 20%; color: #1e3a8a;">
                            {{ $agreement->agreement_internal_id ?? 'N/A' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Vigencia del Convenio:</td>
                        <td class="val mono" colspan="3">
                            {{ !empty($agreement->expiry_date) ? 'Hasta: ' . \Carbon\Carbon::parse($agreement->expiry_date)->format('d/m/Y') : 'Vigente' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        @endforeach
    @else
        <table cellpadding="0" cellspacing="0" class="data-table">
            <tbody>
                <tr>
                    <td class="val text-center" colspan="4" style="color: #64748b; font-style: italic; padding: 3px;">
                        No registra convenio de colaboración empresarial activo
                    </td>
                </tr>
            </tbody>
        </table>
    @endif

    <!-- 7. VALIDACIÓN ELECTRÓNICA QR Y RESPONSABILIDAD PESV -->
    <table cellpadding="0" cellspacing="0" class="qr-footer-table">
        <tbody>
            <tr>
                <td class="qr-box-cell">
                    @if (!empty($data['qrcode']))
                        <img src="data:image/svg+xml;base64,{{ $data['qrcode'] }}" alt="QR" style="width: 66px; height: 66px; display: block; margin: 0 auto;">
                    @else
                        <div style="font-size: 6.5pt; font-weight: bold; color: #64748b; text-align: center;">
                            VALIDACIÓN<br>PESV
                        </div>
                    @endif
                    <div style="font-size: 5.6pt; font-weight: bold; color: #475569; margin-top: 1px;">ESCANEAR QR</div>
                </td>
                <td class="qr-text-cell">
                    <strong style="color: #0f172a; text-transform: uppercase;">Validación Electrónica y Certificación PESV:</strong><br>
                    El presente documento constituye la <strong>Ficha Técnica Oficial</strong> del vehículo automotor, expedida de conformidad con las exigencias del <strong>Plan Estratégico de Seguridad Vial (PESV - Ley 1503 de 2011, Res. 20223040040595 de 2022)</strong> y directrices del Ministerio de Transporte. Escanee el código QR para validar en tiempo real la autenticidad, vigencia de pólizas obligatorias (SOAT, RCC, RCE), estado de inspección preoperacional diaria y la habilitación de la tarjeta de operación en la plataforma central de gestión de flota.
                </td>
            </tr>
        </tbody>
    </table>
@endsection
