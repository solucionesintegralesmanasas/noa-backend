@php
    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $doc['title'] ?? 'Contrato Laboral' }}</title>
    @include('pdf.standard.styles', ['orientation' => 'portrait', 'pdfMargen' => 'cartas'])
    <style>
        @page {
            size: letter portrait;
            margin: {{ $esHojaMembretadaCompleta ? '34mm 20mm 34mm 20mm' : '18mm 20mm 18mm 20mm' }};
        }

        body {
            background-color: {{ $esHojaMembretadaCompleta ? 'transparent' : '#ffffff' }};
        }

        #watermark {
            position: fixed;
            top: 25%;
            left: 15%;
            width: 70%;
            z-index: -1000;
        }

        #watermark img {
            width: 100%;
            height: auto;
            opacity: 0.12;
        }

        #letterhead-page-bg {
            position: fixed;
            top: -34mm;
            left: -20mm;
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
            margin-bottom: 10px;
        }

        #letterhead-banner img {
            width: 100%;
            height: auto;
            max-height: 2.2cm;
            display: block;
        }

        .contract-container {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            font-size: 9.8pt;
            line-height: 1.48;
            text-align: justify;
            color: #111827;
        }

        .contract-title {
            text-align: center;
            font-weight: 700;
            font-size: 12pt;
            color: #000000;
            background-color: var(--pdf-banda);
            border: 1px solid #1f2937;
            border-left: 3.5px solid #111827;
            padding: 6px 10px;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .contract-intro {
            margin-bottom: 14px;
        }

        .contract-clause {
            margin-bottom: 11px;
        }

        .contract-clause-title {
            font-weight: 700;
            color: #000000;
            margin-right: 4px;
        }

        .contract-signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 34px;
            page-break-inside: avoid;
        }

        .contract-signatures-table td {
            width: 50%;
            border: none;
            vertical-align: top;
            padding: 28px 14px 0 0;
            font-size: 9pt;
            line-height: 1.3;
        }

        .contract-signatures-table td:last-child {
            padding: 28px 0 0 14px;
        }

        .signature-line {
            border-top: 1px solid #1f2937;
            width: 92%;
            margin-bottom: 4px;
        }

        .signature-name {
            font-weight: 700;
            color: #000000;
        }

        .signature-role {
            font-style: italic;
            font-size: 8.5pt;
            color: #262626;
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

    <div class="contract-container">
        <div class="contract-title">
            {{ $doc['title'] }}
        </div>

        <div class="contract-intro">
            {!! nl2br(e($doc['intro'])) !!}
        </div>

        <div class="contract-clauses">
            @foreach ($doc['clauses'] as $clause)
                <div class="contract-clause">
                    <span class="contract-clause-title">{{ $clause['title'] }}:</span>
                    <span class="contract-clause-text">{!! nl2br(e($clause['text'])) !!}</span>
                </div>
            @endforeach
        </div>

        @if (isset($doc['closing']))
            <div class="contract-clause" style="margin-top: 18px;">
                {!! nl2br(e($doc['closing'])) !!}
            </div>
        @endif

        <table class="contract-signatures-table" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    @if (isset($doc['signatures']['employer']))
                        <div class="signature-line"></div>
                        <div class="signature-name">{{ $doc['signatures']['employer']['label'] ?? 'EL EMPLEADOR' }}</div>
                        <div class="signature-name">{{ $doc['signatures']['employer']['name'] ?? '' }}</div>
                        @if (! empty($doc['signatures']['employer']['id']))
                            <div>{{ $doc['signatures']['employer']['id'] }}</div>
                        @endif
                        @if (! empty($doc['signatures']['employer']['role']))
                            <div class="signature-role">{{ $doc['signatures']['employer']['role'] }}</div>
                        @endif
                    @endif
                </td>
                <td>
                    @if (isset($doc['signatures']['employee']))
                        <div class="signature-line"></div>
                        <div class="signature-name">{{ $doc['signatures']['employee']['label'] ?? 'EL TRABAJADOR' }}</div>
                        <div class="signature-name">{{ $doc['signatures']['employee']['name'] ?? '' }}</div>
                        @if (! empty($doc['signatures']['employee']['id']))
                            <div>{{ $doc['signatures']['employee']['id'] }}</div>
                        @endif
                    @endif
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
