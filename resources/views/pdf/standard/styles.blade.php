{{-- Base estándar de impresión PDF (Prototipo A aprobado, 2026-11-17).
     Parámetros: $orientation ('portrait'|'landscape'), $pdfMargen ('denso' por defecto | 'cartas').
     Reglas: sin '* { margin }' (rompe @page en DomPDF), solo tablas, sin flex/grid, unidades en pt. --}}
@php
    $__orient = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
    $__margen = ($pdfMargen ?? 'denso') === 'cartas' ? '18mm 20mm 18mm 20mm' : '8mm 10mm 8mm 8mm';
    $__fontDir = str_replace('\\', '/', resource_path('fonts/inter'));
@endphp
<style>
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $__fontDir }}/Inter-Regular.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 500;
            src: url('{{ $__fontDir }}/Inter-Medium.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 600;
            src: url('{{ $__fontDir }}/Inter-SemiBold.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 700;
            src: url('{{ $__fontDir }}/Inter-Bold.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: italic;
            font-weight: 400;
            src: url('{{ $__fontDir }}/Inter-Italic.ttf') format('truetype');
        }

        :root {
            --pdf-banda: #d8d8d8;
            --pdf-banda-texto: #000000;
            --pdf-acento: #262626;
            --pdf-encabezado: #f0f0f0;
            --pdf-tenue: #f7f7f7;
            --pdf-borde: #1f2937;
            --pdf-borde-interno: #9ca3af;
        }

        @page {
            size: letter {{ $__orient }};
            margin: {{ $__margen }};
        }

        * {
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: 400;
            font-size: 7.5pt;
            line-height: 1.25;
            color: #111827;
            background-color: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td, th {
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* 1. Encabezado Institucional SGC */
        .header-table {
            border: 1.3px solid #0f172a;
            margin-bottom: 4px;
        }

        .header-table td {
            border: 1px solid #0f172a;
            padding: 3px 6px;
        }

        .header-logo-cell {
            width: 19%;
            text-align: center;
            background-color: #ffffff;
            padding: 2px 4px !important;
        }

        .header-logo-img {
            max-height: 54px;
            max-width: 122px;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .logo-monogram {
            font-weight: bold;
            font-size: 13pt;
            color: var(--pdf-acento);
            line-height: 1;
            letter-spacing: 0.5px;
        }

        .logo-caption {
            font-size: 5.5pt;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .header-title-cell {
            width: 55%;
            text-align: center;
            padding: 4px 8px;
        }

        .company-name {
            font-size: 8.4pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .company-sub {
            font-size: 6.5pt;
            font-weight: 500;
            color: #1f2937;
            margin-top: 1px;
        }

        .doc-main-title {
            font-size: 9.2pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            margin-top: 3px;
            letter-spacing: 0.25px;
        }

        .doc-sub-title {
            font-size: 6.2pt;
            font-weight: 600;
            color: #262626;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .header-meta-cell {
            width: 26%;
            padding: 0 !important;
            vertical-align: middle;
        }

        .meta-table td {
            border-top: none !important;
            border-left: none !important;
            border-right: none !important;
            border-bottom: 1px solid #0f172a !important;
            padding: 5px 6px !important;
            font-size: 6.6pt;
            line-height: 1.15;
            vertical-align: middle !important;
        }

        .meta-table tr:first-child td {
            border-top: none !important;
        }

        .meta-table tr:last-child td {
            border-bottom: none !important;
        }

        .meta-label {
            width: 44%;
            background-color: var(--pdf-tenue);
            border-right: 1px solid #0f172a !important;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            text-align: left;
        }

        .meta-val {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            color: #000000;
            text-align: center;
        }

        /* 2. Banda de sección */
        .section-banner {
            border: 1px solid var(--pdf-borde);
            border-left: 3.5px solid #111827;
            background-color: var(--pdf-banda);
            margin-top: 4px;
            margin-bottom: 0;
        }

        .section-banner td {
            padding: 2.5px 6px;
            font-size: 7pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.25px;
        }

        /* 3. Ficha de contexto */
        .info-table {
            border: 1px solid #1f2937;
            border-top: none;
            margin-bottom: 5px;
        }

        .info-table td {
            border: 0.7px solid #9ca3af;
            padding: 2.6px 5px;
            font-size: 7.1pt;
            line-height: 1.25;
            vertical-align: middle;
        }

        .info-lbl {
            background-color: var(--pdf-tenue);
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            font-size: 6.4pt;
        }

        .info-val {
            color: #000000;
            font-weight: 500;
        }

        .placa-inline-tbl {
            width: auto !important;
            table-layout: auto !important;
            border-collapse: collapse;
            margin: 0;
        }

        .placa-inline-tbl td {
            border: none;
            padding: 0;
            vertical-align: middle !important;
            line-height: 1.1;
        }

        .placa-inline-tbl td.placa-badge {
            background-color: #fef08a;
            color: #854d0e;
            border: 0.6pt solid #ca8a04 !important;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            font-size: 7.1pt;
            padding: 1.2px 5px;
            white-space: nowrap;
        }

        .placa-inline-tbl td.vehiculo-sep {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            font-size: 8pt;
            color: #4b5563;
            padding: 0 4px;
            text-align: center;
            white-space: nowrap;
        }

        .placa-inline-tbl td.vehiculo-interno {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            font-size: 7pt;
            color: #111827;
            white-space: nowrap;
        }

        /* 4. Tabla de registros */
        .main-table {
            border: 1.2px solid #1f2937;
            margin-bottom: 5px;
            page-break-inside: auto;
        }

        .main-table thead {
            display: table-header-group;
        }

        .main-table tr {
            page-break-inside: avoid;
        }

        .main-table thead th {
            background-color: var(--pdf-encabezado);
            color: #000000;
            border: 0.7px solid var(--pdf-borde-interno);
            border-bottom: 1.2px solid var(--pdf-borde);
            padding: 3.2px 3px;
            font-size: 6.6pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }

        .main-table tbody td {
            border: 0.7px solid #9ca3af;
            padding: 3px 4px;
            font-size: 7pt;
            color: #000000;
            font-weight: 500;
        }

        .main-table tbody tr:nth-child(even) td {
            background-color: #fafafa;
        }

        .mono {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-weight: 500;
            color: #000000;
        }

        .num {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            text-align: right;
            font-weight: bold;
            color: #000000;
        }

        .txt-center {
            text-align: center;
        }

        .tag {
            display: inline-block;
            padding: 1px 4px;
            font-size: 5.8pt;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 2px;
        }

        .tag-ok {
            background-color: #dcfce7;
            color: #166534;
            border: 0.6px solid #86efac;
        }

        .tag-warn {
            background-color: #fef3c7;
            color: #92400e;
            border: 0.6px solid #fcd34d;
        }

        .tag-alert {
            background-color: #fee2e2;
            color: #991b1b;
            border: 0.6px solid #fca5a5;
        }

        /* 5. Banda de alerta */
        .banda-alerta {
            border: 0.8px solid #b45309;
            border-left: 3.5px solid #b45309;
            background-color: #fffbeb;
            color: #78350f;
            padding: 3.5px 7px;
            font-size: 6.7pt;
            margin-bottom: 4px;
            page-break-inside: avoid;
        }

        /* 6. Cierre indivisible */
        .cierre {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .cierre > tbody > tr > td {
            padding: 0;
            border: none;
        }

        .totals-table {
            border: 1px solid #0f172a;
            background-color: var(--pdf-encabezado);
            margin-bottom: 4px;
        }

        .totals-table td {
            border: 0.7px solid var(--pdf-borde-interno);
            padding: 3.5px 6px;
            font-size: 7.2pt;
            text-align: center;
        }

        .obs-table {
            border: 1px solid #0f172a;
            margin-bottom: 4px;
        }

        .obs-head {
            background-color: var(--pdf-banda);
            border-left: 4px solid var(--pdf-borde);
            border-bottom: 0.8px solid #0f172a;
            padding: 2.5px 6px;
            font-size: 6.8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .obs-body {
            padding: 4px 6px;
            font-size: 6.9pt;
            color: #1f2937;
        }

        .firmas-table {
            border: 1px solid #0f172a;
            margin-bottom: 4px;
        }

        .firmas-table td {
            padding: 5px 10px;
            vertical-align: bottom;
            text-align: center;
            border-right: 0.8px solid #0f172a;
        }

        .firmas-table td:last-child {
            border-right: none;
        }

        .sig-space {
            height: 30px;
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-size: 6.4pt;
            color: #4b5563;
            padding-top: 12px;
        }

        .sig-line {
            border-top: 1px solid #0f172a;
            padding-top: 2px;
        }

        .sig-name {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
        }

        .sig-role {
            font-size: 6.2pt;
            color: #262626;
        }

        .legal-box {
            border: 1px solid #1f2937;
            background-color: #f7f7f7;
            padding: 3.5px 6px;
            font-size: 6pt;
            color: #1f2937;
            text-align: justify;
        }

        .footer-meta {
            margin-top: 3px;
            font-size: 6.2pt;
            color: #262626;
            font-weight: 500;
        }
</style>

