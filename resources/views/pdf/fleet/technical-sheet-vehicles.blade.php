@php
    $orientation = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
    $companyName = $vehicle->company->business_name ?? 'EMPRESA DE TRANSPORTE ESPECIAL';
    $companyNit = $vehicle->company->document_number ?? null;
    $companyDetalle = !empty($companyNit) ? 'NIT ' . $companyNit : null;
    $logoResuelto = $logo_data_url ?? (!empty($data['logo']) ? 'data:image/png;base64,' . $data['logo'] : null);
    $siglasResueltas = $empresa_siglas ?? \App\Services\Pdf\PdfService::siglasEmpresa((string) $companyName);

    $isActive = isset($vehicle->is_active) ? (bool) $vehicle->is_active : (bool) ($vehicle->status ?? true);
    $brandName = $vehicle->brand->description ?? ($vehicle->brand_name ?? 'N/A');
    $className = $vehicle->vehicle_class->description ?? ($vehicle->vehicle_class_name ?? 'N/A');
    $tp = $vehicle->third_party ?? null;
    $tpName = $tp ? (trim(($tp->first_name ?? '') . ' ' . ($tp->last_name ?? '')) ?: ($tp->company_name ?? 'N/A')) : 'N/A';
    $tpDocType = $tp->type_of_document->prefix ?? 'CC';
    $tpDocNum = $tp->document_number ?? 'N/A';
    $tpAddress = $tp->address ?? 'N/A';
    $tpCity = $tp->municipality->name ?? null;
    $tpPhone = $tp->phone ?? null;

    // Especificaciones mecánicas: solo se muestran si existe al menos un campo registrado.
    $camposMecanicos = [
        $vehicle->steering_type ?? null,
        $vehicle->transmission_type ?? null,
        $vehicle->number_of_speeds ?? null,
        $vehicle->bearing_type ?? null,
        $vehicle->rear_suspension ?? null,
        $vehicle->number_of_tires ?? null,
        $vehicle->rim_size ?? null,
        $vehicle->rim_material ?? null,
        $vehicle->number_of_windows ?? null,
        $vehicle->front_brake_type ?? null,
        $vehicle->rear_brake_type ?? null,
    ];
    $tieneMecanicas = collect($camposMecanicos)->contains(function ($v): bool {
        $limpio = trim((string) ($v ?? ''));

        return $limpio !== '' && $limpio !== '0' && strtoupper($limpio) !== 'N/A';
    });

    // Capacidad de pasajeros: si la capacidad total es 0/vacía, mostrar sentados si existe.
    $capTotal = (int) ($vehicle->passenger_capacity ?? 0);
    $capSentados = (int) ($vehicle->seated_passenger_capacity ?? 0);

    // Número de serie: en blanco si no tiene o viene en 0 / N/A.
    $serialRaw = trim((string) ($vehicle->serial_number ?? ''));
    $serialMostrar = ($serialRaw !== '' && $serialRaw !== '0' && strtoupper($serialRaw) !== 'N/A') ? $serialRaw : '';

    $marcaFondo = $logo_fondo ?? $data['logo_fondo'] ?? null;
    $qrBase64 = $data['qrcode'] ?? null;
    $qrMime = $data['qrcode_mime'] ?? 'image/png';
    $sec = 1;
    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
    $sinMarcaVisual = !empty($letterhead ?? null) || !empty($ocultar_marca ?? false);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $title ?? 'Ficha Técnica Vehículo' }}</title>
    @include('pdf.standard.styles', ['orientation' => $orientation, 'pdfMargen' => 'denso'])
    <style>
        @if ($esHojaMembretadaCompleta)
        @page {
            size: letter {{ $orientation }};
            margin: 32.5mm 10mm 28mm 10mm;
        }
        @endif

        /* Escala legible de impresión Carta Vertical; se expande ligeramente cuando no hay sección mecánica */
        body {
            font-size: {{ $tieneMecanicas ? '7.3pt' : '7.7pt' }};
            line-height: {{ $tieneMecanicas ? '1.16' : '1.22' }};
            background-color: {{ $esHojaMembretadaCompleta ? 'transparent' : '#ffffff' }};
        }

        #letterhead-page-bg {
            position: fixed;
            top: -32.5mm;
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
            margin-bottom: 6px;
        }

        #letterhead-banner img {
            max-width: 100%;
            max-height: 2.2cm;
        }

        .header-table {
            margin-bottom: {{ $tieneMecanicas ? '4.5px' : '8px' }};
        }

        .section-banner {
            margin-top: {{ $tieneMecanicas ? '4.5px' : '8px' }};
        }

        .section-banner td {
            padding: {{ $tieneMecanicas ? '2.6px 7px' : '3.6px 7px' }};
            font-size: {{ $tieneMecanicas ? '7.1pt' : '7.5pt' }};
        }

        .info-table {
            margin-bottom: 0;
        }

        .info-table td {
            padding: {{ $tieneMecanicas ? '2.9px 5.5px' : '4.4px 6px' }};
            font-size: {{ $tieneMecanicas ? '7.1pt' : '7.6pt' }};
            line-height: {{ $tieneMecanicas ? '1.16' : '1.22' }};
        }

        .info-lbl {
            font-size: {{ $tieneMecanicas ? '6.4pt' : '6.8pt' }};
        }

        .placa-txt {
            font-weight: bold;
            font-size: 8.6pt;
            color: #000000;
        }

        .interno-txt {
            font-weight: bold;
            font-size: 8pt;
            color: #111827;
        }

        .estado-activo {
            font-weight: bold;
            color: #166534;
            text-transform: uppercase;
        }

        .estado-inactivo {
            font-weight: bold;
            color: #991b1b;
            text-transform: uppercase;
        }

        /* Tablas de grillas (documentos, tarjetas) */
        .main-table {
            border-top: none;
            margin-bottom: 0;
        }

        .main-table thead th {
            padding: {{ $tieneMecanicas ? '3px 4px' : '4.2px 5px' }};
            font-size: {{ $tieneMecanicas ? '6.5pt' : '7pt' }};
        }

        .main-table tbody td {
            padding: {{ $tieneMecanicas ? '2.8px 5px' : '4px 5px' }};
            font-size: {{ $tieneMecanicas ? '7pt' : '7.5pt' }};
            line-height: {{ $tieneMecanicas ? '1.15' : '1.22' }};
        }

        .qr-footer-table {
            border: 1px solid var(--pdf-borde);
            margin-top: {{ $tieneMecanicas ? '5px' : '9px' }};
            background-color: #ffffff;
            page-break-inside: avoid;
        }

        .qr-footer-table td {
            padding: {{ $tieneMecanicas ? '4.5px 8px' : '6.5px 9px' }};
            vertical-align: middle;
        }

        .qr-box-cell {
            width: 16%;
            text-align: center;
            border-right: 0.8px solid var(--pdf-borde-interno);
            background-color: var(--pdf-tenue);
        }

        .qr-text-cell {
            width: 84%;
            font-size: {{ $tieneMecanicas ? '7pt' : '7.3pt' }};
            line-height: 1.32;
            color: #111827;
            text-align: justify;
        }

        .txt-left { text-align: left !important; }
        .txt-center { text-align: center !important; }
        .uppercase { text-transform: uppercase; }

        @if ($esHojaMembretadaCompleta)
        .meta-table td { padding: 3px 6px !important; }
        .header-table { margin-bottom: 3px; }
        .section-banner { margin-top: 2.5px; }
        .section-banner td { padding: 1.8px 6px; font-size: 6.7pt; }
        .info-table td { padding: 1.7px 5px; font-size: 6.8pt; line-height: 1.12; }
        .info-lbl { font-size: 6.1pt; }
        .main-table thead th { padding: 2px 4px; font-size: 6.2pt; }
        .main-table tbody td { padding: 1.7px 4.5px; font-size: 6.7pt; line-height: 1.12; }
        .qr-footer-table { margin-top: 3px; }
        .qr-footer-table td { padding: 3px 7px; }
        .qr-box-cell img { width: 48px !important; height: 48px !important; }
        @endif
    </style>
