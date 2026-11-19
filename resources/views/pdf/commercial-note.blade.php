@php
    $factura = $nota->invoice;
    $cliente = $factura?->customer;
    $municipioEmpresa = $empresa?->municipality;
    $resolucion = $factura?->resolution;
    $resolucionHabilitacion = $empresa?->enablingResolutions?->firstWhere('status', true)
        ?? $empresa?->enablingResolutions?->first();
    $actividad = $empresa?->economicActivities?->firstWhere('is_main_activity', true)
        ?? $empresa?->economicActivities?->first();
    $proveedor = $factura?->billingProvider;
    $medioPago = $nota->paymentMethod;

    $tituloDocumento = $esCredito
        ? ($nota->cude ? 'NOTA CRÉDITO ELECTRÓNICA' : 'NOTA CRÉDITO')
        : ($nota->cude ? 'NOTA DÉBITO ELECTRÓNICA' : 'NOTA DÉBITO');

    $estadoDoc = $nota->status ?? 'BORRADOR';
    $estadoDian = $nota->electronic_status;
    $estadoDianTexto = match ($estadoDian) {
        'ACEPTADA_DIAN' => 'ACEPTADA DIAN',
        'PENDIENTE_DIAN' => 'PENDIENTE DIAN',
        'RECHAZADA_DIAN' => 'RECHAZADA DIAN',
        'VALIDADA_SIN_ENVIO' => 'VALIDADA SIN ENVÍO',
        default => 'SIN ENVÍO DIAN',
    };

    $conceptoCodigo = (string) ($nota->correction_concept_code ?? '');
    $conceptosCredito = [
        '1' => '1 - Devolución parcial de los bienes y/o no aceptación parcial del servicio',
        '01' => '01 - Devolución parcial de los bienes y/o no aceptación parcial del servicio',
        '2' => '2 - Anulación de factura electrónica',
        '02' => '02 - Anulación de factura electrónica',
        '3' => '3 - Rebaja o descuento parcial o total',
        '03' => '03 - Rebaja o descuento parcial o total',
        '4' => '4 - Ajuste de precio',
        '04' => '04 - Ajuste de precio',
        '5' => '5 - Descuento comercial por pronto pago',
        '05' => '05 - Descuento comercial por pronto pago',
        '6' => '6 - Descuento comercial por volumen de ventas',
        '06' => '06 - Descuento comercial por volumen de ventas',
    ];
    $conceptosDebito = [
        '1' => '1 - Intereses',
        '01' => '01 - Intereses',
        '2' => '2 - Gastos por cobrar',
        '02' => '02 - Gastos por cobrar',
        '3' => '3 - Cambio del valor',
        '03' => '03 - Cambio del valor',
        '4' => '4 - Otros',
        '04' => '04 - Otros',
    ];
    $conceptoTexto = ($esCredito ? $conceptosCredito[$conceptoCodigo] ?? null : $conceptosDebito[$conceptoCodigo] ?? null)
        ?? ($conceptoCodigo !== '' ? 'Código ' . $conceptoCodigo : '—');

    $nitEmpresa = $empresa
        ? number_format((int) $empresa->document_number, 0, ',', '.') . '-' . $empresa->verification_digit
        : '—';

    $documentoCliente = $cliente
        ? number_format((int) $cliente->document_number, 0, ',', '.')
            . ($cliente->nit_check_digit !== null ? '-' . $cliente->nit_check_digit : '')
        : '—';
    $nombreCliente = $cliente?->full_name ?? '—';
    $ciudadCliente = trim(implode(', ', array_filter([$cliente?->municipality?->name, $cliente?->municipality?->department?->name])));
    $ciudadEmpresa = trim(implode(', ', array_filter([$municipioEmpresa?->name, $municipioEmpresa?->department?->name])));

    $siglasEmpresa = \App\Services\Pdf\PdfService::siglasEmpresa((string) ($empresa?->business_name ?? ''));
    $tasaIva = $nota->subtotal > 0 ? round(($nota->total_tax / $nota->subtotal) * 100, 2) : 0;
    $subtotalBruto = $nota->subtotal + $nota->total_discount;
    $moneda = fn ($valor) => '$ ' . number_format((float) $valor, 2, ',', '.');

    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
    $sinMarcaVisual = ! empty($letterhead ?? null) || ! empty($ocultar_marca ?? false);
    $logoDataUrl = (! $sinMarcaVisual && ! empty($data['logo'] ?? null))
        ? 'data:image/png;base64,' . $data['logo']
        : null;

    $detalleEmpresa = implode(' · ', array_filter([
        'NIT ' . $nitEmpresa,
        $empresa?->taxRegime?->name ?: null,
        $resolucionHabilitacion ? 'Hab. MinTransporte N° ' . $resolucionHabilitacion->resolution_number : null,
        trim(($empresa?->address ?? '') . ($ciudadEmpresa ? ', ' . $ciudadEmpresa : '')),
        $empresa?->phone ? 'Tel: ' . $empresa->phone : null,
        $empresa?->email ?: null,
    ]));

    $metaCabecera = [
        'NOTA N°' => $nota->note_number,
        'EMISIÓN' => trim(($nota->issue_date?->format('d/m/Y') ?? '—') . ($nota->created_at ? ' ' . $nota->created_at->format('h:i A') : '')),
        'FACTURA REF.' => $factura?->invoice_number ?? '—',
        'ESTADO' => str_replace('_', ' ', $estadoDoc),
        'ESTADO DIAN' => $estadoDianTexto,
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $tituloDocumento }} - {{ $nota->note_number }}</title>
    @include('pdf.standard.styles', ['orientation' => 'portrait', 'pdfMargen' => 'denso'])
    <style>
        @page {
            size: letter portrait;
            margin: {{ $esHojaMembretadaCompleta ? '32mm 11mm 33mm 11mm' : '10mm 11mm' }};
        }

        body {
            background-color: {{ $esHojaMembretadaCompleta ? 'transparent' : '#ffffff' }};
            font-size: 7.4pt;
            line-height: 1.25;
            color: #111827;
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
            opacity: 0.08;
        }

        #letterhead-page-bg {
            position: fixed;
            top: -32mm;
            left: -11mm;
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

        .txt-right { text-align: right; }
        .txt-center { text-align: center; }
        .txt-left { text-align: left; }
        .align-top { vertical-align: top; }
        .bold { font-weight: 700; }
        .muted { color: #374151; }

        .header-table {
            margin-bottom: 5px;
        }

        .meta-table td {
            padding: 2.6px 5px !important;
            font-size: 6.4pt;
        }

        .meta-label {
            width: 39%;
        }

        .meta-val {
            width: 61%;
        }

        .section-banner {
            margin-top: 4px;
        }

        .info-table {
            margin-bottom: 4px;
        }

        .info-table td {
            padding: 2.5px 5.5px;
            font-size: 7pt;
        }

        .info-lbl {
            font-size: 6.3pt;
        }

        .main-table {
            border-top: none;
            margin-bottom: 5px;
        }

        .main-table thead th {
            padding: 3px 4px;
            font-size: 6.4pt;
        }

        .main-table tbody td {
            padding: 3.2px 4.5px;
            font-size: 7pt;
            vertical-align: top;
        }

        .item-title {
            font-weight: 700;
            color: #000000;
            font-size: 7.1pt;
        }

        /* Bloque unificado de Valor en Letras + Motivo + Resumen de Liquidación */
        .resumen-grid {
            width: 100%;
            border: 1px solid var(--pdf-borde);
            border-collapse: collapse;
            margin-bottom: 5px;
            background-color: #ffffff;
            page-break-inside: avoid;
        }

        .resumen-grid > tbody > tr > td {
            vertical-align: top;
            padding: 0;
        }

        .resumen-izq {
            width: 56%;
            border-right: 1px solid var(--pdf-borde);
        }

        .resumen-der {
            width: 44%;
        }

        .sub-head {
            background-color: var(--pdf-banda);
            border-bottom: 1px solid var(--pdf-borde);
            padding: 2.8px 7px;
            font-size: 6.6pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #000000;
        }

        .letras-bloque {
            padding: 5px 7px;
            border-bottom: 0.8px solid var(--pdf-borde-interno);
            background-color: var(--pdf-tenue);
        }

        .letras-bloque .value {
            font-size: 7.2pt;
            font-weight: 700;
            color: #000000;
            line-height: 1.25;
            margin-top: 1px;
        }

        .motivo-bloque {
            padding: 5px 7px;
            font-size: 6.9pt;
            color: #111827;
            line-height: 1.35;
        }

        .totales-tbl td {
            padding: 2.6px 7px;
            font-size: 7pt;
            border-bottom: 0.6px solid #e5e7eb;
        }

        .totales-tbl tr:last-child td {
            border-bottom: none;
        }

        .totales-tbl .k { color: #374151; }
        .totales-tbl .v { color: #000000; font-weight: 700; text-align: right; white-space: nowrap; }

        .totales-tbl tr.total-final td {
            background-color: var(--pdf-encabezado);
            border-top: 1.2px solid var(--pdf-borde);
            padding: 4.5px 7px;
            font-weight: 700;
            color: #000000;
        }

        .totales-tbl tr.total-final td.v {
            font-size: 9.2pt;
        }

        /* Bloque DIAN / QR cerrado */
        .dian-table {
            width: 100%;
            border: 1px solid var(--pdf-borde);
            border-top: none;
            border-collapse: collapse;
            background-color: #ffffff;
            page-break-inside: avoid;
        }

        .qr-cell {
            width: 17%;
            padding: 5px;
            vertical-align: middle;
            text-align: center;
            border-right: 0.8px solid var(--pdf-borde-interno);
            background-color: var(--pdf-tenue);
        }

        .dian-info-cell {
            width: 83%;
            padding: 5px 7px;
            vertical-align: middle;
        }

        .qr-frame {
            display: inline-block;
            border: 0.8px solid var(--pdf-borde-interno);
            padding: 2px;
            background-color: #ffffff;
        }

        .qr-empty {
            width: 72px;
            height: 72px;
            line-height: 72px;
            font-size: 6.5pt;
            color: #4b5563;
            text-align: center;
        }

        .cufe-title {
            font-size: 6.5pt;
            font-weight: 700;
            color: #000000;
            text-transform: uppercase;
        }

        .cufe-value {
            font-size: 6.3pt;
            color: #111827;
            background-color: var(--pdf-tenue);
            border: 0.7px solid var(--pdf-borde-interno);
            padding: 3px 5px;
            margin: 2.5px 0;
            word-wrap: break-word;
            word-break: break-all;
            line-height: 1.28;
        }

        .dian-note {
            font-size: 6.3pt;
            color: #262626;
            line-height: 1.28;
        }

        .legal-note {
            margin-top: 5px;
            font-size: 6.2pt;
            color: #262626;
            text-align: justify;
            line-height: 1.25;
        }

        .note-footer {
            margin-top: 5px;
            padding-top: 3px;
            border-top: 1px solid var(--pdf-borde-interno);
            font-size: 6.3pt;
            color: #374151;
        }

        .note-footer strong {
            color: #000000;
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

    @include('pdf.standard.header', [
        'logo_data_url' => $logoDataUrl,
        'ocultar_celda_logo' => $sinMarcaVisual,
        'ancho_logo' => 17,
        'ancho_meta' => 31,
        'empresa_siglas' => $siglasEmpresa,
        'empresa_transportadora' => $empresa?->business_name ?? 'EMPRESA',
        'empresa_detalle' => $detalleEmpresa,
        'titulo_documento' => $tituloDocumento,
        'subtitulo_documento' => 'REPRESENTACIÓN GRÁFICA OFICIAL · AJUSTE CONTABLE Y FISCAL DIAN',
        'meta_sgc' => $metaCabecera,
    ])

    {{-- 1. CLIENTE Y DOCUMENTO REFERENCIADO --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>1. Datos del Adquiriente / Cliente y Documento Referenciado</td>
        </tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 16%;">Razón Social:</td>
            <td class="info-val" style="width: 42%; font-weight: 700;">{{ $nombreCliente }}</td>
            <td class="info-lbl" style="width: 14%;">{{ $cliente?->documentType?->prefix ?? 'NIT / C.C.' }}:</td>
            <td class="info-val" style="width: 28%;">{{ $documentoCliente }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Dirección:</td>
            <td class="info-val">{{ $cliente?->address ?: '—' }}</td>
            <td class="info-lbl">Ciudad / Depto:</td>
            <td class="info-val">{{ $ciudadCliente ?: '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Correo Electrónico:</td>
            <td class="info-val">{{ $cliente?->email ?: '—' }}</td>
            <td class="info-lbl">Teléfono / Celular:</td>
            <td class="info-val">{{ $cliente?->phone ?: '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Factura Afectada:</td>
            <td class="info-val">
                {{ $factura?->invoice_number ?? '—' }}
                @if ($factura?->issue_date)
                    ({{ $factura->issue_date->format('d/m/Y') }})
                @endif
            </td>
            <td class="info-lbl">Medio de Pago:</td>
            <td class="info-val">{{ $medioPago?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Concepto DIAN:</td>
            <td class="info-val" colspan="3">{{ $conceptoTexto }}</td>
        </tr>
    </table>

    {{-- 2. DETALLE DE CONCEPTOS / AJUSTES --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>2. Detalle de Conceptos y Ajustes</td>
        </tr>
    </table>
    <table class="main-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 43%; text-align: left; padding-left: 6px;">Descripción del Concepto / Ajuste</th>
                <th style="width: 7%;">Cant.</th>
                <th style="width: 6%;">U/M</th>
                <th style="width: 13%; text-align: right; padding-right: 6px;">Vr. Unitario</th>
                <th style="width: 9%; text-align: right; padding-right: 6px;">Dcto.</th>
                <th style="width: 5%;">IVA</th>
                <th style="width: 13%; text-align: right; padding-right: 6px;">Total Línea</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($nota->items as $indice => $item)
                <tr>
                    <td class="txt-center bold muted">{{ str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="txt-left">
                        <div class="item-title">{{ $item->description }}</div>
                    </td>
                    <td class="txt-center">{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                    <td class="txt-center muted">{{ $item->measurementUnit?->code ?? 'UN' }}</td>
                    <td class="txt-right" style="white-space: nowrap;">{{ $moneda($item->unit_price) }}</td>
                    <td class="txt-right muted" style="white-space: nowrap;">{{ (float) $item->discount_amount > 0 ? $moneda($item->discount_amount) : '—' }}</td>
                    <td class="txt-center muted">{{ rtrim(rtrim(number_format((float) $item->tax_rate, 2, '.', ','), '0'), '.') }}%</td>
                    <td class="txt-right bold" style="white-space: nowrap;">{{ $moneda($item->line_total) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="txt-center muted" style="padding: 8px;">Sin ítems registrados en este documento.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- 3. TOTALES Y MOTIVO UNIFICADOS --}}
    <table class="resumen-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td class="resumen-izq">
                <div class="sub-head">Valor en Letras y Motivo del Ajuste</div>
                <div class="letras-bloque">
                    <span class="bold" style="font-size: 6.3pt; text-transform: uppercase;">Valor Total en Letras:</span>
                    <div class="value">{{ \App\Support\Billing\NumeroALetras::formatear((float) $nota->total) }}.</div>
                </div>
                <div class="motivo-bloque">
                    <span class="bold" style="font-size: 6.3pt; text-transform: uppercase;">Motivo / Observación de la {{ $esCredito ? 'Nota Crédito' : 'Nota Débito' }}:</span>
                    <div style="margin-top: 2px;">{{ $nota->observation ?: 'Sin observaciones adicionales registradas.' }}</div>
                </div>
            </td>
            <td class="resumen-der">
                <div class="sub-head">Resumen de Liquidación</div>
                <table class="totales-tbl" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="k">Subtotal Bruto:</td>
                        <td class="v">{{ $moneda($subtotalBruto) }}</td>
                    </tr>
                    <tr>
                        <td class="k">Descuentos Comerciales:</td>
                        <td class="v">{{ $moneda($nota->total_discount) }}</td>
                    </tr>
                    <tr>
                        <td class="k">Subtotal Gravable:</td>
                        <td class="v">{{ $moneda($nota->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td class="k">IVA ({{ rtrim(rtrim(number_format($tasaIva, 2, '.', ','), '0'), '.') }}%):</td>
                        <td class="v">{{ $moneda($nota->total_tax) }}</td>
                    </tr>
                    @if ((float) $nota->total_withholding > 0)
                        <tr>
                            <td class="k">Retenciones Aplicadas:</td>
                            <td class="v">-{{ $moneda($nota->total_withholding) }}</td>
                        </tr>
                    @endif
                    <tr class="total-final">
                        <td class="k">Total {{ $esCredito ? 'Nota Crédito' : 'Nota Débito' }}:</td>
                        <td class="v">{{ $moneda($nota->total) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- 4. VALIDACIÓN ELECTRÓNICA DIAN / QR --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>3. Validación Electrónica DIAN y Código QR</td>
        </tr>
    </table>
    <table class="dian-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="qr-cell">
                <div class="qr-frame">
                    @if (! empty($data['qr'] ?? null))
                        <img src="data:image/png;base64,{{ $data['qr'] }}"
                            alt="QR DIAN"
                            style="width: 82px; height: 82px; display: block; margin: 0 auto;" />
                    @else
                        <div class="qr-empty">QR pendiente</div>
                    @endif
                </div>
            </td>
            <td class="dian-info-cell">
                <table cellpadding="0" cellspacing="0" style="margin-bottom: 2px;">
                    <tr>
                        <td class="cufe-title" style="width: 58%;">Código Único de Documento Electrónico (CUDE):</td>
                        <td class="txt-right bold" style="width: 42%; white-space: nowrap; font-size: 6.4pt; color: {{ $nota->cude ? '#000000' : '#374151' }};">
                            {{ $nota->cude ? 'Firma Digital Validada SHA-384' : 'Sin transmisión electrónica' }}
                        </td>
                    </tr>
                </table>
                <div class="cufe-value">
                    {{ $nota->cude ?: 'CUDE no generado: documento interno o pendiente de transmisión a la DIAN.' }}
                </div>
                <div class="dian-note">
                    @if ($factura?->cufe)
                        <strong>CUFE Factura Referenciada:</strong> {{ $factura->cufe }}.
                    @endif
                    @if ($resolucion)
                        <strong>Resolución Factura Ref.:</strong> N° {{ $resolucion->resolution_number }} ({{ $resolucion->invoice_prefix }}).
                    @endif
                    @if ($actividad)
                        <strong>CIIU:</strong> {{ $actividad->activity_code }}{{ $actividad->activity_description ? ' (' . $actividad->activity_description . ')' : '' }}.
                    @endif
                    @if ($cliente?->taxResponsibility)
                        <strong>Resp. Fiscal:</strong> {{ $cliente->taxResponsibility->code }}.
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="legal-note">
        Este documento ajusta contable y fiscalmente la factura de venta referenciada
        ({{ $factura?->invoice_number ?? '—' }}) conforme a lo dispuesto en la normativa tributaria vigente de la DIAN.
    </div>

    <table class="note-footer" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 42%;">
                @if ($proveedor)
                    Proveedor Tecnológico: <strong>{{ $proveedor->name }}</strong>
                @else
                    Emisor: <strong>{{ $empresa?->business_name ?? 'Empresa' }}</strong>
                @endif
            </td>
            <td class="txt-center" style="width: 34%;">
                Representación Gráfica de {{ $esCredito ? 'Nota Crédito' : 'Nota Débito' }}
            </td>
            <td class="txt-right" style="width: 24%;">
                NIT <strong>{{ $nitEmpresa }}</strong>
            </td>
        </tr>
    </table>

</body>
</html>
