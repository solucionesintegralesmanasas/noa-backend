<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Documento' }}</title>
    @php($pageOrientation = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait')
    <style>
        /* Estándar de color de fondos de tabla para todos los PDF del layout.
           Toma la paleta de la Inspección Preoperacional: bandas slate claro con
           texto navy, encabezados de columna en gris tenue y zonas a mano en blanco.
           Basado en la Inspección Preoperacional Diaria de Vehículos (SGFT04). */
        :root {
            --pdf-banda: #E2E8F0;
            /* Fondo de bandas y encabezados de sección. */
            --pdf-banda-texto: #0F172A;
            /* Texto sobre banda: navy de alto contraste. */
            --pdf-encabezado: #EEF2F6;
            /* Fondo de encabezados de columna (th). */
            --pdf-tenue: #F8FAFC;
            /* Fondo de bloques informativos y notas. */
            --pdf-alerta: #FEE2E2;
            --pdf-alerta-texto: #991B1B;
            --pdf-alerta-fila: #FEF2F2;
            /* Fila con hallazgo. */
            --pdf-diligenciable: #FAFAFA;
            /* Zonas para llenar a mano o con foto. */
            --pdf-borde: #555555;
        }

        @page {
            size: letter {{ $pageOrientation }};
            margin: 0cm 0cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin-top: 0.8cm;
            margin-bottom: 0.8cm;
            margin-left: 0.8cm;
            margin-right: 0.8cm;
            padding: 0;
            color: #000;
            background-color: #fff;
        }

        body.with-letterhead {
            margin-top: 0.6cm;
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
            opacity: 0.4;
        }

        /* Membrete en flujo con alto acotado: un fondo fixed a página completa
           puede generar páginas extra en DomPDF. En flujo nunca rompe el paginado. */
        #letterhead-banner {
            text-align: center;
            margin-bottom: 6px;
        }

        #letterhead-banner img {
            max-width: 100%;
            max-height: 2.5cm;
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
            vertical-align: middle;
            word-wrap: break-word;
        }

        .label {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-justify {
            text-align: justify;
        }

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
            display: block;
            margin: 0 auto;
        }

        .company-info {
            font-size: 10.5px;
            text-align: center;
            line-height: 1.4;
        }

        .legal-footer {
            font-size: 10px;
            text-align: center;
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

        @stack('styles')
    </style>
    @stack('head')
</head>

{{-- NOTA: pdf.fuec NO extiende este layout a propósito; queda congelado como referencia visual. --}}
<body class="{{ !empty($letterhead ?? null) ? 'with-letterhead' : '' }}">
    {{-- NOTA: se usa $__env->hasSection() (existe la sección aunque esté vacía) y no
        @hasSection, que en esta versión equivale a "sección con contenido no vacío"
        y haría perder un override intencionalmente vacío (p. ej. sin marca de agua). --}}
    @if ($__env->hasSection('watermarks'))
        @yield('watermarks')
    @else
        @include('pdf.layouts.partials.watermarks', [
            'data' => $data ?? [],
            'letterhead' => $letterhead ?? null,
            'logo_fondo' => $logo_fondo ?? null,
            'ocultar_marca' => $ocultar_marca ?? false,
        ])
    @endif

    {{-- La sección propia siempre se muestra (es contenido del documento, p. ej.
        código/versión); solo el encabezado default de 3 logos se suprime con membrete. --}}
    @if ($__env->hasSection('header'))
        @yield('header')
    @elseif (empty($letterhead ?? null) && empty($hideHeader ?? false))
        @include('pdf.layouts.partials.header-logos', ['data' => $data ?? []])
    @endif

    @yield('content')

    @stack('after-content')
</body>

</html>
