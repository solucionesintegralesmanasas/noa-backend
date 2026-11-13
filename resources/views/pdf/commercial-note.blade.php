@extends('pdf.layouts.base')

{{-- Mantenemos @section('watermarks') del layout base para respetar membrete ($letterhead),
     marca de agua ($logo_fondo / $ocultar_marca) y sello de anulado ($data['cancelado']).
     Suprimimos @section('header') porque la nota usa el encabezado corporativo de facturación. --}}
@section('header')
@endsection

@push('styles')
    @page {
        size: letter portrait;
        margin: 10mm 12mm 16mm 12mm;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        color: #1e293b;
        font-size: 9.5px;
        line-height: 1.35;
        margin: 0 !important;
        padding: 0 !important;
        background-color: #ffffff;
    }

    body.with-letterhead {
        margin-top: 0 !important;
    }

    /* Reset quirúrgico sobre reglas globales de pdf.layouts.base */
    .note-page table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .note-page td,
    .note-page th {
        border: none;
        padding: 0;
        vertical-align: top;
        word-wrap: break-word;
    }

    .note-page strong {
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
    .navy { color: #0f172a; }
    .accent { color: #1e3a8a; }
    .red { color: #b91c1c; }
    .emerald { color: #047857; }

    /* ===== Encabezado corporativo ===== */
    .header-doc {
        border-bottom: 2px solid #0f172a;
        padding-bottom: 10px;
        margin-bottom: 8px;
    }

    .header-main {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .brand-cell {
        width: 61%;
        padding-right: 12px;
        vertical-align: top;
    }

    .document-cell {
        width: 39%;
        vertical-align: top;
    }

    .brand-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
    }

    .logo-cell {
        width: 1%;
        white-space: nowrap;
        padding-right: 10px;
        vertical-align: middle;
    }

    .logo-img {
        max-width: 108px;
        max-height: 62px;
        width: auto;
        height: auto;
        display: block;
    }

    .brand-info {
        width: 99%;
        vertical-align: top;
    }

    .monogram {
        width: 56px;
        height: 56px;
        background-color: #0f172a;
        color: #ffffff;
        text-align: center;
        border: 1px solid #1e3a8a;
    }

    .monogram-inner {
        padding-top: 13px;
    }

    .monogram .mono-main {
        display: block;
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 2px;
        color: #ffffff;
        line-height: 1.1;
    }

    .monogram .mono-sub {
        display: block;
        font-size: 6.5px;
        font-weight: bold;
        letter-spacing: 1px;
        color: #cbd5e1;
        text-transform: uppercase;
        margin-top: 2px;
    }

    .company-name {
        font-size: 11.5px;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        line-height: 1.2;
        margin-bottom: 2px;
        white-space: normal;
        word-wrap: break-word;
    }

    .company-nit {
        font-size: 9.5px;
        font-weight: bold;
        color: #1e3a8a;
        margin-bottom: 2px;
    }

    .company-line {
        font-size: 8px;
        color: #475569;
        line-height: 1.35;
    }

    /* ===== Tarjeta del documento ===== */
    .doc-card {
        border: 1px solid #0f172a;
        background-color: #ffffff;
    }

    .doc-card-head {
        background-color: #0f172a;
        color: #ffffff;
        padding: 5px 8px;
        border-bottom: 2px solid #1e3a8a;
    }

    .doc-card-head .label {
        display: block;
        font-size: 7.2px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #cbd5e1;
    }

    .doc-card-head .number {
        display: block;
        font-size: 11.5px;
        font-weight: bold;
        letter-spacing: 0.5px;
        color: #ffffff;
        margin-top: 1px;
    }

    .doc-card-body {
        background-color: #f8fafc;
        padding: 5px 8px;
    }

    .doc-row {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.2px;
    }

    .doc-row td {
        padding: 2px 0;
        vertical-align: middle;
    }

    .doc-row .k {
        color: #64748b;
        width: 44%;
    }

    .doc-row .v {
        font-weight: bold;
        color: #0f172a;
        text-align: right;
    }

    .doc-row .sep {
        border-top: 1px solid #e2e8f0;
        padding-top: 3px;
    }

    .estado {
        display: inline-block;
        padding: 1px 5px;
        font-size: 7px;
        font-weight: bold;
        text-transform: uppercase;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .estado-aceptada { background-color: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .estado-pendiente { background-color: #fffbeb; color: #92400e; border-color: #fde68a; }
    .estado-rechazada { background-color: #fef2f2; color: #991b1b; border-color: #fecaca; }
    .estado-validada { background-color: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
    .estado-neutro { background-color: #f1f5f9; color: #475569; border-color: #cbd5e1; }

    /* ===== Cliente y Referencia ===== */
    .client-box {
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        margin-bottom: 8px;
    }

    .client-head {
        background-color: #eef2f6;
        border-bottom: 1px solid #cbd5e1;
        border-left: 3px solid #1e3a8a;
        padding: 4px 8px;
    }

    .client-title {
        font-size: 8px;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.7px;
    }

    .client-hint {
        font-size: 7.5px;
        color: #475569;
        text-align: right;
    }

    .client-body {
        padding: 6px 8px;
    }

    .client-grid td {
        padding: 1.5px 4px 1.5px 0;
        font-size: 8.5px;
    }

    .client-grid .k { color: #64748b; }
    .client-grid .v { color: #0f172a; font-weight: bold; }

    .ref-bar {
        border-top: 1px solid #cbd5e1;
        background-color: #f8fafc;
        padding: 5px 8px;
    }

    .ref-bar td {
        font-size: 8px;
        padding-right: 6px;
    }

    .ref-bar .k { color: #64748b; }
    .ref-bar .v { color: #0f172a; font-weight: bold; }

    /* ===== Tabla de ítems ===== */
    .items-table {
        margin-bottom: 8px;
        border: 1px solid #cbd5e1;
    }

    .items-table thead {
        display: table-header-group;
    }

    .items-table thead th {
        background-color: #0f172a;
        color: #ffffff;
        font-weight: bold;
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 5px 5px;
        border-bottom: 1px solid #0f172a;
    }

    .items-table tbody tr {
        page-break-inside: avoid;
    }

    .items-table tbody td {
        padding: 5px 5px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 8.5px;
        vertical-align: top;
    }

    .items-table tbody tr.alt td {
        background-color: #f8fafc;
    }

    .item-title {
        font-weight: bold;
        color: #0f172a;
        font-size: 8.8px;
        line-height: 1.3;
    }

    /* ===== Totales y motivo ===== */
    .amounts-section {
        page-break-inside: avoid;
        margin-bottom: 8px;
    }

    .amounts-left {
        width: 56%;
        padding-right: 10px;
    }

    .amounts-right {
        width: 44%;
    }

    .letters-box {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        border-left: 3px solid #1e3a8a;
        padding: 5px 8px;
        margin-bottom: 6px;
    }

    .letters-box .label {
        display: block;
        font-size: 7.2px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #1e3a8a;
        margin-bottom: 1px;
    }

    .letters-box .value {
        font-size: 8.5px;
        font-weight: bold;
        color: #0f172a;
        line-height: 1.3;
    }

    .observations {
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        padding: 6px 8px;
        font-size: 8px;
        color: #334155;
        line-height: 1.4;
    }

    .observations .label {
        display: block;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        font-size: 7.2px;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }

    .totals-card {
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
    }

    .totals-head {
        background-color: #eef2f6;
        border-bottom: 1px solid #cbd5e1;
        padding: 4px 8px;
        font-size: 7.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: #0f172a;
    }

    .totals-body {
        padding: 5px 8px;
    }

    .total-row td {
        padding: 2px 0;
        font-size: 8.5px;
    }

    .total-row .k { color: #475569; }
    .total-row .v { color: #0f172a; font-weight: bold; text-align: right; }

    .total-row .divider td {
        border-top: 1px solid #e2e8f0;
        padding-top: 3px;
    }

    .total-highlight {
        background-color: #0f172a;
        color: #ffffff;
        margin-top: 4px;
    }

    .total-highlight td {
        padding: 6px 8px;
        vertical-align: middle;
    }

    .total-highlight .k {
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #e2e8f0;
    }

    .total-highlight .v {
        font-size: 11.5px;
        font-weight: bold;
        color: #ffffff;
        text-align: right;
    }

    /* ===== Bloque DIAN / CUDE ===== */
    .dian-box {
        page-break-inside: avoid;
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        padding: 6px 8px;
        margin-bottom: 6px;
    }

    .qr-cell {
        width: 82px;
        padding-right: 8px;
    }

    .qr-frame {
        width: 74px;
        height: 74px;
        border: 1px solid #cbd5e1;
        padding: 2px;
        background-color: #ffffff;
        text-align: center;
    }

    .qr-empty {
        width: 70px;
        height: 70px;
        line-height: 70px;
        font-size: 7.5px;
        color: #94a3b8;
        text-align: center;
    }

    .cufe-title {
        font-size: 7.8px;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .cufe-value {
        font-family: 'Courier New', Courier, monospace;
        font-size: 7px;
        color: #334155;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 4px 6px;
        margin: 3px 0;
        word-wrap: break-word;
        word-break: break-all;
        line-height: 1.35;
    }

    .dian-note {
        font-size: 7.3px;
        color: #475569;
        line-height: 1.35;
    }

    .dian-note .label {
        font-weight: bold;
        color: #0f172a;
    }

    .legal-note {
        font-size: 7px;
        color: #64748b;
        text-align: justify;
        line-height: 1.35;
    }

    /* ===== Pie de página fijo ===== */
    .note-footer {
        position: fixed;
        bottom: -10mm;
        left: 0;
        right: 0;
        border-top: 1px solid #cbd5e1;
        padding-top: 4px;
        font-size: 7px;
        color: #64748b;
    }

    .note-footer strong {
        color: #0f172a;
        font-size: 7px;
    }
@endpush

@section('content')
    @php
        $factura = $nota->invoice;
        $cliente = $factura?->customer;
        $municipioEmpresa = $empresa?->municipality;
        $resolucion = $factura?->resolution;
        $resolucionHabilitacion =
            $empresa?->enablingResolutions?->firstWhere('status', true) ?? $empresa?->enablingResolutions?->first();
        $actividad =
            $empresa?->economicActivities?->firstWhere('is_main_activity', true) ??
            $empresa?->economicActivities?->first();
        $proveedor = $factura?->billingProvider;
        $medioPago = $nota->paymentMethod;

        $tituloDocumento = $esCredito
            ? ($nota->cude ? 'Nota Crédito Electrónica' : 'Nota Crédito')
            : ($nota->cude ? 'Nota Débito Electrónica' : 'Nota Débito');

        $estadoDoc = $nota->status ?? 'BORRADOR';
        $estadoDocClase = match ($estadoDoc) {
            'EMITIDA', 'APROBADA' => 'estado-aceptada',
            'ANULADA', 'RECHAZADA' => 'estado-rechazada',
            default => 'estado-neutro',
        };

        $estadoDian = $nota->electronic_status;
        $estadoDianTexto = match ($estadoDian) {
            'ACEPTADA_DIAN' => 'ACEPTADA DIAN',
            'PENDIENTE_DIAN' => 'PENDIENTE DIAN',
            'RECHAZADA_DIAN' => 'RECHAZADA DIAN',
            'VALIDADA_SIN_ENVIO' => 'VALIDADA SIN ENVÍO',
            default => 'SIN ENVÍO DIAN',
        };
        $estadoDianClase = match ($estadoDian) {
            'ACEPTADA_DIAN' => 'estado-aceptada',
            'PENDIENTE_DIAN' => 'estado-pendiente',
            'RECHAZADA_DIAN' => 'estado-rechazada',
            'VALIDADA_SIN_ENVIO' => 'estado-validada',
            default => 'estado-neutro',
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
            ? number_format((int) $cliente->document_number, 0, ',', '.') .
                ($cliente->nit_check_digit !== null ? '-' . $cliente->nit_check_digit : '')
            : '—';
        $nombreCliente = $cliente?->full_name ?? '—';
        $ciudadCliente = trim(
            implode(', ', array_filter([$cliente?->municipality?->name, $cliente?->municipality?->department?->name])),
        );
        $ciudadEmpresa = trim(
            implode(', ', array_filter([$municipioEmpresa?->name, $municipioEmpresa?->department?->name])),
        );

        $palabrasEmpresa = preg_split('/\s+/', trim((string) ($empresa?->business_name ?? 'NO'))) ?: ['NO'];
        $inicialesEmpresa = '';
        foreach (array_slice($palabrasEmpresa, 0, 2) as $palabra) {
            $inicialesEmpresa .= mb_strtoupper(mb_substr($palabra, 0, 1));
        }
        $inicialesEmpresa = $inicialesEmpresa ?: 'NO';

        $tasaIva = $nota->subtotal > 0 ? round(($nota->total_tax / $nota->subtotal) * 100, 2) : 0;
        $subtotalBruto = $nota->subtotal + $nota->total_discount;
        $moneda = fn($valor) => '$ ' . number_format((float) $valor, 2, ',', '.');
    @endphp

    <div class="note-page">
        {{-- Pie de página fijo --}}
        <footer class="note-footer">
            <table>
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
        </footer>

        {{-- Encabezado Corporativo --}}
        <header class="header-doc">
            <table class="header-main">
                <tr>
                    <td class="brand-cell">
                        <table class="brand-table">
                            <tr>
                                @if (empty($letterhead ?? null))
                                    <td class="logo-cell">
                                        @if (!empty($data['logo'] ?? null))
                                            <img src="data:image/png;base64,{{ $data['logo'] }}"
                                                alt="Logo {{ $empresa?->business_name }}" class="logo-img" />
                                        @else
                                            <div class="monogram">
                                                <div class="monogram-inner">
                                                    <span class="mono-main">{{ $inicialesEmpresa }}</span>
                                                    <span class="mono-sub">NIT</span>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                @endif
                                <td class="brand-info">
                                    <div class="company-name">{{ $empresa?->business_name ?? 'EMPRESA' }}</div>
                                    <div class="company-nit">NIT: {{ $nitEmpresa }}</div>
                                    @if ($empresa?->taxRegime?->name)
                                        <div class="company-line">{{ $empresa->taxRegime->name }}</div>
                                    @endif
                                    @if ($resolucionHabilitacion)
                                        <div class="company-line">
                                            Habilitación MinTransporte Res. N° {{ $resolucionHabilitacion->resolution_number }}
                                        </div>
                                    @endif
                                    <div class="company-line">
                                        {{ $empresa?->address ?: '—' }}{{ $ciudadEmpresa ? ' · ' . $ciudadEmpresa : '' }}
                                    </div>
                                    <div class="company-line">
                                        Tel: {{ $empresa?->phone ?: '—' }} · {{ $empresa?->email ?: '—' }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>

                    <td class="document-cell">
                        <div class="doc-card">
                            <div class="doc-card-head">
                                <span class="label">{{ $tituloDocumento }}</span>
                                <span class="number">N° {{ $nota->note_number }}</span>
                            </div>
                            <div class="doc-card-body">
                                <table class="doc-row">
                                    <tr>
                                        <td class="k">Fecha Emisión:</td>
                                        <td class="v">
                                            {{ $nota->issue_date?->format('d/m/Y') ?? '—' }}
                                            @if ($nota->created_at)
                                                <span class="muted" style="font-weight: normal;">{{ $nota->created_at->format('h:i A') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="k">Factura Ref.:</td>
                                        <td class="v">{{ $factura?->invoice_number ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="k sep">Estado:</td>
                                        <td class="v sep">
                                            <span class="estado {{ $estadoDocClase }}">{{ str_replace('_', ' ', $estadoDoc) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="k sep">Estado DIAN:</td>
                                        <td class="v sep">
                                            <span class="estado {{ $estadoDianClase }}">{{ $estadoDianTexto }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </header>

        {{-- Datos del Adquiriente y Documento de Referencia --}}
        <section class="client-box">
            <div class="client-head">
                <table>
                    <tr>
                        <td class="client-title" style="width: 65%;">Datos del Adquiriente / Cliente</td>
                        <td class="client-hint" style="width: 35%;">
                            {{ $cliente?->documentType?->prefix ?? 'Doc.' }}: {{ $documentoCliente }}
                        </td>
                    </tr>
                </table>
            </div>
            <div class="client-body">
                <table class="client-grid">
                    <tr>
                        <td style="width: 58%;"><span class="k">Razón Social / Nombre:</span> <span class="v">{{ $nombreCliente }}</span></td>
                        <td style="width: 42%;"><span class="k">{{ $cliente?->documentType?->prefix ?? 'NIT / C.C.' }}:</span> <span class="v">{{ $documentoCliente }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="k">Dirección:</span> <span class="v">{{ $cliente?->address ?: '—' }}</span></td>
                        <td><span class="k">Ciudad / Depto:</span> <span class="v">{{ $ciudadCliente ?: '—' }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="k">Correo Electrónico:</span> <span class="v">{{ $cliente?->email ?: '—' }}</span></td>
                        <td><span class="k">Teléfono / Celular:</span> <span class="v">{{ $cliente?->phone ?: '—' }}</span></td>
                    </tr>
                </table>
            </div>
            <div class="ref-bar">
                <table>
                    <tr>
                        <td style="width: 34%;">
                            <span class="k">Factura Afectada:</span>
                            <span class="v">{{ $factura?->invoice_number ?? '—' }}</span>
                            @if ($factura?->issue_date)
                                <span class="muted">({{ $factura->issue_date->format('d/m/Y') }})</span>
                            @endif
                        </td>
                        <td style="width: 40%;">
                            <span class="k">Concepto:</span>
                            <span class="v">{{ $conceptoTexto }}</span>
                        </td>
                        <td style="width: 26%;" class="txt-right">
                            <span class="k">Medio de Pago:</span>
                            <span class="v">{{ $medioPago?->name ?? '—' }}</span>
                        </td>
                    </tr>
                </table>
            </div>
        </section>

        {{-- Detalle de Ítems (suma exacta de anchos = 100%) --}}
        <section>
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="txt-center" style="width: 4%;">#</th>
                        <th class="txt-left" style="width: 43%;">Descripción del Concepto / Ajuste</th>
                        <th class="txt-center" style="width: 7%;">Cant.</th>
                        <th class="txt-center" style="width: 6%;">U/M</th>
                        <th class="txt-right" style="width: 13%;">Vr. Unitario</th>
                        <th class="txt-right" style="width: 9%;">Dcto.</th>
                        <th class="txt-center" style="width: 5%;">IVA</th>
                        <th class="txt-right" style="width: 13%;">Total Línea</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($nota->items as $indice => $item)
                        <tr class="{{ $indice % 2 === 1 ? 'alt' : '' }}">
                            <td class="txt-center bold muted">{{ str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                <div class="item-title">{{ $item->description }}</div>
                            </td>
                            <td class="txt-center">{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                            <td class="txt-center muted">{{ $item->measurementUnit?->code ?? 'UN' }}</td>
                            <td class="txt-right">{{ $moneda($item->unit_price) }}</td>
                            <td class="txt-right muted">{{ (float) $item->discount_amount > 0 ? $moneda($item->discount_amount) : '—' }}</td>
                            <td class="txt-center muted">{{ rtrim(rtrim(number_format((float) $item->tax_rate, 2, '.', ','), '0'), '.') }}%</td>
                            <td class="txt-right bold navy">{{ $moneda($item->line_total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="txt-center muted" style="padding: 12px;">Sin ítems registrados en este documento.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Totales, Valor en Letras y Motivo --}}
        <section class="amounts-section">
            <table>
                <tr>
                    <td class="amounts-left align-top">
                        <div class="letters-box">
                            <span class="label">Valor Total en Letras</span>
                            <div class="value">
                                {{ \App\Support\Billing\NumeroALetras::formatear((float) $nota->total) }}.
                            </div>
                        </div>

                        <div class="observations">
                            <span class="label">Motivo / Observación de la {{ $esCredito ? 'Nota Crédito' : 'Nota Débito' }}</span>
                            {{ $nota->observation ?: 'Sin observaciones adicionales registradas.' }}
                        </div>
                    </td>

                    <td class="amounts-right align-top">
                        <div class="totals-card">
                            <div class="totals-head">Resumen de Liquidación</div>
                            <div class="totals-body">
                                <table class="total-row">
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
                                        <tr class="divider">
                                            <td class="k">Retenciones Aplicadas:</td>
                                            <td class="v">-{{ $moneda($nota->total_withholding) }}</td>
                                        </tr>
                                    @endif
                                </table>

                                <table class="total-highlight">
                                    <tr>
                                        <td class="k">Total {{ $esCredito ? 'Nota Crédito' : 'Nota Débito' }}</td>
                                        <td class="v">{{ $moneda($nota->total) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </section>

        {{-- Bloque DIAN / CUDE --}}
        <section class="dian-box">
            <table>
                <tr>
                    <td class="qr-cell align-top">
                        <div class="qr-frame">
                            @if (!empty($data['qr'] ?? null))
                                <img src="data:image/png;base64,{{ $data['qr'] }}"
                                    alt="QR DIAN {{ $nota->cude }}"
                                    style="width: 70px; height: 70px; display: block;" />
                            @else
                                <div class="qr-empty">QR pendiente</div>
                            @endif
                        </div>
                    </td>
                    <td class="align-top">
                        <table style="margin-bottom: 3px;">
                            <tr>
                                <td class="cufe-title" style="width: 62%;">Código Único de Documento Electrónico (CUDE)</td>
                                <td class="txt-right" style="width: 38%;">
                                    @if ($nota->cude)
                                        <span class="estado estado-aceptada">Firma Digital Validada SHA-384</span>
                                    @else
                                        <span class="estado estado-neutro">Sin transmisión electrónica</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <div class="cufe-value">
                            {{ $nota->cude ?: 'CUDE no generado: documento interno o pendiente de transmisión a la DIAN.' }}
                        </div>
                        <div class="dian-note">
                            @if ($factura?->cufe)
                                <span class="label">CUFE Factura Referenciada:</span>
                                <span class="mono">{{ $factura->cufe }}</span>.
                            @endif
                            @if ($resolucion)
                                <span class="label">Resolución Factura Ref.:</span> N° {{ $resolucion->resolution_number }}
                                ({{ $resolucion->invoice_prefix }}).
                            @endif
                            @if ($actividad)
                                <span class="label">CIIU Principal:</span> {{ $actividad->activity_code }}{{ $actividad->activity_description ? ' (' . $actividad->activity_description . ')' : '' }}.
                            @endif
                            @if ($cliente?->taxResponsibility)
                                <span class="label">Resp. Fiscal Adquiriente:</span> {{ $cliente->taxResponsibility->code }}.
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </section>

        <section class="legal-note">
            Este documento ajusta contable y fiscalmente la factura de venta referenciada
            ({{ $factura?->invoice_number ?? '—' }}) conforme a lo dispuesto en la normativa tributaria vigente de la DIAN.
        </section>
    </div>
@endsection
