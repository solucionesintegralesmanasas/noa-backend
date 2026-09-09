<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recibo de Caja - {{ $charge->charge_internal_id ?? ($charge->payment_reference ?? 'N/A') }}</title>
    <style>
        @page {
            size: letter;
            margin: 0cm 0cm;
        }

        /**
        * Define los márgenes reales del contenido de tu PDF
        * Aquí arreglarás los márgenes del encabezado y pie de página
        * De tu imagen de fondo.
        **/
        body {
            margin-top: 2.54cm;
            margin-bottom: 2.54cm;
            margin-left: 2.54cm;
            margin-right: 2.54cm;
            font-family: Arial, sans-serif;
            font-size: 10px;
            padding: 0;
            color: #000;
            background-color: #fff;
        }

        #watermark {
            position: fixed;
            top: 25%;
            left: 15%;
            width: 70%;
            height: auto;
            z-index: -1000;
        }

        #watermark img {
            width: 100%;
            height: auto;
            opacity: 0.15;
        }

        .container {
            padding: 4px;
            min-height: 95%;
        }

        .header {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }

        .header-cell {
            display: table-cell;
            vertical-align: middle;
        }

        .logo-box {
            width: 25%;
            text-align: left;
            padding: 10px;
            border-right: none;
        }

        .logo-box img {
            max-width: 160px;
            max-height: 60px;
        }

        .logo-name {
            font-size: 14px;
            font-weight: bold;
        }

        .logo-nit {
            font-size: 10px;
            font-weight: bold;
        }

        .company-box {
            width: 50%;
            background: transparent;
            color: #000000;
            text-align: center;
            padding: 12px 10px;
            border: 1px solid #000;
        }

        .company-box h2 {
            margin: 0;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .company-box p {
            margin: 3px 0;
            font-size: 10px;
        }

        .invoice-box {
            width: 25%;
            background: transparent;
            color: #000000;
            padding: 12px 10px;
            border: 1px solid #000;
            border-left: none;
            text-align: center;
        }

        .invoice-box h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            text-transform: uppercase;
        }

        .invoice-box p {
            margin: 5px 0;
            font-size: 11px;
        }

        .section-title {
            background-color: #ffffff;
            color: #000000;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
            padding: 10px 0 5px 0;
            margin-top: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #000000;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .data-table td {
            padding: 5px 8px;
            font-size: 10px;
            vertical-align: middle;
        }

        .data-table td strong {
            display: inline-block;
            width: 120px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-top: 10px;
        }

        .items-table th {
            background-color: #ffffff;
            font-size: 10px;
            padding: 6px;
            color: #000000;
            border-bottom: 1px solid #000000;
        }

        .items-table td {
            padding: 8px;
            font-size: 10px;
            vertical-align: middle;
        }

        .payment-box {
            display: table;
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }

        .payment-left {
            display: table-cell;
            width: 50%;
            background-color: #ffffff;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 13px;
            vertical-align: middle;
        }

        .payment-right {
            display: table-cell;
            width: 35%;
            padding: 10px 15px;
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            vertical-align: middle;
        }

        .payment-qr {
            display: table-cell;
            width: 15%;
            text-align: center;
            vertical-align: middle;
            padding: 5px;
        }

        .payment-qr img {
            width: 80px;
            height: 80px;
        }

        .footer-quote {
            padding: 10px;
            margin-top: 25px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }

        .barcode-section {
            text-align: center;
            margin-top: 25px;
            display: table;
            width: 100%;
        }

        .barcode-left {
            display: table-cell;
            width: 30%;
            vertical-align: middle;
            text-align: left;
            padding-left: 20px;
        }

        .barcode-right {
            display: table-cell;
            width: 70%;
            text-align: center;
        }

        .barcode {
            font-family: 'Courier New', Courier, monospace;
            font-size: 32px;
            letter-spacing: -2px;
            transform: scaleY(1.5);
            display: inline-block;
            font-weight: bold;
        }

        .barcode-text {
            margin-top: 15px;
            font-size: 9px;
            letter-spacing: 1px;
        }

        #watermark {
            position: absolute;
            top: 25%;
            left: 15%;
            width: 70%;
            height: auto;
            z-index: -1000;
            opacity: 0.15;
        }

        #watermark img {
            width: 100%;
            height: auto;
        }

        .pay-stamp {
            position: absolute;
            top: 400px;
            right: 40px;
            font-size: 24px;
            font-weight: bold;
            color: #d9534f;
            border: 3px solid #d9534f;
            padding: 10px 20px;
            border-radius: 8px;
            transform: rotate(-15deg);
            opacity: 0.8;
            z-index: 100;
        }
    </style>
</head>

