@extends('pdf.layouts.base')

{{-- Estilos propios del documento comercial. Van en crudo porque la base los inyecta dentro de su <style>. --}}
@push('styles')
    .title-cell {
        text-align: center;
    }

    .title-cell .doc-title {
        font-size: 13px;
        font-weight: bold;
        color: var(--pdf-banda-texto);
    }

    .title-cell .responsible {
        font-size: 8px;
        margin-top: 2px;
        color: var(--pdf-banda-texto);
    }

    .monto {
        text-align: right;
        white-space: nowrap;
    }

    .totales td {
        border: none;
        padding: 2px 6px;
        font-size: 9px;
    }

    .totales .etiqueta {
        text-align: right;
        font-weight: bold;
    }

    .totales .valor {
        text-align: right;
        width: 130px;
    }

    .nota-interna {
        margin-top: 8px;
        padding: 6px 8px;
        border: 0.5px solid var(--pdf-borde);
        background-color: var(--pdf-tenue);
        font-size: 9px;
    }

    .firmas td {
        height: 55px;
        vertical-align: bottom;
        border-left: none;
        border-right: none;
    }
@endpush

{{-- Sin sección 'watermarks' propia: usa la default de la base (logo_fondo,
     se suprime con membrete/modo limpio y nunca deja un img roto). --}}

{{-- Encabezado propio: logo de la empresa y título del documento. --}}
@section('header')
    <table class="header-table" style="margin-bottom: 4px;">
        <tr>
            <td style="width: 140px; text-align: center; vertical-align: middle; padding: 5px; border-right: none;">
                @if (!empty($data['logo']))
                    <img class="logo-header" src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo">
                @endif
            </td>
            <td class="title-cell" style="border-left: none;">
                <div class="doc-title">{{ $documento->invoice_type === 'COTIZACION' ? 'COTIZACIÓN' : 'FACTURA DE VENTA' }} {{ $documento->invoice_number }}</div>
                <div class="responsible">Emitida: {{ $documento->issue_date?->format('d/m/Y') }} · Estado: {{ $documento->status }}</div>
            </td>
        </tr>
    </table>
@endsection

@section('content')
    <div class="page">
        {{-- 1. EMISOR --}}
        <table class="section-header">
            <tr><td>Emisor</td></tr>
        </table>
        <table class="data-table">
            <tr>
                <td class="label">Empresa</td>
                <td class="value"><strong>{{ $empresa->business_name ?? '' }}</strong></td>
                <td class="label">NIT</td>
                <td class="value">{{ $empresa->document_number ?? '' }}-{{ $empresa->verification_digit ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td class="value">{{ $empresa->address ?? '' }}</td>
                <td class="label">Teléfono</td>
                <td class="value">{{ $empresa->phone ?? '' }} {{ $empresa->email ?? '' }}</td>
            </tr>
        </table>

        {{-- 2. CLIENTE --}}
        <table class="section-header">
            <tr><td>Cliente</td></tr>
        </table>
        <table class="data-table">
            <tr>
                <td class="label">Nombre</td>
                <td class="value"><strong>{{ $documento->customer?->company_name ?? trim(($documento->customer?->first_name ?? '').' '.($documento->customer?->last_name ?? '')) }}</strong></td>
                <td class="label">Documento</td>
                <td class="value">{{ $documento->customer?->document_number ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td class="value">{{ $documento->customer?->address ?? '' }}</td>
                <td class="label">Contacto</td>
                <td class="value">{{ $documento->customer?->email ?? '' }} {{ $documento->customer?->phone ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Vigencia</td>
                <td class="value">{{ $documento->valid_until?->format('d/m/Y') ?? '—' }}</td>
                <td class="label">Vencimiento</td>
                <td class="value">{{ $documento->due_date?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        </table>

        {{-- 3. DETALLE --}}
        <table class="section-header">
            <tr><td>Detalle del servicio</td></tr>
        </table>
        <table class="docs-table">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Descripción</th>
                    <th style="width: 55px;">Cant.</th>
                    <th style="width: 75px;">Precio</th>
                    <th style="width: 75px;">Descuento</th>
                    <th style="width: 50px;">IVA %</th>
                    <th style="width: 85px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($documento->items as $item)
                    <tr>
                        <td>{{ $item->order_index }}</td>
                        <td style="text-align: left;">{{ $item->description }}</td>
                        <td class="monto">{{ number_format($item->quantity, 2) }}</td>
                        <td class="monto">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="monto">{{ number_format($item->discount_amount, 2) }}</td>
                        <td class="monto">{{ number_format($item->tax_rate, 2) }}</td>
                        <td class="monto">{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- 4. TOTALES --}}
        <table class="totales">
            <tr><td class="etiqueta">Subtotal:</td><td class="valor">{{ number_format($documento->subtotal, 2) }}</td></tr>
            <tr><td class="etiqueta">Descuentos:</td><td class="valor">{{ number_format($documento->total_discount, 2) }}</td></tr>
            <tr><td class="etiqueta">Impuestos:</td><td class="valor">{{ number_format($documento->total_tax, 2) }}</td></tr>
            <tr><td class="etiqueta">Retenciones:</td><td class="valor">{{ number_format($documento->total_withholding, 2) }}</td></tr>
            <tr><td class="etiqueta">Total:</td><td class="valor"><strong>{{ number_format($documento->invoice_total, 2) }}</strong></td></tr>
            @if($documento->esFactura())
                <tr><td class="etiqueta">Abonado:</td><td class="valor">{{ number_format($documento->amount_paid, 2) }}</td></tr>
                <tr><td class="etiqueta">Saldo:</td><td class="valor">{{ number_format($documento->invoice_total - $documento->amount_paid, 2) }}</td></tr>
            @endif
        </table>

        {{-- 5. CONDICIONES --}}
        @if($documento->notes || $documento->terms)
            <table class="section-header">
                <tr><td>Condiciones</td></tr>
            </table>
            <table class="data-table">
                @if($documento->notes)
                    <tr><td class="label">Notas</td><td class="value">{{ $documento->notes }}</td></tr>
                @endif
                @if($documento->terms)
                    <tr><td class="label">Términos</td><td class="value">{{ $documento->terms }}</td></tr>
                @endif
            </table>
        @endif

        @if($documento->esCotizacion())
            <table class="section-header">
                <tr><td>Aceptación del cliente</td></tr>
            </table>
            <table class="data-table firmas">
                <tr>
                    <td style="width: 50%;">Firma: ___________________________</td>
                    <td>Fecha: ___________________________</td>
                </tr>
            </table>
        @endif

        <div class="nota-interna">
            Documento interno — pendiente de validación DIAN. Sin CUFE.
        </div>
    </div>
@endsection
