@php
    $nombreEmpresa = (string) (data_get($company ?? null, 'business_name') ?: 'NOMBRE DE LA EMPRESA');
    $nitEmpresa = trim((string) (data_get($company ?? null, 'document_number') ?? ''));
    $siglasEmpresa = \App\Services\Pdf\PdfService::siglasEmpresa($nombreEmpresa);
    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
    $sinMarcaVisual = ! empty($letterhead ?? null) || ! empty($ocultar_marca ?? false);
    $logoDataUrl = (! $sinMarcaVisual && ! empty($images['logo'] ?? null))
        ? 'data:image/png;base64,' . $images['logo']
        : null;

    $items4 = [
        'ESTADO GENERAL DE LA CARROCERIA',
        'VIDRIOS PARABRISAS',
        'LIMPIA PARABRISAS',
        'VIDRIOS LATERALES',
        'ESPEJOS COMPLET',
        'RETROVISOR LATERALES',
        "STOP'S",
        "TERCER STOP'S",
        'LUCES DELANTERAS BAJAS',
        'LUCES DELANTERAS ALTAS',
        'LUCES DELANTERAS SEÑALES DIRECCIONALES',
        'LUCES TRASERAS',
        'LUCES TRASERAS SEÑALES DIRECCIONALES',
        'LUCES DE REVERSA',
        'LUCES DE ESTACIONAMIENTO',
        'PITO',
        'PITO DE REVERSA',
        'CARPA DE LONA',
        'ESTADO GENERAL DE LAS LLANTAS',
        'ESTADO DE ELEMENTOS DE SUSPENSION',
        'SALIDA DE EMERGENCIA (BUSETA)',
        'ESTADO DE FRENO',
        'ESTADO DE FRENO DE SEGURIDAD',
        'NIVEL Y ESTADO ACEITE DE MOTOR',
        'NIVEL Y ESTADO DE LIQUIDO REFRIGERANTE',
        'NIVEL Y ESTADO LIQUIDO DE FRENOS',
        'NIVEL Y ESTADO DE FLUIDO HIDR DE DIRECCION',
        'NIVEL LIQUIDO DE BATERIA',
        'NIVEL LIQUIDO LIMPIABRISAS',
        'ESTADO DE CORREAS',
        'ESTADO DE MANGUERAS',
        'CABLES DE BATERIA',
        'ANTENA DE RADIO',
    ];
    $mitad4 = (int) ceil(count($items4) / 2);
    $items4Izq = array_slice($items4, 0, $mitad4);
    $items4Der = array_slice($items4, $mitad4);

    $items5 = [
        'CINTURONES DE SEGURIDAD',
        'LUZ INTERNA',
        'APOYA CABEZAS',
        'INEXISTENCIA DE OBJETOS SUELTOS',
        'PARASOLES',
        'TABLERO DE INSTRUMENTOS',
        'CHAPAS Y MANIJAS',
        'ESTADO DE LAS PUERTAS',
        'AIRBAG',
        'ASIENTOS DELANTEROS Y TRASEROS',
        'TAPETES',
        'TIMON',
        'PANELES DE CONTROL',
        'PALANCA DE CAMBIOS',
        'RADIO',
    ];

    $items6 = [
        'GANCHO DE REMOLQUE',
        'BARRA ANTI-VUELCO',
        'PALA ANTICHISPA',
        'TACOS',
        '10 MTS DE MANILA',
        'CRUCETA',
        'GATO',
        'LINTERNA CON PILAS',
        'KIT DE HERRAMIENTAS',
        'CHALECO REFLECTIVO',
        'CONOS Y/O TRIANGULOS REFLECTIVOS',
        'EXTINTOR',
    ];

    $items7 = [
        'VENDA ELASTICA',
        'TIJERAS',
        'MANTA TERMICA (1 UNIDAD)',
        'LACTATO DE RINGER (MINIMO 1 BOLSA)',
        'INMOVILIZADORES PARA CUELLO Y EXTREMIDADES',
        'GUANTES DESECHABLES (MINIMO 5 PARES)',
        'GASA (MINIMO 5 PAQUETES)',
        'ESPARADRAPO (MINIMO 1 ROLLO)',
        'MICROPORE (MINIMO 1 ROLLO)',
        'ISODINE SOLUCION Y ESPUMA',
        'APOSITO OCULAR (MINIMO 5 UNIDADES)',
        'BAJALENGUAS (MINIMO 4 UNIDADES)',
        'AGUA DESTILADA (MINIMO 2 BOLSAS)',
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Acta de Entrega de Vehículos</title>
    @include('pdf.standard.styles', ['orientation' => 'portrait', 'pdfMargen' => 'denso'])
    <style>
        @page {
            size: letter portrait;
            margin: {{ $esHojaMembretadaCompleta ? '33mm 10mm 32mm 10mm' : '8mm 10mm 8mm 8mm' }};
        }

        body {
            background-color: {{ $esHojaMembretadaCompleta ? 'transparent' : '#ffffff' }};
            font-size: 7.2pt;
            line-height: 1.2;
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
            opacity: 0.1;
        }

        #letterhead-page-bg {
            position: fixed;
            top: -33mm;
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

        #letterhead-banner {
            text-align: center;
            margin-bottom: 5px;
        }

        #letterhead-banner img {
            width: 100%;
            height: auto;
            max-height: 2.2cm;
            display: block;
        }

        .header-table {
            margin-bottom: 5px;
        }

        .meta-table td {
            padding: 3px 6px !important;
            font-size: 6.6pt;
        }

        .section-banner {
            margin-top: 5px;
        }

        .info-table {
            margin-bottom: 4px;
        }

        .info-table td {
            padding: 2.6px 5px;
            font-size: 6.9pt;
            line-height: 1.2;
        }

        .info-lbl {
            font-size: 6.2pt;
        }

        /* Layout en dos columnas equilibradas (sin celdas gigantes que partan DomPDF). */
        .cols-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 4px;
            margin-bottom: 2px;
        }

        .cols-grid > tbody > tr > td,
        .cols-grid > tr > td {
            width: 50%;
            border: none;
            vertical-align: top;
            padding: 0;
        }

        .cols-grid > tbody > tr > td:first-child,
        .cols-grid > tr > td:first-child {
            padding-right: 4px;
        }

        .cols-grid > tbody > tr > td:last-child,
        .cols-grid > tr > td:last-child {
            padding-left: 4px;
        }

        .chk-table {
            width: 100%;
            border: 1px solid var(--pdf-borde);
            border-top: none;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .chk-table th {
            background-color: var(--pdf-encabezado);
            color: #000000;
            font-size: 6.1pt;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2.4px 3px;
            border: 0.7px solid var(--pdf-borde-interno);
            border-bottom: 1px solid var(--pdf-borde);
            text-align: center;
        }

        .chk-table th.elem-th {
            text-align: left;
            padding-left: 5px;
        }

        .chk-table td {
            border: 0.7px solid var(--pdf-borde-interno);
            padding: 2.4px 4px;
            font-size: 6.5pt;
            line-height: 1.18;
            color: #111827;
        }

        .chk-table tr:nth-child(even) td {
            background-color: #fafafa;
        }

        .chk-box-col {
            width: 9%;
            text-align: center;
            padding: 1.5px 1px !important;
        }

        .chk-obs-col {
            width: 18%;
        }

        .box {
            display: inline-block;
            width: 8px;
            height: 8px;
            border: 0.9px solid #1f2937;
            background-color: #ffffff;
        }

        .obs-lines-box {
            border: 1px solid var(--pdf-borde);
            border-top: none;
            background-color: #ffffff;
            padding: 4px 7px 6px 7px;
            margin-bottom: 5px;
        }

        .obs-line-row {
            height: 18px;
            border-bottom: 0.7px dashed #9ca3af;
        }

        .obs-line-row:last-child {
            border-bottom: none;
        }

        .salto-pagina {
            page-break-before: always;
        }

        .firmas-acta {
            width: 100%;
            border: 1px solid var(--pdf-borde);
            border-collapse: collapse;
            margin-top: 6px;
            page-break-inside: avoid;
        }

        .firmas-acta td {
            width: 50%;
            padding: 6px 18px 8px 18px;
            vertical-align: bottom;
            text-align: center;
            border-right: 0.8px solid var(--pdf-borde);
            background-color: #ffffff;
        }

        .firmas-acta td:last-child {
            border-right: none;
        }

        .firma-espacio {
            height: 36px;
            vertical-align: bottom;
            text-align: center;
        }

        .firma-espacio img {
            max-height: 34px;
            max-width: 150px;
        }
    </style>
</head>
<body class="{{ ! empty($letterhead ?? null) ? 'with-letterhead' : '' }}">

    @if (! empty($letterhead ?? null) && $esHojaMembretadaCompleta)
        <div id="letterhead-page-bg">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @elseif (! empty($letterhead ?? null))
        <div id="letterhead-banner">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @endif

    @if (empty($ocultar_marca ?? false) && empty($letterhead ?? null) && ! empty($logo_fondo ?? null))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $logo_fondo }}" alt="Marca de agua" />
        </div>
    @endif

    {{-- ============ PÁGINA 1: CABECERA + DATOS DE IDENTIFICACIÓN + ESTADO GENERAL ============ --}}
    @include('pdf.standard.header', [
        'logo_data_url' => $logoDataUrl,
        'ocultar_celda_logo' => $sinMarcaVisual,
        'empresa_siglas' => $siglasEmpresa,
        'empresa_transportadora' => $nombreEmpresa,
        'empresa_detalle' => $nitEmpresa !== '' ? 'NIT ' . $nitEmpresa : 'Sistema de Gestión de Calidad',
        'titulo_documento' => 'ACTA DE ENTREGA Y RECIBO DE VEHÍCULOS',
        'subtitulo_documento' => 'PLAN ESTRATÉGICO DE SEGURIDAD VIAL (PESV) · CONTROL DE FLOTA',
        'meta_sgc' => [
            'CÓDIGO' => 'M-F-059',
            'VERSIÓN' => '001',
            'FECHA' => $generation_date ?? date('d/m/Y'),
        ],
    ])

    {{-- 1. INFORMACIÓN DEL VEHÍCULO --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>1. Información del Vehículo</td>
        </tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 12%;">Placa:</td>
            <td class="info-val" style="width: 13%; font-weight: 700;">{{ $vehiculo->placa ?? '—' }}</td>
            <td class="info-lbl" style="width: 11%;">Marca:</td>
            <td class="info-val" style="width: 14%;">{{ $vehiculo->marca ?? '—' }}</td>
            <td class="info-lbl" style="width: 12%;">N° Chasis:</td>
            <td class="info-val" style="width: 17%;">{{ $vehiculo->chasis ?? '—' }}</td>
            <td class="info-lbl" style="width: 10%;">Modelo:</td>
            <td class="info-val" style="width: 11%;">{{ $vehiculo->modelo ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Clase:</td>
            <td class="info-val">{{ $vehiculo->vehiculo ?? '—' }}</td>
            <td class="info-lbl">Combust.:</td>
            <td class="info-val">{{ $vehiculo->combustible ?? '—' }}</td>
            <td class="info-lbl">Lic. Tránsito:</td>
            <td class="info-val">{{ $vehiculo->licencia ?? '—' }}</td>
            <td class="info-lbl">Cilindraje:</td>
            <td class="info-val">{{ $vehiculo->cilindraje ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Servicio:</td>
            <td class="info-val">{{ $vehiculo->clase_servicio ?? '—' }}</td>
            <td class="info-lbl">N° Motor:</td>
            <td class="info-val">{{ $vehiculo->motor ?? '—' }}</td>
            <td class="info-lbl">Carrocería:</td>
            <td class="info-val">{{ $vehiculo->carroceria ?? '—' }}</td>
            <td class="info-lbl">RTM:</td>
            <td class="info-val">{{ $vehiculo->tecno ?? '—' }}</td>
        </tr>
    </table>

    {{-- 2 y 3. PROPIETARIO Y ENTREGA LADO A LADO --}}
    <table class="cols-grid" cellpadding="0" cellspacing="0" style="margin-top: 2px;">
        <tr>
            <td>
                <table class="section-banner" cellpadding="0" cellspacing="0" style="margin-top: 2px;">
                    <tr>
                        <td>2. Información del Propietario</td>
                    </tr>
                </table>
                <table class="info-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="info-lbl" style="width: 34%;">Nombre:</td>
                        <td class="info-val" colspan="3">{{ $propietario->nombre ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="info-lbl">Cédula / NIT:</td>
                        <td class="info-val" style="width: 26%;">{{ $propietario->cedula ?? '—' }}</td>
                        <td class="info-lbl" style="width: 16%;">Lugar:</td>
                        <td class="info-val" style="width: 24%;">{{ $propietario->lugar ?? '—' }}</td>
                    </tr>
                </table>
            </td>
            <td>
                <table class="section-banner" cellpadding="0" cellspacing="0" style="margin-top: 2px;">
                    <tr>
                        <td>3. Información de la Entrega</td>
                    </tr>
                </table>
                <table class="info-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="info-lbl" style="width: 22%;">Fecha:</td>
                        <td class="info-val" style="width: 22%;">{{ $entrega->fecha ?? '____/____/____' }}</td>
                        <td class="info-lbl" style="width: 16%;">Cargo:</td>
                        <td class="info-val" style="width: 40%;">{{ $entrega->cargo ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="info-lbl">Entrega:</td>
                        <td class="info-val">{{ $entrega->nombre_entrega ?? '—' }}</td>
                        <td class="info-lbl">Recibe:</td>
                        <td class="info-val">{{ $entrega->delegado ?? '—' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- 4. ESTADO GENERAL DEL VEHÍCULO (33 ítems repartidos en 2 columnas paralelas = 17 + 16 filas) --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>4. Estado General del Vehículo (Inspección Exterior, Luces y Fluidos)</td>
        </tr>
    </table>
    <table class="cols-grid" cellpadding="0" cellspacing="0" style="margin-top: 0;">
        <tr>
            <td>
                <table class="chk-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="elem-th" style="width: 55%;">Elemento</th>
                            <th class="chk-box-col">B</th>
                            <th class="chk-box-col">M</th>
                            <th class="chk-box-col">N/A</th>
                            <th class="chk-obs-col">Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items4Izq as $item)
                            <tr>
                                <td>{{ $item }}</td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-obs-col">&nbsp;</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td>
                <table class="chk-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="elem-th" style="width: 55%;">Elemento</th>
                            <th class="chk-box-col">B</th>
                            <th class="chk-box-col">M</th>
                            <th class="chk-box-col">N/A</th>
                            <th class="chk-obs-col">Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items4Der as $item)
                            <tr>
                                <td>{{ $item }}</td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-obs-col">&nbsp;</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- ============ PÁGINA 2: CABINA, EQUIPO DE CONTROL, BOTIQUÍN, OBSERVACIONES Y FIRMAS ============ --}}
    <div class="salto-pagina"></div>

    <table class="cols-grid" cellpadding="0" cellspacing="0" style="margin-top: 0;">
        <tr>
            <td>
                {{-- 5. INSPECCIÓN DE CABINA --}}
                <table class="section-banner" cellpadding="0" cellspacing="0" style="margin-top: 0;">
                    <tr>
                        <td>5. Inspección de Cabina</td>
                    </tr>
                </table>
                <table class="chk-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="elem-th" style="width: 55%;">Elemento</th>
                            <th class="chk-box-col">B</th>
                            <th class="chk-box-col">M</th>
                            <th class="chk-box-col">N/A</th>
                            <th class="chk-obs-col">Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items5 as $item)
                            <tr>
                                <td>{{ $item }}</td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-obs-col">&nbsp;</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- 6. EQUIPO / CONTROL --}}
                <table class="section-banner" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>6. Equipo / Control y Seguridad Vial</td>
                    </tr>
                </table>
                <table class="chk-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="elem-th" style="width: 55%;">Elemento</th>
                            <th class="chk-box-col">B</th>
                            <th class="chk-box-col">M</th>
                            <th class="chk-box-col">N/A</th>
                            <th class="chk-obs-col">Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items6 as $item)
                            <tr>
                                <td>{{ $item }}</td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-obs-col">&nbsp;</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td>
                {{-- 7. EQUIPO / ELEMENTO DEL BOTIQUÍN --}}
                <table class="section-banner" cellpadding="0" cellspacing="0" style="margin-top: 0;">
                    <tr>
                        <td>7. Equipo / Elemento del Botiquín</td>
                    </tr>
                </table>
                <table class="chk-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="elem-th" style="width: 55%;">Elemento</th>
                            <th class="chk-box-col">B</th>
                            <th class="chk-box-col">M</th>
                            <th class="chk-box-col">N/A</th>
                            <th class="chk-obs-col">Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items7 as $item)
                            <tr>
                                <td>{{ $item }}</td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-box-col"><span class="box"></span></td>
                                <td class="chk-obs-col">&nbsp;</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- 8. OBSERVACIONES GENERALES --}}
                <table class="section-banner" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>8. Observaciones Generales (Pintura, Golpes, Rayones)</td>
                    </tr>
                </table>
                <div class="obs-lines-box">
                    @for ($r = 0; $r < 12; $r++)
                        <div class="obs-line-row"></div>
                    @endfor
                </div>
            </td>
        </tr>
    </table>

    {{-- ============ FIRMAS DE CONFORMIDAD ============ --}}
    <table class="firmas-acta" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="firma-espacio">&nbsp;</div>
                <div class="sig-line">
                    <div class="sig-name">{{ $entrega->delegado ?? 'DELEGADO DE LA EMPRESA' }}</div>
                    <div class="sig-role">Firma de quien recibe · Control de Flota</div>
                </div>
            </td>
            <td>
                <div class="firma-espacio">
                    @if (! empty($images['firma'] ?? null))
                        <img src="data:image/png;base64,{{ $images['firma'] }}" alt="Firma">
                    @endif
                </div>
                <div class="sig-line">
                    <div class="sig-name">{{ $entrega->nombre_entrega ?? 'QUIEN ENTREGA EL VEHÍCULO' }}</div>
                    <div class="sig-role">Firma de quien entrega · {{ $entrega->cargo ?? 'Responsable' }}</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
