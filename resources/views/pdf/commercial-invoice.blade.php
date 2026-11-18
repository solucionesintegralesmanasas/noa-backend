@php
    $cliente = $documento->customer;
    $municipioEmpresa = $empresa?->municipality;
    $resolucion = $documento->resolution;
    $resolucionHabilitacion = $empresa?->enablingResolutions?->firstWhere('status', true)
        ?? $empresa?->enablingResolutions?->first();
    $banco = $empresa?->bankDetails?->firstWhere('is_active', true)
        ?? $empresa?->bankDetails?->first();
    $actividad = $empresa?->economicActivities?->firstWhere('is_main_activity', true)
        ?? $empresa?->economicActivities?->first();
    $proveedor = $documento->billingProvider;
    $medioPago = $documento->paymentMethod;

    $esCotizacion = method_exists($documento, 'esCotizacion')
        ? $documento->esCotizacion()
        : (($documento->document_type ?? null) === 'COTIZACION');

    $estado = $documento->electronic_status;
    $estadoTexto = match ($estado) {
        'ACEPTADA_DIAN' => 'ACEPTADA DIAN',
        'PENDIENTE_DIAN' => 'PENDIENTE DIAN',
        'RECHAZADA_DIAN' => 'RECHAZADA DIAN',
        'VALIDADA_SIN_ENVIO' => 'VALIDADA SIN ENVÍO',
        default => 'SIN ENVÍO DIAN',
    };

    $nitEmpresa = $empresa
        ? number_format((int) $empresa->document_number, 0, ',', '.') . '-' . $empresa->verification_digit
        : '—';
    $documentoCliente = $cliente
        ? number_format((int) $cliente->document_number, 0, ',', '.')
            . ($cliente->nit_check_digit !== null ? '-' . $cliente->nit_check_digit : '')
        : '—';
    $nombreCliente = $cliente?->full_name ?? '—';
    $ciudadCliente = trim(implode(', ', array_filter([
        $cliente?->municipality?->name,
        $cliente?->municipality?->department?->name,
    ])));
    $ciudadEmpresa = trim(implode(', ', array_filter([
        $municipioEmpresa?->name,
        $municipioEmpresa?->department?->name,
    ])));

    $esCredito = $documento->due_date && $documento->issue_date
        && $documento->due_date->gt($documento->issue_date);
    $diasCredito = $esCredito ? $documento->issue_date->diffInDays($documento->due_date) : 0;
    $formaPago = $esCredito
        ? 'Crédito a ' . $diasCredito . ' ' . ($diasCredito === 1 ? 'día' : 'días')
        : 'Contado';

    $tasaIva = $documento->subtotal > 0
        ? round($documento->total_tax / $documento->subtotal * 100, 2)
        : 0;
    $subtotalBruto = $documento->subtotal + $documento->total_discount;
    $saldoPendiente = $documento->invoice_total - $documento->amount_paid;
    $moneda = fn ($valor) => '$ ' . number_format((float) $valor, 2, ',', '.');

    $siglasEmpresa = \App\Services\Pdf\PdfService::siglasEmpresa((string) ($empresa?->business_name ?? ''));
    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
    $sinMarcaVisual = ! empty($letterhead ?? null) || ! empty($ocultar_marca ?? false);
    $logoDataUrl = (! $sinMarcaVisual && ! empty($data['logo'] ?? null))
        ? 'data:image/png;base64,' . $data['logo']
        : null;

    $detalleEmpresa = implode(' · ', array_filter([
        'NIT ' . $nitEmpresa,
        $resolucionHabilitacion ? 'Hab. MinTransporte N° ' . $resolucionHabilitacion->resolution_number : null,
        trim(($empresa?->address ?? '') . ($ciudadEmpresa ? ', ' . $ciudadEmpresa : '')),
        $empresa?->phone ? 'Tel: ' . $empresa->phone : null,
        $empresa?->email ?: null,
    ]));

    $metaCabecera = [
        $esCotizacion ? 'COTIZACIÓN N°' : 'FACTURA N°' => $documento->invoice_number,
        'EMISIÓN' => trim(($documento->issue_date?->format('d/m/Y') ?? '—') . ' ' . ($documento->created_at?->format('h:i A') ?? '')),
        'VENCIMIENTO' => $documento->due_date?->format('d/m/Y') ?? '—',
        'ESTADO DIAN' => $estadoTexto,
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $esCotizacion ? 'Cotización' : 'Factura de Venta' }} - {{ $documento->invoice_number }}</title>
    @include('pdf.standard.styles', ['orientation' => 'portrait', 'pdfMargen' => 'denso'])
    <style>
        @page {
            size: letter portrait;
            margin: {{ $esHojaMembretadaCompleta ? '32mm 11mm 33mm 11mm' : '10mm 11mm' }};
        }

        body {
            background-color: {{ $esHojaMembretadaCompleta ? 'transparent' : '#ffffff' }};
            font-size: 7.3pt;
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
            padding: 2.8px 5px !important;
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
            padding: 2.4px 5.5px;
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
            padding: 3px 4.5px;
            font-size: 7pt;
            vertical-align: top;
        }

        .item-title {
            font-weight: 700;
            color: #000000;
            font-size: 7.1pt;
        }

        .item-detail {
            font-size: 6.3pt;
            color: #374151;
            margin-top: 1px;
        }

        /* Bloque unificado de Valor en Letras + Bancos + Resumen de Liquidación (misma altura siempre). */
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
            width: 57%;
            border-right: 1px solid var(--pdf-borde);
        }

        .resumen-der {
            width: 43%;
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
            padding: 4.5px 7px;
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

        .banco-bloque {
            padding: 4.5px 7px;
            font-size: 6.8pt;
            line-height: 1.3;
        }

        .banco-grid td {
            width: 50%;
            padding: 1px 4px 1px 0;
            vertical-align: top;
        }

        .obs-bloque {
            padding: 3.5px 7px;
            border-top: 0.8px solid var(--pdf-borde-interno);
            font-size: 6.6pt;
            color: #262626;
        }

        .totales-tbl td {
            padding: 2.4px 7px;
            font-size: 7pt;
            border-bottom: 0.6px solid #e5e7eb;
        }

        .totales-tbl tr:last-child td {
            border-bottom: none;
        }

        .totales-tbl .k {
            color: #374151;
        }

        .totales-tbl .v {
            color: #000000;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .totales-tbl tr.total-final td {
            background-color: var(--pdf-encabezado);
            border-top: 1.2px solid var(--pdf-borde);
            border-bottom: 1.2px solid var(--pdf-borde);
            padding: 4px 7px;
            font-weight: 700;
            color: #000000;
        }

        .totales-tbl tr.total-final td.v {
            font-size: 9pt;
        }

        /* Bloque DIAN / QR cerrado bajo section-banner */
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
            width: 70px;
            height: 70px;
            line-height: 70px;
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

        .legal {
            margin-top: 4px;
            font-size: 6.1pt;
            color: #262626;
            text-align: justify;
            line-height: 1.22;
        }

        .invoice-footer {
            margin-top: 4px;
            padding-top: 3px;
            border-top: 1px solid var(--pdf-borde-interno);
            width: 100%;
            font-size: 6.2pt;
            color: #374151;
        }

        .invoice-footer strong {
            color: #000000;
        }

        @if ($esHojaMembretadaCompleta)
        .header-table { margin-bottom: 3.5px; }
        .section-banner { margin-top: 3px; }
        .info-table { margin-bottom: 3px; }
        .info-table td { padding: 1.8px 5px; font-size: 6.7pt; }
        .main-table { margin-bottom: 3.5px; }
        .main-table thead th, .main-table tbody td { padding: 2.2px 4px; font-size: 6.7pt; }
        .resumen-grid { margin-bottom: 3.5px; }
        .totales-tbl td { padding: 1.8px 6px; font-size: 6.7pt; }
        .totales-tbl tr.total-final td { padding: 3px 6px; }
        .totales-tbl tr.total-final td.v { font-size: 8.5pt; }
        @endif
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
        'titulo_documento' => $esCotizacion ? 'COTIZACIÓN COMERCIAL' : 'FACTURA ELECTRÓNICA DE VENTA',
        'subtitulo_documento' => 'REPRESENTACIÓN GRÁFICA OFICIAL PARA EL ADQUIRIENTE',
        'meta_sgc' => $metaCabecera,
    ])

    {{-- 1. DATOS DEL ADQUIRIENTE / CLIENTE --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>1. Datos del Adquiriente / Cliente · ID Receptor: {{ $cliente?->documentType?->prefix ?? 'ID' }} Verificado</td>
        </tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 15%;">Razón Social:</td>
            <td class="info-val" style="width: 43%; font-weight: 700;">{{ $nombreCliente }}</td>
            <td class="info-lbl" style="width: 14%;">NIT / C.C.:</td>
            <td class="info-val" style="width: 28%;">{{ $documentoCliente }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Dirección:</td>
            <td class="info-val">{{ $cliente?->address ?: '—' }}</td>
            <td class="info-lbl">Ciudad / Depto:</td>
            <td class="info-val">{{ $ciudadCliente ?: '—' }}{{ $ciudadCliente ? ' - Colombia' : '' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Email Entrega:</td>
            <td class="info-val">{{ $cliente?->email ?: '—' }}</td>
            <td class="info-lbl">Teléfono / Cel:</td>
            <td class="info-val">{{ $cliente?->phone ?: '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Forma de Pago:</td>
            <td class="info-val">{{ $formaPago }}</td>
            <td class="info-lbl">Medio / Proyecto:</td>
            <td class="info-val">
                {{ $medioPago?->name ?? '—' }}{{ $medioPago?->dian_code ? ' (' . $medioPago->dian_code . ')' : '' }}
                @if ($documento->project?->name) · {{ $documento->project->name }} @endif
            </td>
        </tr>
    </table>

    {{-- 2. DETALLE DE SERVICIOS Y CONCEPTOS --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>2. Detalle de Servicios y Conceptos Facturados</td>
        </tr>
    </table>
    <table class="main-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 6%;">Cód.</th>
                <th style="width: 34%; text-align: left; padding-left: 6px;">Descripción del Servicio / Detalle Contractual</th>
                <th style="width: 6%;">Cant.</th>
                <th style="width: 5%;">U/M</th>
                <th style="width: 14%; text-align: right; padding-right: 6px;">Vr. Unitario</th>
                <th style="width: 12%; text-align: right; padding-right: 6px;">Dcto.</th>
                <th style="width: 5%;">IVA</th>
                <th style="width: 14%; text-align: right; padding-right: 6px;">Total Línea</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documento->items as $indice => $item)
                <tr>
                    <td class="txt-center bold muted">{{ str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="txt-center muted">
                        {{ str_pad((string) ($item->order_index ?? $indice + 1), 2, '0', STR_PAD_LEFT) }}
                    </td>
                    <td class="txt-left">
                        <div class="item-title">{{ $item->description }}</div>
                        @php
                            $detalles = array_filter([
                                $item->vehicle?->vehicle_license_plate
                                    ? 'Vehículo Placa: ' . $item->vehicle->vehicle_license_plate
                                        . ($item->vehicle->internal_number ? ' · No. Interno: ' . $item->vehicle->internal_number : '')
                                    : null,
                                $item->fuec_uuid ? 'FUEC asociado' : null,
                                $item->control_sheet_uuid ? 'Planilla de control asociada' : null,
                            ]);
                        @endphp
                        @if (! empty($detalles))
                            <div class="item-detail">{{ implode(' · ', $detalles) }}</div>
                        @endif
                    </td>
                    <td class="txt-center">{{ number_format((float) $item->quantity, 2, '.', ',') }}</td>
                    <td class="txt-center muted">{{ $item->measurementUnit?->code ?? 'UN' }}</td>
                    <td class="txt-right" style="white-space: nowrap;">{{ $moneda($item->unit_price) }}</td>
                    <td class="txt-right muted" style="white-space: nowrap;">{{ $moneda($item->discount_amount) }}</td>
                    <td class="txt-center muted">{{ rtrim(rtrim(number_format((float) $item->tax_rate, 2, '.', ','), '0'), '.') }}%</td>
                    <td class="txt-right bold" style="white-space: nowrap;">{{ $moneda($item->line_total) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="txt-center muted">Sin ítems registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- 3. TOTALES E INSTRUCCIONES UNIFICADAS --}}
    <table class="resumen-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td class="resumen-izq">
                <div class="sub-head">Valor en Letras e Instrucciones de Pago</div>
                <div class="letras-bloque">
                    <span class="bold" style="font-size: 6.3pt; text-transform: uppercase;">Valor Total en Letras:</span>
                    <div class="value">{{ \App\Support\Billing\NumeroALetras::formatear((float) $documento->invoice_total) }}.</div>
                </div>
                <div class="banco-bloque">
                    @if ($banco)
                        <table class="banco-grid" cellpadding="0" cellspacing="0">
                            <tr>
                                <td><span class="muted">Banco:</span> <strong>{{ $banco->bank_name ?: '—' }}</strong></td>
                                <td><span class="muted">Cuenta:</span> <strong>{{ $banco->account_type ?: '—' }}{{ $banco->account_number ? ' N° ' . $banco->account_number : '' }}</strong></td>
                            </tr>
                            <tr>
                                <td><span class="muted">Titular:</span> <strong>{{ $banco->account_holder ?: $empresa?->business_name }}</strong></td>
                                <td><span class="muted">Notificar a:</span> <strong>{{ $empresa?->email ?: '—' }}</strong></td>
                            </tr>
                        </table>
                    @else
                        <div class="muted">La empresa no tiene datos bancarios registrados. Consulte con facturación.</div>
                    @endif
                </div>
                @if ($documento->notes || $documento->terms)
                    <div class="obs-bloque">
                        <strong>Observaciones:</strong> {{ $documento->notes ?: $documento->terms }}
                    </div>
                @endif
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
                        <td class="v">{{ $moneda($documento->total_discount) }}</td>
                    </tr>
                    <tr>
                        <td class="k">Subtotal Gravable:</td>
                        <td class="v">{{ $moneda($documento->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td class="k">IVA General ({{ rtrim(rtrim(number_format($tasaIva, 2, '.', ','), '0'), '.') }}%):</td>
                        <td class="v">{{ $moneda($documento->total_tax) }}</td>
                    </tr>
                    <tr>
                        <td class="k">Retención en la Fuente:</td>
                        <td class="v">-{{ $moneda($documento->total_withholding) }}</td>
                    </tr>
                    <tr class="total-final">
                        <td class="k">Total a Pagar:</td>
                        <td class="v">{{ $moneda($documento->invoice_total) }}</td>
                    </tr>
                    <tr>
                        <td class="k">Anticipos Aplicados:</td>
                        <td class="v">{{ $moneda($documento->amount_paid) }}</td>
                    </tr>
                    <tr>
                        <td class="k bold" style="color: #000000;">Saldo Pendiente:</td>
                        <td class="v">{{ $moneda($saldoPendiente) }}</td>
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
                        <td class="cufe-title" style="width: 58%;">Código Único de Factura Electrónica (CUFE):</td>
                        <td class="txt-right bold" style="width: 42%; white-space: nowrap; font-size: 6.4pt; color: {{ $documento->cufe ? '#166534' : '#374151' }};">
                            {{ $documento->cufe ? 'Firma Digital Validada SHA-384' : 'Sin firma electrónica' }}
                        </td>
                    </tr>
                </table>
                <div class="cufe-value">{{ $documento->cufe ?: 'CUFE no generado: documento pendiente de transmisión a la DIAN.' }}</div>
                <div class="dian-note">
                    @if ($resolucion)
                        <strong>Autorización DIAN:</strong>
                        Resolución N° {{ $resolucion->resolution_number }} de {{ $resolucion->start_date?->format('d/m/Y') }}.
                        Prefijo {{ $resolucion->invoice_prefix }} del {{ $resolucion->invoice_prefix }}{{ $resolucion->invoice_start_number }}
                        al {{ $resolucion->invoice_prefix }}{{ $resolucion->invoice_end_number }}
                        (Vigencia {{ $resolucion->end_date?->format('d/m/Y') }}).
                    @endif
                    @if ($actividad)
                        <strong>CIIU:</strong> {{ $actividad->activity_code }}{{ $actividad->activity_description ? ' (' . $actividad->activity_description . ')' : '' }}.
                    @endif
                    @if ($empresa?->taxRegime?->name)
                        <strong>Régimen:</strong> {{ $empresa->taxRegime->name }}.
                    @endif
                    @if ($cliente?->taxResponsibility)
                        <strong>Resp. Fiscal:</strong> {{ $cliente->taxResponsibility->code }}.
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="legal">
        Esta factura electrónica de venta constituye título valor en los términos de la Ley 1231 de 2008 y el Art. 774
        del Código de Comercio Colombiano. La firma electrónica y/o recibo físico de los servicios o mercancías objeto
        de la presente factura implica aceptación irrevocable de la misma. A partir del vencimiento se cobrarán
        intereses moratorios a la tasa máxima legal autorizada por la Superintendencia Financiera de Colombia.
    </div>

    <table class="invoice-footer" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 50%;">
                Proveedor Tecnológico: <strong>{{ $proveedor?->name ?? 'No configurado' }}</strong>
            </td>
            <td class="txt-right" style="width: 50%;">
                Representación Gráfica Oficial para el Adquiriente · NIT <strong>{{ $nitEmpresa }}</strong>
            </td>
        </tr>
    </table>

</body>
</html>
