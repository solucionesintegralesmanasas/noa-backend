<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Ficha Técnica del Conductor - PDF NOA</title>
    <style>
        /* Configuración de página Carta (Letter Portrait: 8.5in x 11in).
           La hoja completa es la página: el padding del "sheet" hace de margen. */
        @page {
            size: letter portrait;
            margin: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            line-height: 1.35;
            color: #000000;
            margin: 0;
            padding: 0;
            background: #ffffff;
            /* `overflow-wrap` es heredable en DomPDF: cubre también los textos
               que van en divs (encabezado, bandas de sección) y no solo en td/th. */
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        /* Contenedor de hoja física Letter: ocupa el alto completo de la página.
           `width: auto` (no 100%) para que el padding no desborde el ancho carta:
           DomPDF no soporta box-sizing y 100% + 28mm se salía de la hoja. */
        .sheet-preview {
            width: auto;
            min-height: 10in;
            margin: 0;
            background: #ffffff;
            padding: 12mm 14mm 12mm 14mm;
            position: relative;
        }

        /* Tablas base 100% compatibles con DomPDF */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td,
        th {
            vertical-align: middle;
            /* Partir palabras/correos/NIT largos dentro del ancho de la celda
               en lugar de dejar que se salgan o se recorten. */
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }

        /* Encabezado Institucional de Control Documental */
        .header-box {
            width: 100%;
            border: 1.5px solid #0f172a;
            margin-bottom: 14px;
            border-collapse: collapse;
        }

        .header-logo-cell {
            width: 22%;
            text-align: center;
            padding: 11px 6px;
            border-right: 1.5px solid #0f172a;
            background-color: #ffffff;
            vertical-align: middle;
        }

        .header-title-cell {
            width: 54%;
            text-align: center;
            padding: 11px 10px;
            border-right: 1.5px solid #0f172a;
            vertical-align: middle;
        }

        .header-meta-cell {
            width: 24%;
            padding: 0;
            vertical-align: middle;
        }

        img.logo-empresa {
            max-width: 100%;
            max-height: 52px;
            height: auto;
        }

        .company-title {
            font-size: 8.8pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }

        .doc-main-title {
            font-size: 10pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .doc-sub-title {
            font-size: 7pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 12px 6px;
            font-size: 7.2pt;
            border-bottom: 1px solid #cbd5e1;
        }

        .meta-table tr:last-child td {
            border-bottom: none;
        }

        .meta-label {
            font-weight: bold;
            color: #000000;
            width: 50%;
            background-color: #f8fafc;
            border-right: 1px solid #94a3b8;
            text-transform: uppercase;
        }

        .meta-val {
            font-weight: bold;
            color: #000000;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
        }

        /* Títulos de Sección */
        .section-banner {
            background-color: #f8fafc;
            color: #000000;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 8px;
            margin-top: 11px;
            margin-bottom: 8px;
            border: 1px solid #94a3b8;
            border-left: 4px solid #475569;
            border-radius: 2px;
        }

        .section-banner-alt {
            background-color: #f8fafc;
        }

        /* Grilla de Datos Personales con Foto */
        .personal-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .data-grid-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #94a3b8;
        }

        .data-grid-table td {
            padding: 11px 6px;
            line-height: 15px;
            font-size: 7.8pt;
            border: 1px solid #cbd5e1;
        }

        .field-label {
            background-color: #f8fafc;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            font-size: 7.2pt;
        }

        .data-grid-table td.field-label {
            border-right: 1px solid #94a3b8;
        }

        .field-value {
            color: #000000;
            font-weight: bold;
        }

        .mono-value {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            font-weight: bold;
            color: #000000;
        }

        /* Foto del Conductor */
        .photo-cell-container {
            width: 23%;
            padding-left: 8px;
            vertical-align: top;
        }

        .photo-box {
            border: 1.5px solid #0f172a;
            height: 150px;
            background-color: #f8fafc;
            text-align: center;
            vertical-align: middle;
            border-radius: 2px;
            overflow: hidden;
            display: table;
            width: 100%;
        }

        .photo-inner {
            display: table-cell;
            vertical-align: middle;
            padding: 4px;
        }

        img.foto-conductor {
            max-width: 100%;
            max-height: 115px;
            height: auto;
        }

        /* Tablas Estructuradas Estandarizadas (.table-data) */
        .table-data {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #94a3b8;
            margin-bottom: 9px;
            font-size: 7.5pt;
        }

        .table-data th {
            background-color: #f8fafc;
            color: #000000;
            font-weight: bold;
            text-transform: uppercase;
            padding: 13px 5px;
            border: 1px solid #94a3b8;
            border-bottom: 2px solid #475569;
            font-size: 7.2pt;
            line-height: 15px;
            text-align: center;
        }

        .table-data td {
            padding: 11px 5px;
            line-height: 15px;
            border: 1px solid #cbd5e1;
            color: #000000;
            text-align: center;
        }

        /* Badges de Estado */
        .badge-active {
            background-color: #dcfce7;
            color: #000000;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            display: inline-block;
            font-size: 6.8pt;
            border: 1px solid #86efac;
            text-transform: uppercase;
        }

        .badge-inactive {
            background-color: #fee2e2;
            color: #000000;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            display: inline-block;
            font-size: 6.8pt;
            border: 1px solid #fca5a5;
            text-transform: uppercase;
        }

        .badge-highlight {
            background-color: #e5e7eb;
            color: #000000;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            display: inline-block;
            font-size: 6.8pt;
            border: 1px solid #93c5fd;
        }

        /* Fila de Seguridad Social Combinada 2x2 */
        .ss-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #94a3b8;
            margin-bottom: 8px;
        }

        .ss-table th {
            background-color: #f8fafc;
            border: 1px solid #94a3b8;
            border-bottom: 2px solid #475569;
            padding: 10px 5px;
            font-size: 7pt;
            line-height: 15px;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
        }

        .ss-table td {
            border: 1px solid #cbd5e1;
            padding: 11px 5px;
            line-height: 15px;
            font-size: 7.3pt;
            text-align: center;
        }

        .ss-entity-title {
            font-weight: bold;
            color: #000000;
            font-size: 7.2pt;
            text-align: left !important;
            padding-left: 6px !important;
            background-color: #f8fafc;
        }

        .ss-table td.ss-entity-title {
            border-right: 1px solid #94a3b8;
        }

        /* Firmas y Controles */
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: avoid;
        }

        .sig-cell {
            width: 50%;
            padding: 4px 14px;
            vertical-align: bottom;
        }

        .sig-line {
            border-top: 1.2px solid #0f172a;
            padding-top: 3px;
            text-align: center;
        }

        .sig-title {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
        }

        .sig-sub {
            font-size: 6.8pt;
            color: #000000;
        }

        /* Footer Institucional */
        .footer-bar {
            margin-top: 8px;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
            font-size: 6.8pt;
            color: #000000;
        }

        .footer-table td {
            padding: 0;
        }

        .uppercase {
            text-transform: uppercase;
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

        .font-bold {
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="sheet-preview">

        @php
            $fmtFecha = fn($fecha) => $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '';

            $contrato = $driver->employmentContracts->first();
            $aportes = $driver->socialSecurityContributions
                ->sortByDesc(fn($c) => optional($c->billing_period)->timestamp ?? 0)
                ->first();

            $nitEmpresa = trim(
                ($driver->company->document_number ?? '') .
                    (!empty($driver->company->verification_digit) ? ' - ' . $driver->company->verification_digit : ''),
            );
            $ciudad = $driver->municipality
                ? trim(
                    $driver->municipality->name .
                        ($driver->municipality->department ? ', ' . $driver->municipality->department->name : ''),
                )
                : '';
            // Evita la coma inicial cuando el tercero no tiene dirección
            // registrada: en ese caso solo se muestra la ciudad/departamento.
            $direccion = implode(
                ', ',
                array_filter([trim((string) ($driver->address ?? '')), $ciudad], fn($parte) => $parte !== ''),
            );
        @endphp

        <!-- ENCABEZADO FORMAL SGC (Sistema de Gestión de Calidad / PESV) -->
        <table class="header-box" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <!-- Logo Corporativo -->
                    <td class="header-logo-cell">
                        @if (!empty($data['logo']))
                            <img class="logo-empresa" src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo">
                        @else
                            <div
                                style="font-weight: bold; font-size: 16pt; color: #000000; letter-spacing: -0.5px; line-height: 1;">
                                SB
                            </div>
                            <div
                                style="font-size: 6.8pt; font-weight: bold; color: #000000; text-transform: uppercase; margin-top: 2px; line-height: 1.1;">
                                Transportes Especiales<br><span style="color: #000000;">Sin Barreras S.A.S.</span>
                            </div>
                        @endif
                    </td>

                    <!-- Título Central -->
                    <td class="header-title-cell">
                        <div class="company-title">{{ $driver->company->business_name ?? '' }}</div>
                        <div class="doc-main-title">FICHA TÉCNICA DEL CONDUCTOR</div>
                        <div class="doc-sub-title">PLAN ESTRATÉGICO DE SEGURIDAD VIAL (PESV) · JEFE OPERATIVO</div>
                    </td>

                    <!-- Metadatos de Control Calidad -->
                    <td class="header-meta-cell">
                        <table class="meta-table" cellpadding="0" cellspacing="0">
                            <tbody>
                                <tr>
                                    <td class="meta-label">Código</td>
                                    <td class="meta-val">FT-TAL-003</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">Versión</td>
                                    <td class="meta-val">02</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">Fecha</td>
                                    <td class="meta-val">{{ now()->format('d/m/Y') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- SECCIÓN 1: DATOS PERSONALES DEL CONDUCTOR -->
        <div class="section-banner">1. Identificación y Datos Personales del Conductor</div>

        <table class="personal-table" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <!-- Bloque de Campos en Grilla Formal -->
                    <td style="width: 77%; vertical-align: top;">
                        <table class="data-grid-table" cellpadding="0" cellspacing="0">
                            <tbody>
                                <tr>
                                    <td class="field-label" style="width: 33%;">Cédula de Ciudadanía:</td>
                                    <td class="mono-value" style="width: 27%;">{{ $driver->document_number ?? '' }}</td>
                                    <td class="field-label" style="width: 21%;">Expedida en:</td>
                                    <td class="field-value" style="width: 19%;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="field-label">Nombres y Apellidos:</td>
                                    <td class="field-value uppercase" colspan="3"
                                        style="font-size: 8.5pt; color: #000000;">
                                        {{ $driver->full_name ?? '' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="field-label">Fecha y Lugar Nacimiento:</td>
                                    <td class="field-value" colspan="3">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="field-label">Sexo:</td>
                                    <td class="field-value">&nbsp;</td>
                                    <td class="field-label">Estado Civil:</td>
                                    <td class="field-value">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="field-label">Teléfono:</td>
                                    <td class="mono-value">{{ $driver->phone ?? '' }}&nbsp;</td>
                                    <td class="field-label">G.S. RH:</td>
                                    <td class="mono-value">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="field-label">Dirección de Residencia:</td>
                                    <td class="field-value uppercase" colspan="3">
                                        {{ $direccion }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="field-label">Correo Electrónico:</td>
                                    <td class="field-value" colspan="3">
                                        {{ $driver->email ?? '' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>

                    <!-- Recuadro para Fotografía Formal -->
                    <td class="photo-cell-container">
                        <div class="photo-box">
                            <div class="photo-inner">
                                @if (!empty($data['photo']))
                                    <img class="foto-conductor" src="data:image/png;base64,{{ $data['photo'] }}"
                                        alt="Fotografía">
                                @else
                                    <!-- Icono/Silueta SVG Vectorial nativa sin dependencias -->
                                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none"
                                        stroke="#000000" stroke-width="1.5" style="margin: 0 auto; display: block;">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z">
                                        </path>
                                    </svg>
                                    <div
                                        style="font-size: 6.8pt; font-weight: bold; color: #000000; margin-top: 4px; text-transform: uppercase;">
                                        FOTOGRAFÍA 3x4
                                    </div>
                                    <div style="font-size: 5.8pt; color: #000000; margin-top: 1px;">
                                        Fondo blanco
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- SECCIÓN 2: EMPRESA AFILIADA Y VINCULACIÓN OPERATIVA -->
        <div class="section-banner">2. Datos de la Empresa y Modalidad de Servicio</div>
        <table class="table-data" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 44%;">Empresa a la que se Vincula</th>
                    <th style="width: 26%;">Identificación Tributaria (NIT)</th>
                    <th style="width: 30%;">Modalidad y Radio de Acción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-bold text-left uppercase" style="color: #000000; padding-left: 8px;">
                        {{ $driver->company->business_name ?? '' }}
                    </td>
                    <td class="mono-value">{{ $nitEmpresa }}</td>
                    <td class="font-bold uppercase">SERVICIO ESPECIAL · NACIONAL</td>
                </tr>
            </tbody>
        </table>

        <!-- SECCIÓN 3: LICENCIA DE CONDUCCIÓN -->
        <div class="section-banner">3. Licencia(s) de Conducción y Habilitación RUNT</div>
        <table class="table-data" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 30%;">Nro. de Licencia</th>
                    <th style="width: 18%;">Categoría</th>
                    <th style="width: 26%;">Fecha Expedición</th>
                    <th style="width: 26%;">Vigencia / Vence</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($driver->driverLicenses as $licencia)
                    <tr>
                        <td class="mono-value">{{ $licencia->number ?? '' }}</td>
                        <td><strong style="color: #000000; font-size: 8.5pt;">{{ $licencia->category ?? '' }}</strong>
                        </td>
                        <td>{{ $fmtFecha($licencia->issue_date) }}</td>
                        <td><strong>{{ $fmtFecha($licencia->expiration_date) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- SECCIÓN 4: INFORMACIÓN CONTRACTUAL Y LABORAL -->
        <div class="section-banner">4. Información del Contrato Laboral</div>
        <table class="table-data" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 26%;">Tipo de Contrato</th>
                    <th style="width: 22%;">Cargo Operativo</th>
                    <th style="width: 16%;">Fecha de Inicio</th>
                    <th style="width: 16%;">Fecha de Fin</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-bold uppercase text-left" style="padding-left: 8px;">
                        {{ $contrato->contract_type ?? '' }}&nbsp;
                    </td>
                    <td class="font-bold uppercase" style="color: #000000;">{{ $contrato->position ?? '' }}&nbsp;
                    </td>
                    <td>{{ $fmtFecha($contrato->start_date ?? null) }}&nbsp;</td>
                    <td>
                        @if ($contrato && !$contrato->end_date)
                            <span style="font-style: italic; color: #000000;">INDEFINIDO</span>
                        @else
                            {{ $fmtFecha($contrato->end_date ?? null) }}
                        @endif
                        &nbsp;
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- SECCIÓN 5: SEGURIDAD SOCIAL INTEGRAL (EPS, ARL, AFP, CCF) -->
        <div class="section-banner">5. Afiliaciones al Sistema de Seguridad Social Integral</div>
        <table class="ss-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 26%; text-align: left; padding-left: 6px;">Subsistema</th>
                    <th style="width: 32%; text-align: left; padding-left: 6px;">Entidad Administradora</th>
                    <th style="width: 18%;">Régimen / Nivel</th>
                    <th style="width: 14%;">Fecha Afiliación</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subsistemas = [
                        [
                            'Salud (EPS)',
                            $aportes->eps_name ?? '',
                            $aportes->eps_affiliation_date ?? null,
                            $aportes->health_paid ?? false,
                        ],
                        [
                            'Riesgos Laborales (ARL)',
                            $aportes->risk_labor_name ?? '',
                            $aportes->risk_labor_affiliation_date ?? null,
                            $aportes->risk_labor_paid ?? false,
                        ],
                        [
                            'Pensiones (AFP)',
                            $aportes->pension_name ?? '',
                            $aportes->pension_affiliation_date ?? null,
                            $aportes->pension_paid ?? false,
                        ],
                        [
                            'Caja Compensación (CCF)',
                            $aportes->compensation_fund_name ?? '',
                            $aportes->compensation_fund_affiliation_date ?? null,
                            $aportes->compensation_fund_paid ?? false,
                        ],
                    ];
                @endphp
                @foreach ($subsistemas as [$subsistema, $entidad, $fechaAfiliacion, $pagado])
                    <tr>
                        <td class="ss-entity-title">{{ $subsistema }}</td>
                        <td class="text-left font-bold uppercase" style="padding-left: 6px;">
                            {{ $entidad }}&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>{{ $fmtFecha($fechaAfiliacion) }}&nbsp;</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    </div>

</body>

</html>