<body>
    @if (!empty($data['logo']))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $data['logo'] }}" alt="Watermark">
        </div>
    @endif

    @php
        $affiliate = $charge->vehicle->thirdParty ?? null;
        $company = $charge->company ?? null;
        $receiptNumber = $charge->charge_internal_id ?? ($charge->payment_reference ?? 'N/A');
        $status = $charge->status ?? 'PENDIENTE';
    @endphp

    <div class="container">

        <!-- HEADER ENCAZABEZADO -->
        <div class="header">
            <div class="header-cell logo-box">
                @if (!empty($data['logo']))
                    <img src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo">
                @else
                    <div class="logo-name">{{ $data['company_name'] ?? 'Empresa' }}</div>
                @endif
            </div>
            <div class="header-cell company-box">
                <h2>{{ $data['company_name'] ?? 'Empresa de Transporte' }}</h2>
                <p>{{ $company->address ?? '' }} - {{ $company->municipality->name ?? '' }} | Tel:
                    {{ $company->phone ?? '' }}</p>
                <p>Email: {{ $company->email ?? '' }}</p>
                <p>Oficina Administrativa: {{ $company->address ?? '' }}</p>
            </div>
            <div class="header-cell invoice-box">
                <h3>Recibo de Caja</h3>
                <p><strong>Número:</strong> <br> {{ $receiptNumber }}</p>
                <p><strong>Expedido el:</strong> <br>
                    {{ $data['payment_date'] ?? \Carbon\Carbon::now()->format('Y-m-d') }}</p>
            </div>
        </div>

        @if ($status === 'PAGADO')
            <div class="pay-stamp">PAGADO</div>
        @elseif($status === 'ANULADO')
            <div class="pay-stamp" style="color: #6c757d; border-color: #6c757d;">ANULADO</div>
        @elseif($status === 'PENDIENTE')
            <div class="pay-stamp" style="color: #f0ad4e; border-color: #f0ad4e;">PENDIENTE</div>
        @endif

        <!-- DATOS DEL CLIENTE -->
        <div class="section-title">DATOS DEL CLIENTE / PROPIETARIO</div>
        <table class="data-table">
            <tr>
                <td style="width: 50%;"><strong>Nombre / Razón Social:</strong>
                    {{ $affiliate ? $affiliate->company_name ?? $affiliate->first_name . ' ' . $affiliate->last_name : 'N/A' }}
                </td>
                <td style="width: 50%;"><strong>Municipio:</strong> {{ $company->municipality->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Nit / CC:</strong> {{ $affiliate->document_number ?? 'N/A' }}</td>
                <td><strong>Teléfono:</strong> {{ $affiliate->phone ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Dirección del Cliente:</strong> {{ $affiliate->address ?? 'N/A' }}</td>
                <td><strong>Dirección de correo:</strong> {{ $affiliate->email ?? 'N/A' }}</td>
            </tr>
        </table>

        <!-- DATOS DEL VEHICULO -->
        <div class="section-title">INFORMACIÓN DEL VEHÍCULO</div>
        <table class="data-table">
            <tr>
                <td style="width: 25%;"><strong>Placa:</strong> {{ $data['vehicle_license_plate'] }}</td>
                <td style="width: 25%;"><strong>Clase:</strong> {{ $data['vehicle_type'] }}</td>
                <td style="width: 25%;"><strong>Marca:</strong> {{ $data['vehicle_brand'] }}</td>
                <td style="width: 25%;"><strong>Modelo:</strong> {{ $data['vehicle_model'] }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Capacidad Pasajeros:</strong> {{ $data['vehicle_capacity'] }}</td>
                <td colspan="2"><strong>Forma de Pago Registrada:</strong> {{ $charge->payment_method ?? 'N/A' }}
                </td>
            </tr>
        </table>

        <!-- DETALLES DEL SERVICIO / LIQUIDACION -->
        <div class="section-title">LIQUIDACIÓN DE COBROS</div>
        <table class="items-table">
            <tr>
                <th style="width: 15%;">Periodo Facturado</th>
                <th style="width: 50%;">Concepto</th>
                <th style="width: 15%;">Tasa Mora</th>
                <th style="width: 20%;">Valor</th>
            </tr>
            <tr>
                <td>{{ $charge->period_date ? \Carbon\Carbon::parse($charge->period_date)->format('M Y') : 'N/A' }}
                </td>
                <td style="text-align: left;">{{ $charge->concept ?? 'Cuota de Administración' }}</td>
                <td>{{ $charge->late_fee_percentage ?? '0.00' }}%</td>
                <td style="text-align: right;">$ {{ number_format($charge->amount ?? 0, 0, ',', '.') }}</td>
            </tr>
            @if (!empty($charge->notes))
                <tr>
                    <td colspan="4" style="text-align: left; font-size: 9px; padding-top: 15px; color: #555;">
                        <strong>Observaciones:</strong> {{ $charge->notes }}
                    </td>
                </tr>
            @endif
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold; border-top: 1px solid #000000;">SubTotal
                    Mes:</td>
                <td style="text-align: right; border-top: 1px solid #000000;">$
                    {{ number_format($charge->amount ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold;">Saldo en mora:</td>
                <td style="text-align: right;">$ 0</td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold;">TOTAL A
                    PAGAR:</td>
                <td style="text-align: right; font-weight: bold;">$
                    {{ number_format($charge->amount ?? 0, 0, ',', '.') }}</td>
            </tr>
        </table>

        <!-- MENSAJE -->
        <div class="footer-quote">
            "CONTRIBUYENDO AL DESARROLLO DEL SECTOR TRANSPORTE CON SEGURIDAD Y CUMPLIMIENTO."
        </div>

        <!-- TOTALS Y FECHAS -->
        <div class="payment-box">
            <div class="payment-left">
                Pague Oportunamente hasta el día: <br><br>
                <span
                    style="font-size: 16px;">{{ $charge->due_date ? \Carbon\Carbon::parse($charge->due_date)->format('d-M.-Y') : 'N/A' }}</span><br><br>
                <span style="font-size: 9px; font-weight: normal;">El pago posterior a esta fecha causa intereses por
                    mora por cada día de atraso.</span>
            </div>
            <div class="payment-right">
                Total a pagar este recibo: <br><br>
                <span style="font-size: 24px;">$ {{ number_format($charge->amount ?? 0, 0, ',', '.') }}</span>
            </div>
            <div class="payment-qr">
                <img src="{{ $data['qr_code'] }}" alt="Código QR">
            </div>
        </div>
    </div>
</body>

</html>
