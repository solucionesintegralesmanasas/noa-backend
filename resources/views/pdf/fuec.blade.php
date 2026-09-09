<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUEC - {{ $fuec->number_fuec }}</title>
    <style>
        @page {
            size: letter;
            margin: 0cm 0cm;
        }

        body {
            font-family: Arial, sans-serif;
            /* Aumentado de 9px a 11px */
            font-size: 9px;
            margin-top: 0.8cm;
            margin-bottom: 0.8cm;
            margin-left: 0.8cm;
            margin-right: 0.8cm;
            padding: 0;
            color: #000;
            background-color: #fff;
        }

        /**
        * Defina la marca de agua centrada y transparente
        **/
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
            /* Opacidad al 15% para que se noten más los colores sin perder legibilidad */
        }

        #cancelado-watermark {
            position: fixed;
            top: 15%;
            left: 5%;
            width: 90%;
            height: 70%;
            z-index: -500;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        #cancelado-watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            opacity: 0.4;
        }

        .main-container {
            width: 100%;
            border: 1.2px solid #000;
            border-radius: 12px;
            overflow: hidden;
            border-collapse: separate;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td {
            border: 0.5px solid #000;
            padding: 6px 8px;
            /* Un poco más de aire */
            vertical-align: middle;
            word-wrap: break-word;
        }

        .label {
            font-weight: bold;
            /* Aumentado de 8px a 10px */
            font-size: 10px;
            text-transform: uppercase;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        /* Título del FUEC */
        h3 {
            font-size: 13px;
            margin: 5px 0;
        }

        .qr-section-container {
            display: table;
            width: 100%;
            border-top: 1px solid #000;
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
            /* Aumentado a 11px */
            font-size: 11px;
            line-height: 1.4;
        }

        img.qr {
            width: 85px;
            height: 85px;
            display: block;
            margin: 0 auto;
        }

        img.firma {
            width: 140px;
            height: 55px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        /* Aumentado de 9px a 10.5px */
        .company-info {
            font-size: 10.5px;
            text-align: center;
            line-height: 1.4;
        }

        /* Aumentado de 8px a 10px */
        .legal-footer {
            font-size: 10px;
            text-align: center;
            /* margin-top: 15px; */
            border: none;
        }

        strong {
            font-size: 11px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 10px;
        }

        .header-table td {
            width: 33.33%;
            vertical-align: middle;
            text-align: center;
            border: none;
            padding: 5px;
        }

        .logo-header {
            max-width: 100%;
            height: auto;
            max-height: 65px;
            display: inline-block;
        }

        .page-break {
            page-break-before: always;
        }

        .tabla-validacion {
            width: 100%;
            border: 1px solid #000;
            border-radius: 7px;
            overflow: hidden;
            border-collapse: separate;
            margin-top: 15px;
        }

        .sin-bordes-y {
            border-top: 1px solid #FFF !important;
            border-bottom: 1px solid #FFF !important;
        }

        .text-content-y {
            font-size: 11px;
        }
    </style>
</head>

<body>
    <div id="watermark">
        <img src="data:image/png;base64,{{ $data['logo'] }}" />
    </div>

    @if (isset($data['cancelado']) && $data['cancelado'])
        <div id="cancelado-watermark">
            <img src="data:image/png;base64,{{ $data['cancelado'] }}" />
        </div>
    @endif
    <table class="header-table">
        <tr>
            <td>
                @if ($data['transpor'])
                    <img class="logo-header" src="data:image/png;base64,{{ $data['transpor'] }}" alt="MinTransporte">
                @endif
            </td>
            <td>
                @if ($data['super'])
                    <img class="logo-header" src="data:image/png;base64,{{ $data['super'] }}" alt="Super">
                @endif
            </td>
            <td>
                @if ($data['logo'])
                    <img class="logo-header" src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo">
                @endif
            </td>
        </tr>
    </table>

    <div class="main-container">
        <table>
            <tr>
                <td class="label text-center" colspan="6" style="padding: 10px;">
                    <h3>FORMATO ÚNICO DE EXTRACTO DE CONTRATO DEL SERVICIO PÚBLICO DE <br> TRANSPORTE TERRESTRE
                        AUTOMOTOR ESPECIAL <br> N° {{ $fuec->request_number }}</h3>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="sin-bordes-y"><strong>RAZON SOCIAL DE LA EMPRESA:</strong>
                    <span class="text-content-y">{{ $fuec->company->business_name }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="sin-bordes-y"><strong>NIT:</strong>
                    <span class="text-content-y">{{ $fuec->company->document_number }} -
                        {{ $fuec->company->verification_digit }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="sin-bordes-y"><strong>CONTRATO No:</strong>
                    <span class="text-content-y">{{ $fuec->contract_number }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="sin-bordes-y"><strong>CONTRATANTE:</strong>
                    <span class="text-content-y">{{ $fuec->contractor->company_name }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="sin-bordes-y"><strong>NIT/CC:</strong>
                    <span class="text-content-y">{{ $fuec->contractor->document_number }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="sin-bordes-y"><strong>OBJETO CONTRATO:</strong>
                    <span class="text-content-y">{{ $fuec->objects_contract->description }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="6" class="sin-bordes-y"><strong>ORIGEN - DESTINO:</strong>
                    <span class="text-content-y">{{ $fuec->origin_route }} - {{ $fuec->destination_route }} Y RETORNO
                        AL PUNTO DE ORIGEN</span>
                </td>
            </tr>
            <tr>
                <td colspan="6"><strong>CONVENIO DE COLABORACIÓN:</strong>
                    <span
                        class="text-content-y">{{ $fuec->vehicle->business_collaboration_agreements->first()->contracting_entity_name ?? '' }}</span>
                </td>
            </tr>

            <tr>
                <td colspan="6" class="label text-center"><strong>VIGENCIA DEL CONTRATO</strong></td>
            </tr>
            <tr>
                <td class="label text-left" colspan="2">FECHA INICIAL</td>
                <td class="text-center">
                    <strong>DÍA</strong><br>{{ \Carbon\Carbon::parse($fuec->effective_date)->format('d') }}
                </td>
                <td class="text-center">
                    <strong>MES</strong><br>{{ \Carbon\Carbon::parse($fuec->effective_date)->format('m') }}
                </td>
                <td class="text-center" colspan="2">
                    <strong>AÑO</strong><br>{{ \Carbon\Carbon::parse($fuec->effective_date)->format('Y') }}
                </td>
            </tr>
            <tr>
                <td class="label text-left" colspan="2">FECHA VENCIMIENTO</td>
                <td class="text-center">
                    <strong>DÍA</strong><br>{{ \Carbon\Carbon::parse($fuec->expiration_date)->format('d') }}
                </td>
                <td class="text-center">
                    <strong>MES</strong><br>{{ \Carbon\Carbon::parse($fuec->expiration_date)->format('m') }}
                </td>
                <td class="text-center" colspan="2">
                    <strong>AÑO</strong><br>{{ \Carbon\Carbon::parse($fuec->expiration_date)->format('Y') }}
                </td>
            </tr>

            <tr>
                <td colspan="6" class="label text-center">CARACTERÍSTICAS DEL VEHÍCULO</td>
            </tr>
            <tr class="text-center">
                <td class="label">PLACA</td>
                <td class="label">MODELO</td>
                <td class="label" colspan="2">MARCA</td>
                <td class="label" colspan="2">CLASE</td>
            </tr>
            <tr class="text-center">
                <td>{{ $fuec->vehicle->vehicle_license_plate ?? 'N/A' }}</td>
                <td>{{ $fuec->vehicle->model ?? 'N/A' }}</td>
                <td colspan="2">{{ $fuec->vehicle->brand->description ?? 'N/A' }}</td>
                <td colspan="2">{{ $fuec->vehicle->vehicle_class->description ?? 'N/A' }}</td>
            </tr>
            <tr class="text-center">
                <td class="label" colspan="3">NÚMERO INTERNO</td>
                <td class="label" colspan="3">TARJETA DE OPERACIÓN</td>
            </tr>
            <tr class="text-center">
                <td colspan="3">{{ $fuec->vehicle->internal_number ?? 'N/A' }}</td>
                <td colspan="3">
                    {{ $fuec->vehicle->operation_cards->pluck('operating_card_number')->first() ?? 'N/A' }}</td>
            </tr>

            @foreach (['CONDUCTOR 1' => $fuec->main_conductor, 'CONDUCTOR 2' => $fuec->secondary_conductor, 'CONDUCTOR 3' => $fuec->tertiary_conductor] as $rol => $conductor)
                @if ($conductor)
                    <tr>
                        <td class="label">{{ strtoupper($rol) }}</td>
                        <td colspan="2" class="text-center"><strong>NOMBRES:</strong><br>
                            {{ $conductor->first_name }}
                            {{ $conductor->last_name }}</td>
                        <td class="text-center"><strong>CÉDULA:</strong><br> {{ $conductor->document_number }}</td>
                        <td class="text-center"><strong>LICENCIA:</strong><br>
                            {{ $conductor->driver_licenses->first()->number ?? 'N/A' }}</td>
                        <td class="text-center"><strong>VIGENCIA:</strong><br>
                            {{ $conductor->driver_licenses->first() ? \Carbon\Carbon::parse($conductor->driver_licenses->first()->expiration_date)->format('d/m/Y') : 'N/A' }}
                        </td>
                    </tr>
                @endif
            @endforeach

            <tr>
                <td class="label">RESPONSABLE DEL CONTRATANTE</td>
                <td colspan="2" class="text-center"><strong>NOMBRES:</strong><br>
                    {{ $fuec->contractor->responsible_name ?? 'N/A' }}</td>
                <td class="text-center"><strong>CÉDULA:</strong><br>
                    {{ $fuec->contractor->responsible_document ?? 'N/A' }}
                </td>
                <td class="text-center"><strong>TEL:</strong><br> {{ $fuec->contractor->responsible_phone ?? 'N/A' }}
                </td>
                <td class="text-center"><strong>DIRECCIÓN:</strong><br>
                    {{ $fuec->contractor->responsible_address ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td colspan="4" class="company-info" style="border-top: 1px solid #000; padding: 10px;">
                    <strong>{{ $fuec->company->business_name }}</strong><br>
                    NIT: {{ $fuec->company->document_number }} - {{ $fuec->company->verification_digit }}<br>
                    {{ $fuec->company->address }} | Tel: {{ $fuec->company->phone ?? '' }}
                </td>
                <td colspan="2" class="text-center" style="border-top: 1px solid #000;">
                    @if ($data['firma'])
                        <img class="firma" src="data:image/png;base64,{{ $data['firma'] }}" alt="Firma">
                    @endif
                    <div style="font-size: 8px; margin-top: 4px; border-top: 0.5px solid #000; padding-top: 2px;">
                        Firma Digital Ley 527 de 1999<br>Decreto 2364 de 2012
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="6" style="padding: 0;">
                    <div class="qr-section-container">
                        <div class="qr-box">
                            @if ($data['qrcode'])
                                <img class="qr" src="data:image/svg+xml;base64,{{ $data['qrcode'] }}"
                                    alt="QR">
                            @endif
                        </div>
                        <div class="instruction-box">
                            Para verificar este documento, por favor leer el código QR por medio de la cámara de su
                            dispositivo y/o la aplicación correspondiente.
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="legal-footer">
        <p style="margin: 1px 0;"><strong>Válido hasta:</strong>
            {{ \Carbon\Carbon::parse($fuec->expiration_date)->format('d / m / Y') }}</p>
        <p style="margin: 1px 0;">Formato según resolución 6652 del 27 de Diciembre del 2019</p>
        <p style="margin: 1px 0; font-style: italic;">Este documento ha sido generado mediante NOA Transportes,
        </p>
    </div>

    <div class="page-break"></div>

    <div class="main-container">
        <table>
            <tr>
                <td class="text-center instructivo-title" style="background-color: #f5f5f5;">
                    INSTRUCTIVO PARA DETERMINACIÓN DEL NÚMERO CONSECUTIVO DEL FUEC
                </td>
            </tr>
            <tr>
                <td style="padding: 15px; border-bottom: none; font-size: 10px;">
                    EL Formato Único de Extracto de Contrato “FUEC” estará constituido por los siguientes números:
                    <br><br>
                    <strong>a.</strong> Los tres primeras dígitos de izquierda a derecha corresponderán al código de la
                    Dirección Territorial que otorgó la habilitación de la empresa de Transporte de Servicio Especial.
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border-top: none; border-bottom: none;">
                    <table class="tabla-deptos">
                        <tr>
                            <td class="label">ANTIOQUIA – CHOCO</td>
                            <td class="text-center">305</td>
                            <td class="label">HUILA – CAQUETA</td>
                            <td class="text-center">441</td>
                        </tr>
                        <tr>
                            <td class="label">ATLÁNTICO</td>
                            <td class="text-center">208</td>
                            <td class="label">MAGDALENA</td>
                            <td class="text-center">247</td>
                        </tr>
                        <tr>
                            <td class="label">BOLÍVAR – SAN ANDRÉS Y PROV.</td>
                            <td class="text-center">213</td>
                            <td class="label">META – VAUPÉS – VICHADA</td>
                            <td class="text-center">550</td>
                        </tr>
                        <tr>
                            <td class="label">BOYACA – CASANARE</td>
                            <td class="text-center">415</td>
                            <td class="label">NARIÑO – PUTUMAYO</td>
                            <td class="text-center">352</td>
                        </tr>
                        <tr>
                            <td class="label">CALDAS</td>
                            <td class="text-center">317</td>
                            <td class="label">N/SANTANDER – ARAUCA</td>
                            <td class="text-center">454</td>
                        </tr>
                        <tr>
                            <td class="label">CAUCA</td>
                            <td class="text-center">305</td>
                            <td class="label">QUINDIO</td>
                            <td class="text-center">363</td>
                        </tr>
                        <tr>
                            <td class="label">CESAR</td>
                            <td class="text-center">220</td>
                            <td class="label">RISARALDA</td>
                            <td class="text-center">366</td>
                        </tr>
                        <tr>
                            <td class="label">CORDÓBA – SUCRE</td>
                            <td class="text-center">223</td>
                            <td class="label">SANTANDER</td>
                            <td class="text-center">468</td>
                        </tr>
                        <tr>
                            <td class="label">CUNDINAMARCA</td>
                            <td class="text-center">425</td>
                            <td class="label">TOLIMA</td>
                            <td class="text-center">473</td>
                        </tr>
                        <tr>
                            <td class="label">GUAJIRA</td>
                            <td class="text-center">241</td>
                            <td class="label">VALLE DEL CAUCA</td>
                            <td class="text-center">376</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="text-justify" style="padding: 15px; line-height: 1.5; border-top: none; font-size: 11px;">
                    <strong>b.</strong> Los cuatro dígitos siguientes señalarán el número de resolución mediante la cual
                    se otorgó la habilitación de la Empresa. En caso que la resolución no tenga estos dígitos, los
                    faltantes serán completados con ceros a la izquierda.<br><br>
                    <strong>c.</strong> Los siguientes dígitos, corresponderán a los dos últimos del año en que la
                    empresa fue habilitada.<br><br>
                    <strong>d.</strong> A continuación, cuatro dígitos que corresponderán al año en que se expide el
                    extracto de contrato.<br><br>
                    <strong>e.</strong> Posteriormente, cuatro dígitos que identifican el número de contrato. La
                    numeración debe ser consecutiva, establecida por cada empresa y continuará con la numeración dada a
                    los contratos de prestación de servicio celebrados para el transporte de estudiantes, empleados,
                    turistas, usuarios del servicio de salud y grupos específicos de usuarios, en vigencia de la
                    resolución 3068 de 2014.<br><br>
                    <strong>f.</strong> Finalmente, los cuatro últimos dígitos corresponderán al número consecutivo del
                    extracto de contrato que se expida para la ejecución de cada contrato. Se debe expedir un nuevo
                    extracto por vencimiento del plazo.
                </td>
            </tr>
            <tr>
                <td style="padding: 15px; background-color: #f9f9f9; font-size: 11px;">
                    <strong>EJEMPLO:</strong><br><br>
                    Empresa habilitada por la Dirección Territorial Cundinamarca en el año 2012, con resolución de
                    habilitación No. 0155, que expide el primer extracto del contrato en el año 2015, del contrato 255.
                    El número del Formato Único de Extracto de Contrato “FUEC” será: 425015512201502550001.
                </td>
            </tr>
        </table>
    </div>
    <table class="tabla-validacion">
        <tr>
            <td style="text-align:center; font-size:10px; color:#000000; padding:10px; border-bottom: 1px solid #000;">
                <strong>{{ $fuec->company->business_name }}</strong> Garantiza que el vehículo cumple con las políticas
                de la empresa.
            </td>
        </tr>
        <tr>
            <td style="text-align:center; font-size:10px; color:#000000; padding:10px;">
                Verifique la información de este Formato Único de Extracto de Contrato en: <br>
                <a href="https://{{ $fuec->company->web_page }}/validacion-de-fuec/"
                    style="color: blue; text-decoration: none;">Validacion de fuec</a><br><br>
                Finalmente ingrese el siguiente código: <strong>{{ $fuec->verification_code }}</strong>
            </td>
        </tr>
    </table>

    @if (isset($fuec->passengers) && count($fuec->passengers) > 0)
        <div class="page-break"></div>

        <table class="header-table">
            <tr>
                <td>
                    @if ($data['transpor'])
                        <img class="logo-header" src="data:image/png;base64,{{ $data['transpor'] }}"
                            alt="MinTransporte">
                    @endif
                </td>
                <td>
                    @if ($data['super'])
                        <img class="logo-header" src="data:image/png;base64,{{ $data['super'] }}" alt="Super">
                    @endif
                </td>
                <td>
                    @if ($data['logo'])
                        <img class="logo-header" src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo">
                    @endif
                </td>
            </tr>
        </table>
        <div class="main-container">
            <table>
                <tr>
                    <td class="label text-center" colspan="6" style="padding: 10px;">
                        <h3>FORMATO ÚNICO DE EXTRACTO DE CONTRATO DEL SERVICIO PÚBLICO DE <br> TRANSPORTE TERRESTRE
                            AUTOMOTOR ESPECIAL <br> N° {{ $fuec->request_number }}</h3>
                    </td>
                </tr>
                <tr>
                    <td class="label text-center" colspan="6" style="padding: 10px;">
                        RELACIÓN DE PASAJEROS
                    </td>
                </tr>
                @foreach ($fuec->passengers as $passenger)
                    <tr class="text-center">
                        <td colspan="2" class="label">N° PASAJERO {{ $passenger['counter'] }}</td>
                        <td colspan="2" class="label">{{ $passenger['document_number'] }}</td>
                        <td class="text-left label" colspan="2">{{ $passenger['first_and_last_name'] }}</td>
                    </tr>
                @endforeach

                <tr>
                    <td colspan="4" class="company-info" style="border-top: 1px solid #000; padding: 10px;">
                        <strong>{{ $fuec->company->business_name }}</strong><br>
                        NIT: {{ $fuec->company->document_number }} - {{ $fuec->company->verification_digit }}<br>
                        {{ $fuec->company->address }} | Tel: {{ $fuec->company->phone ?? '' }}
                    </td>
                    <td colspan="2" class="text-center" style="border-top: 1px solid #000;">
                        @if (isset($data['firma']) && $data['firma'])
                            <img class="firma" src="data:image/jpeg;base64,{{ $data['firma'] }}" alt="Firma">
                        @endif
                        <div style="font-size: 8px; margin-top: 4px; border-top: 0.5px solid #000; padding-top: 2px;">
                            Firma Digital Ley 527 de 1999<br>Decreto 2364 de 2012
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="6" style="padding: 0;">
                        <div class="qr-section-container">
                            <div class="qr-box">
                                @if (isset($data['qrcode']) && $data['qrcode'])
                                    <img class="qr" src="data:image/svg+xml;base64,{{ $data['qrcode'] }}"
                                        alt="QR">
                                @endif
                            </div>
                            <div class="instruction-box">
                                Para verificar este documento, por favor leer el código QR por medio de la cámara de su
                                dispositivo y/o la aplicación correspondiente.
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="legal-footer">
            <p style="margin: 1px 0;"><strong>Válido hasta:</strong>
                {{ \Carbon\Carbon::parse($fuec->expiration_date)->format('d / m / Y') }}</p>
            <p style="margin: 1px 0;">Formato según resolución 6652 del 27 de Diciembre del 2019</p>
            <p style="margin: 1px 0; font-style: italic;">Este documento ha sido generado mediante NOA Transportes,
            </p>
        </div>
    @endif

</body>

</html>
