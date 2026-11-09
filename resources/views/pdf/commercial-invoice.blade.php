@extends('pdf.layouts.base')

@section('watermarks')@endsection
@section('header')@endsection

@push('styles')
    @page {
        size: letter portrait;
        margin: 12mm 12mm 14mm 12mm;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        color: #1e293b;
        font-size: 10px;
        margin: 0;
    }

    .invoice-page {
        padding: 0 0 9mm 0;
        font-size: 10px;
        line-height: 1.35;
    }

    /* Reset del layout base: la base define `td { border: 0.5px solid #000; padding: 6px 8px;
       vertical-align: middle; }` y `strong { font-size: 11px; }`, que ensucian este diseño. */
    .invoice-page td,
    .invoice-page th {
        border: none;
        padding: 0;
        vertical-align: top;
    }

    .invoice-page strong {
        font-size: inherit;
    }

    .txt-right { text-align: right; }
    .txt-center { text-align: center; }
    .txt-left { text-align: left; }
    .txt-justify { text-align: justify; }
    .align-top { vertical-align: top; }
    .align-middle { vertical-align: middle; }
    .upper { text-transform: uppercase; }
    .bold { font-weight: bold; }
    .mono { font-family: 'Courier New', Courier, monospace; }
    .muted { color: #64748b; }
    .soft { color: #475569; }
    .navy { color: #172554; }
    .amber { color: #b45309; }
    .red { color: #b91c1c; }
    .emerald { color: #047857; }

    .header-doc {
        border-bottom: 2px solid #0f172a;
        padding-bottom: 9px;
    }

    .brand-cell { width: 66%; }
    .document-cell { width: 34%; }

    .monogram {
        width: 48px;
        height: 48px;
        background-color: #172554;
        color: #ffffff;
        text-align: center;
        vertical-align: middle;
        border-radius: 8px;
    }

    .monogram .mono-main {
        display: block;
        font-size: 15px;
        font-weight: bold;
        letter-spacing: 3px;
        color: #38bdf8;
    }

    .monogram .mono-sub {
        display: block;
        font-size: 7px;
        font-weight: bold;
        letter-spacing: 1px;
        color: #bfdbfe;
        text-transform: uppercase;
    }

    .company-name {
        font-size: 12px;
        font-weight: bold;
        color: #172554;
        text-transform: uppercase;
        line-height: 1.2;
    }

    .company-nit {
        font-size: 10px;
        font-weight: bold;
        color: #334155;
    }

    .company-line {
        font-size: 8.5px;
        color: #64748b;
        line-height: 1.35;
    }

    .doc-card {
        border: 2px solid #172554;
        border-radius: 6px;
        overflow: hidden;
    }

    .doc-card-head {
        background-color: #172554;
        color: #ffffff;
        padding: 4px 8px;
    }

    .doc-card-head .label {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #e2e8f0;
    }

    .doc-card-head .number {
        font-size: 11px;
        font-weight: bold;
        letter-spacing: 1px;
        color: #fcd34d;
    }

    .doc-card-body {
        background-color: #ffffff;
        padding: 5px 8px;
    }

    .doc-row {
        width: 100%;
        font-size: 8.5px;
    }

    .doc-row td { padding: 1px 0; }

    .doc-row .k { color: #64748b; }
    .doc-row .v { font-weight: bold; color: #1e293b; }

    .estado {
        display: inline-block;
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 7.5px;
        font-weight: bold;
        white-space: nowrap;
    }

    .estado-aceptada { background-color: #d1fae5; color: #065f46; }
    .estado-pendiente { background-color: #fef3c7; color: #92400e; }
    .estado-rechazada { background-color: #fee2e2; color: #991b1b; }
    .estado-validada { background-color: #e0f2fe; color: #075985; }
    .estado-neutro { background-color: #f1f5f9; color: #475569; }

    .client-box {
        margin-top: 8px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        padding: 7px 9px;
    }

    .client-head {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 5px;
    }

    .client-head td {
        border: none;
        padding: 0;
        vertical-align: middle;
    }

    .client-title {
        font-size: 8.5px;
        font-weight: bold;
        color: #1e3a8a;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .client-row { width: 100%; font-size: 9px; }
    .client-row td { padding: 1px 0; vertical-align: top; }
    .client-row .k { color: #64748b; }
    .client-row .v { color: #1e293b; font-weight: bold; }
    .client-hint { font-size: 8px; color: #64748b; }

    .terms-row { width: 100%; font-size: 9px; border-collapse: collapse; }
    .terms-row td {
        border-top: 1px solid #e2e8f0;
        padding-top: 3px;
        margin-top: 3px;
        vertical-align: top;
    }

    .items {
        width: 100%;
        border-collapse: collapse;
        font-size: 9px;
        margin-top: 8px;
    }

    .items thead th {
        background-color: #172554;
        color: #ffffff;
        font-weight: bold;
        text-align: left;
        padding: 4px 5px;
        font-size: 8.5px;
    }

    .items tbody td {
        padding: 4px 5px;
        border-left: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }

    .items tbody tr.alt td { background-color: #f8fafc; }
    .item-title { font-weight: bold; color: #0f172a; font-size: 9px; }
    .item-detail { font-size: 8px; color: #64748b; margin-top: 1px; }

    .amounts { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .amounts > tbody > tr > td { vertical-align: top; }
    .amounts-left { width: 58%; padding-right: 7px; }
    .amounts-right { width: 42%; }

    .letters {
        background-color: #eff6ff;
        border-left: 4px solid #1e3a8a;
        padding: 6px 8px;
        margin-bottom: 6px;
    }

    .letters .label {
        display: block;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        color: #172554;
    }

    .letters .value {
        font-size: 9px;
        font-weight: bold;
        color: #1e293b;
        line-height: 1.3;
    }

    .bank-box {
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        background-color: #f8fafc;
        padding: 6px 8px;
        font-size: 8.5px;
    }

    .bank-title {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #334155;
        margin-bottom: 3px;
    }

    .bank-grid { width: 100%; border-collapse: collapse; }
    .bank-grid td { width: 50%; padding: 1px 0; vertical-align: top; }
    .bank-grid .k { color: #64748b; }
    .bank-grid .v { color: #1e293b; font-weight: bold; }

    .observations { font-size: 8px; color: #64748b; margin-top: 6px; line-height: 1.35; }
    .observations .label { font-weight: bold; color: #334155; }

    .totals {
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        background-color: #f8fafc;
        overflow: hidden;
    }

    .totals-head {
        background-color: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
        padding: 3px 8px;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #334155;
    }

    .totals-body { padding: 6px 8px; }

    .total-row { width: 100%; font-size: 9px; padding: 1px 0; }
    .total-row td { padding: 1px 0; }
    .total-row .k { color: #64748b; }
    .total-row .v { color: #1e293b; font-weight: bold; text-align: right; }

    .total-highlight {
        width: 100%;
        background-color: #172554;
        color: #ffffff;
        border-radius: 4px;
        margin: 5px 0;
    }

    .total-highlight td { padding: 5px 8px; vertical-align: middle; }
    .total-highlight .k {
        font-size: 9.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .total-highlight .v {
        font-size: 12px;
        font-weight: bold;
        color: #fcd34d;
        text-align: right;
    }

    .saldo .k { color: #0f172a; font-weight: bold; }
    .saldo .v { color: #1e3a8a; font-weight: bold; }

    .dian-box {
        margin-top: 8px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        padding: 6px 8px;
        background-color: #ffffff;
    }

    .dian-table { width: 100%; border-collapse: collapse; }
    .dian-table td { vertical-align: top; }
    .qr-cell { width: 82px; padding-right: 8px; }
    .qr-frame {
        width: 76px;
        height: 76px;
        border: 1px solid #cbd5e1;
        padding: 2px;
        background-color: #ffffff;
        text-align: center;
    }

    .qr-empty {
        width: 70px;
        height: 70px;
        line-height: 70px;
        font-size: 8px;
        color: #94a3b8;
        text-align: center;
    }

    .cufe-title {
        font-size: 8px;
        font-weight: bold;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .firma-badge {
        font-size: 7px;
        font-weight: bold;
        color: #047857;
        background-color: #ecfdf5;
        border: 1px solid #a7f3d0;
        padding: 1px 5px;
        border-radius: 3px;
    }

    .cufe-value {
        font-family: 'Courier New', Courier, monospace;
        font-size: 7px;
        color: #334155;
        background-color: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 3px;
        padding: 4px 5px;
        margin: 3px 0;
        word-wrap: break-word;
        word-break: break-all;
        line-height: 1.4;
    }

    .dian-note { font-size: 7.5px; color: #64748b; line-height: 1.35; }
    .dian-note .label { font-weight: bold; color: #334155; }

    .legal {
        margin-top: 7px;
        font-size: 7.2px;
        color: #64748b;
        text-align: justify;
        line-height: 1.35;
    }

    .footer {
        margin-top: 10px;
        padding-top: 4px;
        border-top: 1px solid #e2e8f0;
        width: 100%;
        font-size: 7px;
        color: #94a3b8;
        border-collapse: collapse;
    }

    .footer td { vertical-align: middle; }
    .footer .center { text-align: center; }
    .footer .right { text-align: right; }
    .footer strong { color: #475569; }
@endpush

@section('content')
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

        $estado = $documento->electronic_status;
        $estadoTexto = match ($estado) {
            'ACEPTADA_DIAN' => 'ACEPTADA DIAN',
            'PENDIENTE_DIAN' => 'PENDIENTE DIAN',
            'RECHAZADA_DIAN' => 'RECHAZADA DIAN',
            'VALIDADA_SIN_ENVIO' => 'VALIDADA SIN ENVÍO',
            default => 'SIN ENVÍO DIAN',
        };
        $estadoClase = match ($estado) {
            'ACEPTADA_DIAN' => 'estado-aceptada',
            'PENDIENTE_DIAN' => 'estado-pendiente',
            'RECHAZADA_DIAN' => 'estado-rechazada',
            'VALIDADA_SIN_ENVIO' => 'estado-validada',
            default => 'estado-neutro',
        };

        $nitEmpresa = $empresa
            ? number_format((int) $empresa->document_number, 0, ',', '.') . '-' . $empresa->verification_digit
            : '—';
        $documentoCliente = $cliente
            ? number_format((int) $cliente->document_number, 0, ',', '.')
                . ($cliente->nit_check_digit !== null ? ' · DV: ' . $cliente->nit_check_digit : '')
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
    @endphp

    <div class="invoice-page">
        <header class="header-doc">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td class="brand-cell align-top">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td class="align-top" style="width: 58px; padding-right: 8px;">
                                    @if (!empty($data['logo'] ?? null))
                                        <img src="data:image/png;base64,{{ $data['logo'] }}"
                                             alt="Logo {{ $empresa?->business_name }}"
                                             style="width: 48px; max-height: 48px;" />
                                    @else
                                        <div class="monogram">
                                            <span class="mono-main">SB</span>
                                            <span class="mono-sub">Trans</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="align-top">
                                    <div class="company-name">{{ $empresa?->business_name ?? 'EMPRESA' }}</div>
                                    <div class="company-nit">NIT: {{ $nitEmpresa }}</div>
                                    @if ($resolucionHabilitacion)
                                        <div class="company-line">
                                            Resolución Habilitación MinTransporte N° {{ $resolucionHabilitacion->resolution_number }}
                                        </div>
                                    @endif
                                    <div class="company-line">
                                        {{ $empresa?->address }}{{ $ciudadEmpresa ? ' · ' . $ciudadEmpresa : '' }}
                                    </div>
                                    <div class="company-line">
                                        PBX: {{ $empresa?->phone ?: '—' }} · {{ $empresa?->email ?: '—' }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="document-cell align-top">
                        <div class="doc-card">
                            <div class="doc-card-head">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td class="txt-left"><span class="label">Factura N°</span></td>
                                        <td class="txt-right"><span class="number">{{ $documento->invoice_number }}</span></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="doc-card-body">
                                <table class="doc-row">
                                    <tr>
                                        <td class="k txt-left">Fecha Emisión:</td>
                                        <td class="v txt-right">
                                            {{ $documento->issue_date?->format('d/m/Y') }}
                                            {{ $documento->created_at?->format('h:i A') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="k txt-left">Vencimiento:</td>
                                        <td class="v txt-right red">{{ $documento->due_date?->format('d/m/Y') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="k txt-left" style="border-top: 1px solid #f1f5f9; padding-top: 2px;">Estado DIAN:</td>
                                        <td class="txt-right" style="border-top: 1px solid #f1f5f9; padding-top: 2px;">
                                            <span class="estado {{ $estadoClase }}">● {{ $estadoTexto }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </header>

        <section class="client-box">
            <table class="client-head">
                <tr>
                    <td class="client-title">Datos del Adquiriente / Cliente</td>
                    <td class="client-hint txt-right">
                        ID Receptor: {{ $cliente?->documentType?->prefix ?? 'ID' }} Verificado
                    </td>
                </tr>
            </table>
            <table class="client-row">
                <tr>
                    <td style="width: 58%;"><span class="k">Razón Social:</span> <span class="v">{{ $nombreCliente }}</span></td>
                    <td style="width: 42%;"><span class="k">NIT / C.C.:</span> <span class="v">{{ $documentoCliente }}</span></td>
                </tr>
                <tr>
                    <td><span class="k">Dirección:</span> <span class="v">{{ $cliente?->address ?: '—' }}</span></td>
                    <td><span class="k">Ciudad / Depto:</span> <span class="v">{{ $ciudadCliente ?: '—' }}{{ $ciudadCliente ? ' - Colombia' : '' }}</span></td>
                </tr>
                <tr>
                    <td><span class="k">Email Entrega:</span> <span class="v">{{ $cliente?->email ?: '—' }}</span></td>
                    <td><span class="k">Teléfono / Cel:</span> <span class="v">{{ $cliente?->phone ?: '—' }}</span></td>
                </tr>
            </table>
            <table class="terms-row">
                <tr>
                    <td style="width: 33%;"><span class="k">Forma de Pago:</span> <span class="v">{{ $formaPago }}</span></td>
                    <td style="width: 34%;"><span class="k">Medio de Pago:</span> <span class="v">{{ $medioPago?->name ?? '—' }}{{ $medioPago?->dian_code ? ' (' . $medioPago->dian_code . ')' : '' }}</span></td>
                    <td style="width: 33%;" class="txt-right"><span class="k">Orden de Compra / Exp:</span> <span class="v">{{ $documento->project?->name ?? '—' }}</span></td>
                </tr>
            </table>
        </section>

        <section>
            <table class="items">
                <thead>
                    <tr>
                        <th class="txt-center" style="width: 5%;">#</th>
                        <th style="width: 12%;">Código</th>
                        <th style="width: 38%;">Descripción del Servicio / Detalle Contractual</th>
                        <th class="txt-center" style="width: 7%;">Cant.</th>
                        <th class="txt-center" style="width: 7%;">U/M</th>
                        <th class="txt-right" style="width: 13%;">Vr. Unitario</th>
                        <th class="txt-right" style="width: 8%;">Dcto.</th>
                        <th class="txt-center" style="width: 5%;">IVA</th>
                        <th class="txt-right" style="width: 13%;">Total Línea</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documento->items as $indice => $item)
                        <tr class="{{ $indice % 2 === 1 ? 'alt' : '' }}">
                            <td class="txt-center bold muted">{{ str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="mono muted" style="font-size: 8px;">
                                {{ str_pad((string) ($item->order_index ?? $indice + 1), 2, '0', STR_PAD_LEFT) }}
                            </td>
                            <td>
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
                                @if (!empty($detalles))
                                    <div class="item-detail">{{ implode(' · ', $detalles) }}</div>
                                @endif
                            </td>
                            <td class="txt-center">{{ number_format((float) $item->quantity, 2, '.', ',') }}</td>
                            <td class="txt-center muted">{{ $item->measurementUnit?->code ?? 'UN' }}</td>
                            <td class="txt-right">{{ $moneda($item->unit_price) }}</td>
                            <td class="txt-right muted">{{ $moneda($item->discount_amount) }}</td>
                            <td class="txt-center muted">{{ rtrim(rtrim(number_format((float) $item->tax_rate, 2, '.', ','), '0'), ',') }}%</td>
                            <td class="txt-right bold">{{ $moneda($item->line_total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="txt-center muted">Sin ítems registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="amounts">
            <table class="amounts" style="margin-top: 0;">
                <tr>
                    <td class="amounts-left">
                        <div class="letters">
                            <span class="label">Valor total en letras:</span>
                            <div class="value">{{ \App\Support\Billing\NumeroALetras::formatear((float) $documento->invoice_total) }}.</div>
                        </div>

                        <div class="bank-box">
                            <div class="bank-title">Instrucciones Bancarias para Pago</div>
                            @if ($banco)
                                <table class="bank-grid">
                                    <tr>
                                        <td><span class="k">Banco:</span> <span class="v">{{ $banco->bank_name ?: '—' }}</span></td>
                                        <td><span class="k">Tipo de Cuenta:</span> <span class="v">{{ $banco->account_type ?: '—' }}{{ $banco->account_number ? ' N° ' . $banco->account_number : '' }}</span></td>
                                    </tr>
                                    <tr>
                                        <td><span class="k">Titular:</span> <span class="v">{{ $banco->account_holder ?: $empresa?->business_name }}</span></td>
                                        <td><span class="k">Notificar a:</span> <span class="v">{{ $empresa?->email ?: '—' }}</span></td>
                                    </tr>
                                </table>
                            @else
                                <div class="muted">La empresa no tiene datos bancarios registrados. Consulte con facturación.</div>
                            @endif
                        </div>

                        @if ($documento->notes || $documento->terms)
                            <div class="observations">
                                <span class="label">Observaciones:</span>
                                {{ $documento->notes ?: $documento->terms }}
                            </div>
                        @endif
                    </td>
                    <td class="amounts-right">
                        <div class="totals">
                            <div class="totals-head">Resumen de Liquidación</div>
                            <div class="totals-body">
                                <table class="total-row">
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
                                        <td class="k">IVA General ({{ rtrim(rtrim(number_format($tasaIva, 2, '.', ','), '0'), ',') }}%):</td>
                                        <td class="v">{{ $moneda($documento->total_tax) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="k" style="border-top: 1px solid #e2e8f0; padding-top: 3px; font-size: 8px;">Retención en la Fuente:</td>
                                        <td class="v" style="border-top: 1px solid #e2e8f0; padding-top: 3px; font-size: 8px;">-{{ $moneda($documento->total_withholding) }}</td>
                                    </tr>
                                </table>

                                <table class="total-highlight">
                                    <tr>
                                        <td class="k">Total a Pagar:</td>
                                        <td class="v">{{ $moneda($documento->invoice_total) }}</td>
                                    </tr>
                                </table>

                                <table class="total-row">
                                    <tr>
                                        <td class="k">Anticipos Aplicados:</td>
                                        <td class="v">{{ $moneda($documento->amount_paid) }}</td>
                                    </tr>
                                    <tr class="saldo">
                                        <td class="k" style="border-top: 1px solid #e2e8f0; padding-top: 3px;">Saldo Pendiente:</td>
                                        <td class="v" style="border-top: 1px solid #e2e8f0; padding-top: 3px;">{{ $moneda($saldoPendiente) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </section>

        <section class="dian-box">
            <table class="dian-table">
                <tr>
                    <td class="qr-cell align-top">
                        <div class="qr-frame">
                            @if (!empty($data['qr'] ?? null))
                                <img src="data:image/png;base64,{{ $data['qr'] }}"
                                     alt="QR DIAN {{ $documento->cufe }}"
                                     style="width: 70px; height: 70px;" />
                            @else
                                <div class="qr-empty">QR pendiente</div>
                            @endif
                        </div>
                    </td>
                    <td class="align-top">
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 3px;">
                            <tr>
                                <td class="cufe-title">Código Único de Factura Electrónica (CUFE):</td>
                                <td class="txt-right">
                                    @if ($documento->cufe)
                                        <span class="firma-badge">Firma Digital Validada SHA-384</span>
                                    @else
                                        <span class="estado estado-neutro">Sin firma electrónica</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <div class="cufe-value">{{ $documento->cufe ?: 'CUFE no generado: documento pendiente de transmisión a la DIAN.' }}</div>
                        <div class="dian-note">
                            @if ($resolucion)
                                <span class="label">Autorización DIAN:</span>
                                Resolución N° {{ $resolucion->resolution_number }}
                                de {{ $resolucion->start_date?->format('d/m/Y') }}.
                                Prefijo {{ $resolucion->invoice_prefix }} habilitado del
                                {{ $resolucion->invoice_prefix }}{{ $resolucion->invoice_start_number }}
                                al {{ $resolucion->invoice_prefix }}{{ $resolucion->invoice_end_number }}.
                                Vigencia: {{ $resolucion->end_date?->format('d/m/Y') }}.
                            @endif
                            @if ($actividad)
                                Actividad Económica CIIU Principal {{ $actividad->activity_code }}
                                @if ($actividad->activity_description)
                                    ({{ $actividad->activity_description }})
                                @endif.
                            @endif
                            @if ($empresa?->taxRegime?->name)
                                Régimen Tributario: {{ $empresa->taxRegime->name }}.
                            @endif
                            @if ($cliente?->taxResponsibility)
                                Responsabilidad Fiscal del Adquiriente: {{ $cliente->taxResponsibility->code }}.
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </section>

        <section class="legal">
            Esta factura electrónica de venta constituye título valor en los términos de la Ley 1231 de 2008 y el Art. 774
            del Código de Comercio Colombiano. La firma electrónica y/o recibo físico de los servicios o mercancías objeto
            de la presente factura implica aceptación irrevocable de la misma. A partir del vencimiento se cobrarán
            intereses moratorios a la tasa máxima legal autorizada por la Superintendencia Financiera de Colombia.
        </section>

        <footer class="footer">
            <table class="footer">
                <tr>
                    <td style="width: 45%;">
                        Proveedor Tecnológico:
                        <strong>{{ $proveedor?->name ?? 'No configurado' }}</strong>
                    </td>
                    <td class="center" style="width: 30%; font-weight: bold; color: #64748b;">
                        Copia Electrónica Oficial para el Adquiriente
                    </td>
                    <td class="right" style="width: 25%;">
                        Página <strong>1</strong> de <strong>1</strong>
                    </td>
                </tr>
            </table>
        </footer>
    </div>
@endsection
