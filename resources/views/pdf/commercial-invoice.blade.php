<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $documento->invoice_type }} - {{ $documento->invoice_number }}</title>
    <style>
        @page { size: letter; margin: 1cm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background-color: #eee; }
        .sin-borde td, .sin-borde th { border: none; }
        .derecha { text-align: right; }
        .titulo { font-size: 16px; font-weight: bold; }
        .seccion { font-size: 11px; font-weight: bold; background-color: #eee; padding: 3px 6px; margin: 10px 0 4px 0; }
        .leyenda { margin-top: 12px; padding: 8px; border: 1px solid #999; background-color: #f5f5f5; }
        .firmas td { height: 60px; vertical-align: bottom; }
    </style>
</head>
<body>
    {{-- 1. ENCABEZADO: emisor + documento --}}
    <table class="sin-borde">
        <tr>
            <td style="width: 60%;">
                <span class="titulo">{{ $empresa->business_name ?? 'Empresa' }}</span><br>
                NIT {{ $empresa->document_number ?? '' }}-{{ $empresa->verification_digit ?? '' }}<br>
                {{ $empresa->address ?? '' }}<br>
                Tel: {{ $empresa->phone ?? '' }} · {{ $empresa->email ?? '' }}
            </td>
            <td class="derecha" style="width: 40%;">
                <span class="titulo">{{ $documento->invoice_type === 'COTIZACION' ? 'COTIZACIÓN' : 'FACTURA DE VENTA' }}</span><br>
                <strong>No. {{ $documento->invoice_number }}</strong><br>
                Emitida: {{ $documento->issue_date?->format('d/m/Y') }}<br>
                @if($documento->esCotizacion() && $documento->valid_until)
                    Vigente hasta: {{ $documento->valid_until->format('d/m/Y') }}<br>
                @endif
                @if($documento->esFactura() && $documento->due_date)
                    Vence: {{ $documento->due_date->format('d/m/Y') }}<br>
                @endif
                Estado: {{ $documento->status }}
            </td>
        </tr>
    </table>

    {{-- 2. CLIENTE --}}
    <div class="seccion">Cliente</div>
    <table class="sin-borde">
        <tr>
            <td style="width: 60%;">
                <strong>{{ $documento->customer?->company_name ?? trim(($documento->customer?->first_name ?? '').' '.($documento->customer?->last_name ?? '')) }}</strong><br>
                {{ $documento->customer?->address ?? '' }}<br>
                {{ $documento->customer?->email ?? '' }} {{ $documento->customer?->phone ?? '' }}
            </td>
            <td style="width: 40%;">
                Documento: {{ $documento->customer?->document_number ?? '' }}
            </td>
        </tr>
    </table>

    {{-- 3. ÍTEMS --}}
    <div class="seccion">Detalle</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Descripción</th>
                <th>Cant.</th>
                <th>Precio</th>
                <th>Descuento</th>
                <th>IVA %</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documento->items as $item)
                <tr>
                    <td>{{ $item->order_index }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="derecha">{{ number_format($item->quantity, 2) }}</td>
                    <td class="derecha">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="derecha">{{ number_format($item->discount_amount, 2) }}</td>
                    <td class="derecha">{{ number_format($item->tax_rate, 2) }}</td>
                    <td class="derecha">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 4. TOTALES --}}
    <table class="sin-borde">
        <tr><td class="derecha"><strong>Subtotal:</strong></td><td class="derecha">{{ number_format($documento->subtotal, 2) }}</td></tr>
        <tr><td class="derecha"><strong>Descuentos:</strong></td><td class="derecha">{{ number_format($documento->total_discount, 2) }}</td></tr>
        <tr><td class="derecha"><strong>Impuestos:</strong></td><td class="derecha">{{ number_format($documento->total_tax, 2) }}</td></tr>
        <tr><td class="derecha"><strong>Retenciones:</strong></td><td class="derecha">{{ number_format($documento->total_withholding, 2) }}</td></tr>
        <tr><td class="derecha"><strong>Total:</strong></td><td class="derecha"><strong>{{ number_format($documento->invoice_total, 2) }}</strong></td></tr>
        @if($documento->esFactura())
            <tr><td class="derecha"><strong>Abonado:</strong></td><td class="derecha">{{ number_format($documento->amount_paid, 2) }}</td></tr>
            <tr><td class="derecha"><strong>Saldo:</strong></td><td class="derecha">{{ number_format($documento->invoice_total - $documento->amount_paid, 2) }}</td></tr>
        @endif
    </table>

    {{-- 5. CONDICIONES --}}
    @if($documento->notes || $documento->terms)
        <div class="seccion">Condiciones</div>
        @if($documento->notes)<p>{{ $documento->notes }}</p>@endif
        @if($documento->terms)<p>{{ $documento->terms }}</p>@endif
    @endif

    @if($documento->esCotizacion())
        <div class="seccion">Aceptación del cliente</div>
        <table class="sin-borde firmas">
            <tr>
                <td style="width: 50%;">Firma: ___________________________</td>
                <td>Fecha: ___________________________</td>
            </tr>
        </table>
    @endif

    <div class="leyenda">
        Documento interno — pendiente de validación DIAN. Sin CUFE.
    </div>
</body>
</html>
