@include('pdf.standard.styles', ['orientation' => 'portrait', 'pdfMargen' => 'cartas'])
@php
    $__esHojaMembretada = ! empty($es_hoja_membretada)
        || \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
    $__ocultarMarca = ! empty($ocultar_marca);
    $__sinMarcaVisual = $__esHojaMembretada || $__ocultarMarca;
@endphp
<style>
    @page {
        size: letter portrait;
        margin: {{ $__esHojaMembretada ? '34mm 16mm 34mm 16mm' : '18mm 18mm 18mm 18mm' }};
    }

    body {
        font-family: 'Inter', DejaVu Sans, sans-serif;
        font-size: 9.2pt;
        line-height: 1.38;
        color: #111827;
        margin: 0;
        padding: 0;
    }

    #letterhead-page-bg {
        position: fixed;
        top: -34mm;
        left: -16mm;
        width: 215.9mm;
        height: 279.4mm;
        z-index: -1000;
    }

    #letterhead-page-bg img {
        width: 215.9mm;
        height: 279.4mm;
        display: block;
    }

    #watermark {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 95mm;
        text-align: center;
        opacity: 0.08;
        z-index: -1000;
    }

    #watermark img {
        max-width: 95mm;
        max-height: 95mm;
    }

    .encabezado {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 1.5px solid var(--pdf-borde);
        padding-bottom: 4px;
        margin-bottom: 9px;
    }

    .encabezado td {
        vertical-align: middle;
        border: none;
        padding: 0 0 4px 0;
    }

    .encabezado .marca {
        font-size: 11pt;
        font-weight: 700;
        line-height: 1.15;
        color: #000000;
    }

    .encabezado .submarca {
        font-size: 7.8pt;
        color: #374151;
        line-height: 1.2;
    }

    .logo {
        max-height: 42px;
        max-width: 145px;
    }

    .titulo {
        text-align: center;
        font-size: 11pt;
        font-weight: 700;
        text-transform: uppercase;
        color: #000000;
        margin: 6px 0 9px;
        letter-spacing: 0.3px;
    }

    .destinatario {
        margin-bottom: 8px;
        font-size: 9pt;
        color: #111827;
    }

    .destinatario p {
        margin: 0 0 1.5px;
    }

    .referencia {
        margin: 6px 0 8px;
        font-size: 8.8pt;
        color: #111827;
    }

    .referencia p {
        margin: 0 0 2px;
    }

    .cuerpo {
        text-align: justify;
        margin: 0 0 7px;
        font-size: 9.1pt;
        line-height: 1.38;
        color: #111827;
    }

    .datos {
        width: 100%;
        border-collapse: collapse;
        margin: 6px 0 9px;
        border: 1px solid var(--pdf-borde);
        background-color: #ffffff;
    }

    .datos th,
    .datos td {
        border: 0.8px solid var(--pdf-borde-interno);
        padding: 3.5px 7px;
        text-align: left;
        font-size: 8.5pt;
        line-height: 1.28;
        color: #111827;
    }

    .datos th {
        background-color: var(--pdf-encabezado);
        font-weight: 700;
        color: #000000;
        width: 32%;
    }

    .datos thead th {
        background-color: var(--pdf-banda);
        border-bottom: 1px solid var(--pdf-borde);
        text-transform: uppercase;
        font-size: 7.8pt;
    }

    .clausula {
        text-align: justify;
        margin: 0 0 7px;
        font-size: 9.2pt;
        line-height: 1.4;
        color: #111827;
    }

    .clausula strong {
        text-transform: uppercase;
        color: #000000;
    }

    .firmas {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
        page-break-inside: avoid;
    }

    .firmas td {
        width: 50%;
        text-align: center;
        border: none;
        padding: 0 10px;
        vertical-align: bottom;
        font-size: 8.5pt;
        color: #111827;
    }

    .espacio-firma {
        height: 42px;
        line-height: 42px;
        text-align: center;
    }

    .espacio-firma img {
        max-height: 38px;
        max-width: 150px;
        vertical-align: bottom;
    }

    .espacio-firma .firma-escrita {
        display: inline-block;
        line-height: 42px;
        vertical-align: bottom;
    }

    .firma-escrita {
        font-family: 'Inter', DejaVu Sans, sans-serif;
        font-size: 12pt;
        font-style: italic;
        font-weight: 600;
        line-height: 40px;
        max-width: 190px;
        overflow: hidden;
        white-space: nowrap;
        color: #1f2937;
    }

    .renglon-firma {
        border-top: 1px solid var(--pdf-borde);
        padding-top: 4px;
        line-height: 1.3;
    }

    .pie {
        position: fixed;
        bottom: {{ $__esHojaMembretada ? '0mm' : '-9mm' }};
        left: 0;
        right: 0;
        text-align: center;
        font-size: 7.4pt;
        color: #4b5563;
        border-top: 0.8px solid var(--pdf-borde-interno);
        padding-top: 3px;
    }
</style>