</head>
<body>

    @if (empty($ocultar_marca ?? false) && empty($letterhead ?? null) && !empty($marcaFondo))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $marcaFondo }}" alt="Marca de agua" />
        </div>
    @endif

    @if (!empty($letterhead ?? null) && $esHojaMembretadaCompleta)
        <div id="letterhead-page-bg">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @elseif (!empty($letterhead ?? null))
        <div id="letterhead-banner">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @endif

    @include('pdf.standard.header', [
        'logo_data_url' => $sinMarcaVisual ? null : $logoResuelto,
        'ocultar_celda_logo' => $sinMarcaVisual,
        'empresa_siglas' => $siglasResueltas,
        'empresa_transportadora' => $companyName,
        'empresa_detalle' => $companyDetalle,
        'titulo_documento' => 'FICHA TÉCNICA DEL VEHÍCULO',
        'subtitulo_documento' => 'PLAN ESTRATÉGICO DE SEGURIDAD VIAL (PESV) · HOJA DE VIDA AUTOMOTOR',
        'meta_sgc' => [
            'CÓDIGO' => 'JG-VEH-001',
            'VERSIÓN' => '1.0',
            'VIGENCIA' => strtoupper(now()->translatedFormat('M Y')),
        ],
    ])

    {{-- 1. IDENTIFICACIÓN Y ESTADO OPERATIVO --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>{{ $sec++ }}. Identificación y Estado Operativo del Vehículo</td></tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 14%;">Placa:</td>
            <td class="info-val" style="width: 21%;">
                <strong class="placa-txt">{{ $vehicle->vehicle_license_plate ?? 'N/A' }}</strong>
            </td>
            <td class="info-lbl" style="width: 14%;">N° Interno:</td>
            <td class="info-val" style="width: 19%;">
                <strong class="interno-txt">{{ !empty($vehicle->internal_number) ? 'INT-' . $vehicle->internal_number : 'N/A' }}</strong>
            </td>
            <td class="info-lbl" style="width: 13%;">Estado:</td>
            <td class="info-val txt-center" style="width: 19%;">
                @if ($isActive)
                    <span class="estado-activo">ACTIVO</span>
                @else
                    <span class="estado-inactivo">INACTIVO</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="info-lbl">Marca:</td>
            <td class="info-val uppercase">{{ $brandName }}</td>
            <td class="info-lbl">Línea:</td>
            <td class="info-val uppercase">{{ $vehicle->line ?? 'N/A' }}</td>
            <td class="info-lbl">Modelo (Año):</td>
            <td class="info-val txt-center mono" style="font-weight: bold;">{{ $vehicle->model ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Clase:</td>
            <td class="info-val uppercase">{{ $className }}</td>
            <td class="info-lbl">Carrocería:</td>
            <td class="info-val uppercase">{{ $vehicle->body_type ?? 'N/A' }}</td>
            <td class="info-lbl">Color:</td>
            <td class="info-val uppercase">{{ $vehicle->color ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Combustible:</td>
            <td class="info-val uppercase">{{ $vehicle->fuel_type ?? 'N/A' }}</td>
            <td class="info-lbl">Cilindrada:</td>
            <td class="info-val mono">{{ !empty($vehicle->engine_displacement) ? $vehicle->engine_displacement . ' CC' : 'N/A' }}</td>
            <td class="info-lbl">N° de Ejes:</td>
            <td class="info-val txt-center mono">{{ !empty($vehicle->number_of_axles) ? $vehicle->number_of_axles . ' EJES' : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Matrícula:</td>
            <td class="info-val mono">{{ !empty($vehicle->registration_date) ? \Carbon\Carbon::parse($vehicle->registration_date)->format('d/m/Y') : 'N/A' }}</td>
            <td class="info-lbl">N° de Puertas:</td>
            <td class="info-val txt-center mono">{{ $vehicle->doors ?? 'N/A' }}</td>
            <td class="info-lbl">Sucursal:</td>
            <td class="info-val uppercase">{{ $vehicle->branch->name ?? 'N/A' }}</td>
        </tr>
    </table>

    {{-- 2. ESPECIFICACIONES TÉCNICAS Y CAPACIDAD TRANSPORTADORA --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>{{ $sec++ }}. Especificaciones Técnicas y Capacidad Transportadora</td></tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 18%;">N° de Motor:</td>
            <td class="info-val mono" style="width: 30%;">{{ $vehicle->engine_number ?? 'N/A' }}</td>
            <td class="info-lbl" style="width: 23%;">N° de Chasis:</td>
            <td class="info-val mono" style="width: 29%;">{{ $vehicle->chassis_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">VIN:</td>
            <td class="info-val mono">{{ $vehicle->vin_number ?? 'N/A' }}</td>
            <td class="info-lbl">N° de Serie:</td>
            <td class="info-val mono">{{ $serialMostrar }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Licencia Tránsito:</td>
            <td class="info-val mono" colspan="3">
                {{ $vehicle->transit_license_number ?? 'N/A' }}
                @if (!empty($vehicle->transit_authority))
                    ({{ $vehicle->transit_authority }})
                @endif
            </td>
        </tr>
        <tr>
            <td class="info-lbl">Cap. Pasajeros:</td>
            <td class="info-val">
                @if ($capTotal > 0 && $capSentados > 0)
                    <strong>{{ $capTotal }} Pasajeros</strong>
                    <span style="color: #262626;">({{ $capSentados }} Sentados)</span>
                @elseif ($capTotal > 0)
                    <strong>{{ $capTotal }} Pasajeros</strong>
                @elseif ($capSentados > 0)
                    <strong>{{ $capSentados }} Sentados</strong>
                @else
                    N/A
                @endif
            </td>
            <td class="info-lbl">Modalidad:</td>
            <td class="info-val" style="color: #000000; font-weight: bold;">
                {{ $vehicle->type_of_service ?? 'SERVICIO PÚBLICO ESPECIAL' }}
            </td>
        </tr>
        <tr>
            <td class="info-lbl">Cap. de Carga:</td>
            <td class="info-val mono">{{ !empty($vehicle->load_capacity) ? number_format((float) $vehicle->load_capacity, 0, ',', '.') . ' KG' : 'N/A' }}</td>
            <td class="info-lbl">Peso Bruto Vehicular:</td>
            <td class="info-val mono">{{ !empty($vehicle->gross_vehicle_weight) ? number_format((float) $vehicle->gross_vehicle_weight, 0, ',', '.') . ' KG' : 'N/A' }}</td>
        </tr>
    </table>

    {{-- 3. ESPECIFICACIONES MECÁNICAS (solo si existe algún dato registrado) --}}
    @if ($tieneMecanicas)
        <table class="section-banner" cellpadding="0" cellspacing="0">
            <tr><td>{{ $sec++ }}. Especificaciones Mecánicas</td></tr>
        </table>
        <table class="info-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="info-lbl" style="width: 16%;">Dirección:</td>
                <td class="info-val uppercase" style="width: 18%;">{{ $vehicle->steering_type ?? 'N/A' }}</td>
                <td class="info-lbl" style="width: 16%;">Transmisión:</td>
                <td class="info-val uppercase" style="width: 18%;">{{ $vehicle->transmission_type ?? 'N/A' }}</td>
                <td class="info-lbl" style="width: 16%;">Velocidades:</td>
                <td class="info-val txt-center mono" style="width: 16%;">{{ $vehicle->number_of_speeds ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="info-lbl">Rodamientos:</td>
                <td class="info-val uppercase">{{ $vehicle->bearing_type ?? 'N/A' }}</td>
                <td class="info-lbl">Susp. Trasera:</td>
                <td class="info-val uppercase">{{ $vehicle->rear_suspension ?? 'N/A' }}</td>
                <td class="info-lbl">N° Llantas:</td>
                <td class="info-val txt-center mono">{{ $vehicle->number_of_tires ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="info-lbl">Dimensión Rines:</td>
                <td class="info-val mono">{{ $vehicle->rim_size ?? 'N/A' }}</td>
                <td class="info-lbl">Material Rines:</td>
                <td class="info-val uppercase">{{ $vehicle->rim_material ?? 'N/A' }}</td>
                <td class="info-lbl">N° Ventanas:</td>
                <td class="info-val txt-center mono">{{ $vehicle->number_of_windows ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="info-lbl">Frenos Delanteros:</td>
                <td class="info-val uppercase">{{ $vehicle->front_brake_type ?? 'N/A' }}</td>
                <td class="info-lbl">Frenos Traseros:</td>
                <td class="info-val uppercase" colspan="3">{{ $vehicle->rear_brake_type ?? 'N/A' }}</td>
            </tr>
        </table>
    @endif

    {{-- PROPIETARIO O TENEDOR --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>{{ $sec++ }}. Información del Propietario o Tenedor Registrado</td></tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 20%;">Nombre / Razón Social:</td>
            <td class="info-val uppercase" style="width: 47%; color: #000000; font-weight: bold;">{{ $tpName }}</td>
            <td class="info-lbl" style="width: 13%;">Documento:</td>
            <td class="info-val mono" style="width: 20%;">{{ $tpDocType }} {{ $tpDocNum }}</td>
        </tr>
    </table>

    {{-- DOCUMENTACIÓN REGLAMENTARIA Y PÓLIZAS OBLIGATORIAS --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>{{ $sec++ }}. Documentación Reglamentaria y Pólizas Obligatorias (PESV)</td></tr>
    </table>
    <table class="main-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 34%;">Tipo de Documento</th>
                <th style="width: 26%;">N° Póliza / Certificado</th>
                <th style="width: 27%;">Entidad Aseguradora / CDA</th>
                <th style="width: 13%;">Vencimiento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vehicle->vehicle_documents ?? [] as $doc)
                @php
                    $expiryDocFormatted = !empty($doc->expiry_date) ? \Carbon\Carbon::parse($doc->expiry_date)->format('d/m/Y') : 'N/A';
                @endphp
                <tr>
                    <td class="txt-center" style="font-weight: bold;">{{ $doc->document_type ?? 'N/A' }}</td>
                    <td class="txt-center mono">{{ $doc->policy_number ?? 'N/A' }}</td>
                    <td class="uppercase">{{ $doc->issuing_entity ?? 'N/A' }}</td>
                    <td class="txt-center mono" style="font-weight: bold;">{{ $expiryDocFormatted }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="txt-center" style="color: #374151; font-style: italic; padding: 4px;">
                        Sin documentos reglamentarios registrados
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- TARJETA(S) DE OPERACIÓN --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>{{ $sec++ }}. Tarjeta(s) de Operación — Ministerio de Transporte</td></tr>
    </table>
    <table class="main-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 24%;">N° Tarjeta de Operación</th>
                <th style="width: 48%;">Empresa de Transporte Habilitada</th>
                <th style="width: 14%;">Expedición</th>
                <th style="width: 14%;">Vencimiento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vehicle->operation_cards ?? [] as $card)
                @php
                    $expFormatted = !empty($card->expiration_date) ? \Carbon\Carbon::parse($card->expiration_date)->format('d/m/Y') : 'N/A';
                    $issueFormatted = !empty($card->issue_date) ? \Carbon\Carbon::parse($card->issue_date)->format('d/m/Y') : 'N/A';
                    $affCompany = $card->affiliated_company ?? ($vehicle->company->business_name ?? 'N/A');
                @endphp
                <tr>
                    <td class="txt-center mono" style="color: #0f172a; font-weight: bold;">{{ $card->operating_card_number ?? 'N/A' }}</td>
                    <td class="txt-left uppercase" style="font-weight: bold;">{{ $affCompany }}</td>
                    <td class="txt-center mono">{{ $issueFormatted }}</td>
                    <td class="txt-center mono" style="font-weight: bold;">{{ $expFormatted }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="txt-center" style="color: #374151; font-style: italic; padding: 4px;">
                        Sin tarjetas de operación registradas
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- CONVENIO DE COLABORACIÓN EMPRESARIAL --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>{{ $sec++ }}. Convenio de Colaboración Empresarial</td></tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        @forelse ($vehicle->business_collaboration_agreements ?? [] as $agreement)
            <tr>
                <td class="info-lbl" style="width: 21%;">Empresa Aliada:</td>
                <td class="info-val uppercase" style="width: 44%; font-weight: bold; color: #0f172a;">
                    {{ $agreement->contracting_entity_name ?? 'N/A' }}
                </td>
                <td class="info-lbl" style="width: 14%;">N° / Vigencia:</td>
                <td class="info-val mono" style="width: 21%; color: #000000; font-weight: bold;">
                    {{ $agreement->agreement_internal_id ?? 'N/A' }}
                    @if (!empty($agreement->expiry_date))
                        <span style="color: #262626; font-weight: normal;">({{ \Carbon\Carbon::parse($agreement->expiry_date)->format('d/m/Y') }})</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td class="info-val txt-center" colspan="4" style="color: #374151; font-style: italic; padding: 3.5px;">
                    No registra convenio de colaboración empresarial activo
                </td>
            </tr>
        @endforelse
    </table>

    {{-- VALIDACIÓN ELECTRÓNICA QR Y CERTIFICACIÓN PESV --}}
    <table class="cierre" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table class="qr-footer-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="qr-box-cell">
                            @if (!empty($qrBase64))
                                <img src="data:{{ $qrMime }};base64,{{ $qrBase64 }}" alt="QR" style="width: 68px; height: 68px; display: block; margin: 0 auto;">
                            @else
                                <div style="font-size: 6.2pt; font-weight: bold; color: #262626; text-align: center;">
                                    VALIDACIÓN<br>PESV
                                </div>
                            @endif
                            <div style="font-size: 5.4pt; font-weight: bold; color: #111827; margin-top: 1px;">ESCANEAR QR</div>
                        </td>
                        <td class="qr-text-cell">
                            <strong style="color: #0f172a; text-transform: uppercase;">Validación Electrónica y Certificación PESV:</strong><br>
                            El presente documento constituye la <strong>Ficha Técnica Oficial</strong> del vehículo automotor, expedida de conformidad con las exigencias del <strong>Plan Estratégico de Seguridad Vial (PESV - Ley 1503 de 2011, Res. 20223040040595 de 2022)</strong> y directrices del Ministerio de Transporte. Escanee el código QR para validar en tiempo real la autenticidad, vigencia de pólizas obligatorias (SOAT, RCC, RCE), estado de inspección preoperacional diaria y la habilitación de la tarjeta de operación en la plataforma central de gestión de flota.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
