{{-- Base estándar de impresión PDF (Prototipo A aprobado, 2026-11-17).
     Parámetros: $orientation ('portrait'|'landscape'), $pdfMargen ('denso' por defecto | 'cartas').
     Reglas: sin '* { margin }' (rompe @page en DomPDF), solo tablas, sin flex/grid, unidades en pt. --}}
@php
    $__orient = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
    $__margen = ($pdfMargen ?? 'denso') === 'cartas' ? '18mm 20mm 18mm 20mm' : '8mm 10mm 8mm 8mm';
@endphp
<style>
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
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 7.5pt;
            line-height: 1.25;
            color: #0f172a;
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
            color: #1e3a8a;
            line-height: 1;
            letter-spacing: 0.5px;
        }

        .logo-caption {
            font-size: 5.5pt;
            font-weight: bold;
            color: #334155;
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
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.25px;
        }

        .company-sub {
            font-size: 6.4pt;
            color: #475569;
            margin-top: 1px;
        }

        .doc-main-title {
            font-size: 9.2pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-top: 3px;
            letter-spacing: 0.3px;
        }

        .doc-sub-title {
            font-size: 6.2pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .header-meta-cell {
            width: 26%;
            padding: 0 !important;
        }

        .meta-table td {
            border: none !important;
            border-bottom: 0.8px solid #0f172a !important;
            padding: 2px 5px !important;
            font-size: 6.6pt;
        }

        .meta-table tr:last-child td {
            border-bottom: none !important;
        }

        .meta-label {
            width: 44%;
            background-color: #f8fafc;
            border-right: 0.8px solid #0f172a !important;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
        }

        .meta-val {
            font-family: 'Courier', monospace;
            font-weight: bold;
            color: #0f172a;
            text-align: center;
        }

        /* 2. Banda de secciÃ³n */
        .section-banner {
            border: 1px solid #0f172a;
            border-left: 4px solid #1e3a8a;
            background-color: #e2e8f0;
            margin-top: 4px;
            margin-bottom: 0;
        }

        .section-banner td {
            padding: 2.5px 6px;
            font-size: 7pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* 3. Ficha de contexto */
        .info-table {
            border: 1px solid #0f172a;
            border-top: none;
            margin-bottom: 5px;
        }

        .info-table td {
            border: 0.7px solid #cbd5e1;
            padding: 2.6px 5px;
            font-size: 7.1pt;
            line-height: 1.25;
            vertical-align: middle;
        }

        .info-lbl {
            background-color: #f8fafc;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            font-size: 6.3pt;
            white-space: nowrap;
        }

        .info-val {
            color: #0f172a;
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
            font-family: 'Courier', monospace;
            font-weight: bold;
            font-size: 7.1pt;
            padding: 1.2px 5px;
            white-space: nowrap;
        }

        .placa-inline-tbl td.vehiculo-sep {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            font-size: 8pt;
            color: #64748b;
            padding: 0 4px;
            text-align: center;
            white-space: nowrap;
        }

        .placa-inline-tbl td.vehiculo-interno {
            font-family: 'Courier', monospace;
            font-weight: bold;
            font-size: 7pt;
            color: #1e3a8a;
            white-space: nowrap;
        }

        /* 4. Tabla de registros */
        .main-table {
            border: 1.2px solid #0f172a;
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
            background-color: #1e3a8a;
            color: #ffffff;
            border: 0.8px solid #0f172a;
            padding: 3.2px 3px;
            font-size: 6.6pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }

        .main-table tbody td {
            border: 0.6px solid #cbd5e1;
            padding: 3px 4px;
            font-size: 7pt;
        }

        .main-table tbody tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .mono {
            font-family: 'Courier', monospace;
        }

        .num {
            font-family: 'Courier', monospace;
            text-align: right;
            font-weight: bold;
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
            background-color: #f1f5f9;
            margin-bottom: 4px;
        }

        .totals-table td {
            border: 0.7px solid #cbd5e1;
            padding: 3.5px 6px;
            font-size: 7.2pt;
            text-align: center;
        }

        .obs-table {
            border: 1px solid #0f172a;
            margin-bottom: 4px;
        }

        .obs-head {
            background-color: #e2e8f0;
            border-left: 4px solid #1e3a8a;
            border-bottom: 0.8px solid #0f172a;
            padding: 2.5px 6px;
            font-size: 6.8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .obs-body {
            padding: 4px 6px;
            font-size: 6.9pt;
            color: #334155;
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
            font-family: 'Courier', monospace;
            font-size: 6.4pt;
            color: #94a3b8;
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
        }

        .sig-role {
            font-size: 6.2pt;
            color: #475569;
        }

        .legal-box {
            border: 1px solid #0f172a;
            background-color: #f8fafc;
            padding: 3.5px 6px;
            font-size: 6pt;
            color: #475569;
            text-align: justify;
        }

        .footer-meta {
            margin-top: 3px;
            font-size: 6.2pt;
            color: #64748b;
        }
</style>

