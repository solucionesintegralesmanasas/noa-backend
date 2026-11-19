<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato de Alquiler</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 15px;
        }

        .header h1 {
            margin: 0;
            font-size: 18pt;
            color: #2c3e50;
        }

        .header p {
            margin: 5px 0 0;
            font-size: 10pt;
            color: #7f8c8d;
        }

        .info-section {
            margin-bottom: 20px;
        }

        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            width: 25%;
            font-weight: bold;
            padding: 5px;
            background-color: #ecf0f1;
            border: 1px solid #bdc3c7;
        }

        .info-value {
            display: table-cell;
            width: 25%;
            padding: 5px;
            border: 1px solid #bdc3c7;
        }

        .clauses {
            margin-top: 20px;
            text-align: justify;
        }

        .clause-title {
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
        }

        .clause-text {
            margin-bottom: 10px;
            text-indent: 20px;
        }

        .table-container {
            margin-top: 25px;
        }

        .table-title {
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 10px;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }

        th, td {
            border: 1px solid #bdc3c7;
            padding: 6px 4px;
            text-align: center;
        }

        th {
            background-color: #34495e;
            color: white;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .totals {
            margin-top: 15px;
            text-align: right;
            font-weight: bold;
        }

        .signatures {
            margin-top: 50px;
            display: table;
            width: 100%;
        }

        .signature-box {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding-top: 60px;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 5px;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRATO DE ALQUILER DE VEHÍCULO</h1>
        <p>Noa Solutions - Sistema de Gestión de Flotilla</p>
    </div>

    <div class="info-section">
        <h3 style="margin-top:0;">Datos del Contrato</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Número de Contrato:</div>
                <div class="info-value">{{ $contract->contract_number ?? $contract->uuid }}</div>
                <div class="info-label">Fecha de Emisión:</div>
                <div class="info-value">{{ $fechaEmision }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado:</div>
                <div class="info-value">{{ ucfirst($contract->status) }}</div>
                <div class="info-label">Plazo:</div>
                <div class="info-value">{{ $contract->monthly_term }} meses</div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h3>Datos del Vehículo</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Placa:</div>
                <div class="info-value">{{ $contract->vehicle->vehicle_license_plate ?? '-' }}</div>
                <div class="info-label">Marca:</div>
                <div class="info-value">{{ $contract->vehicle->brand->description ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Clase de Vehículo:</div>
                <div class="info-value">{{ $contract->vehicle->vehicleClass->description ?? '-' }}</div>
                <div class="info-label">Modelo:</div>
                <div class="info-value">{{ $contract->vehicle->model ?? '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Capacidad de Pasajeros:</div>
                <div class="info-value">{{ $contract->vehicle->passenger_capacity ?? '-' }} pasajeros</div>
                <div class="info-label">Tipo de Servicio:</div>
                <div class="info-value">{{ $contract->vehicle->type_of_service ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h3>Datos del Propietario</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre:</div>
                <div class="info-value">{{ $contract->owner->owner_name ?? '-' }}</div>
                <div class="info-label">Documento:</div>
                <div class="info-value">{{ $contract->owner->document_number ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h3>Condiciones Económicas</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Fecha Inicio:</div>
                <div class="info-value">{{ $contract->start_date->format('d/m/Y') }}</div>
                <div class="info-label">Fecha Fin:</div>
                <div class="info-value">{{ $contract->end_date->format('d/m/Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Canon Mensual:</div>
                <div class="info-value">${{ number_format($contract->monthly_fee, 2) }}</div>
                <div class="info-label">Valor Total:</div>
                <div class="info-value">${{ number_format($canonTotal, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="clauses">
        <h3>CLÁUSULAS</h3>

        <div class="clause-title">PRIMERA - OBJETO</div>
        <div class="clause-text">
            El ARRENDADOR entrega en arriendo al ARRENDATARIO el vehículo descrito anteriormente,
            para ser utilizado exclusivamente para fines de transporte de personal y logística operativa.
        </div>

        <div class="clause-title">SEGUNDA - CANON Y FORMA DE PAGO</div>
        <div class="clause-text">
            El ARRENDATARIO se obliga a pagar un canon mensual de ${{ number_format((float) $contract->monthly_fee, 2) }}
            (${{ number_format((float) $contract->monthly_fee, 2) }} PESOS M/L), pagadero por anticipado
            cada mes. Los valores incluyen mantenimiento básico y seguro.
        </div>

        <div class="clause-title">TERCERA - OBLIGACIONES DEL ARRENDATARIO</div>
        <div class="clause-text">
            1. Realizar los pagos puntuales según cronograma establecido.
            2. Utilizar el vehículo conforme a su naturaleza y destino señalado.
            3. Mantener el vehículo en buen estado y realizar las reparaciones necesarias.
            4. No subarrendar el vehículo sin autorización escrita.
            5. Cubrir multas, infracciones de tránsito y daños causados durante el uso.
        </div>

        <div class="clause-title">CUARTA - DURACIÓN</div>
        <div class="clause-text">
            El presente contrato tendrá una vigencia de {{ $contract->monthly_term }} ({{ $contract->monthly_term }}) meses,
            iniciándose el {{ $contract->start_date->format('d') }} de {{ $contract->start_date->format('F') }} de
            {{ $contract->start_date->format('Y') }} y finalizando el {{ $contract->end_date->format('d') }} de
            {{ $contract->end_date->format('F') }} de {{ $contract->end_date->format('Y') }}, salvo renovación expresa.
        </div>

        <div class="clause-title">QUINTA - TERMINACIÓN</div>
        <div class="clause-text">
            Cualquiera de las partes podrá dar por terminado este contrato mediante notificación
            escrita con anticipación de treinta (30) días calendario. En caso de incumplimiento de
            cualquiera de las obligaciones aquí establecidas, la parte afectada podrá rescindir el
            contrato de pleno derecho.
        </div>

        <div class="clause-title">SEXTA - MORA</div>
        <div class="clause-text">
            El incumplimiento en el pago del canon mensual generará intereses moratorios del
            doscientos por ciento (200%) sobre la tasa activa bancaria vigente, calculados desde
            la fecha de vencimiento hasta el efectivo pago.
        </div>
    </div>

    <div class="table-container">
        <div class="table-title">ANEXO: CRONOGRAMA DE CUOTAS</div>
        <table>
            <thead>
                <tr>
                    <th>N° Cuota</th>
                    <th>Fecha Venc.</th>
                    <th>Canón Original</th>
                    <th>Saldo Anterior</th>
                    <th>Descuentos</th>
                    <th>Valor Neto</th>
                    <th>Pagado</th>
                    <th>Saldo Pend.</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contract->installments as $installment)
                <tr>
                    <td>{{ $installment->installment_number }}</td>
                    <td>{{ $installment->due_date->format('d/m/Y') }}</td>
                    <td>${{ number_format($installment->gross_amount, 2) }}</td>
                    <td>${{ number_format($installment->previous_balance, 2) }}</td>
                    <td>${{ number_format($installment->discount_amount, 2) }}</td>
                    <td>${{ number_format($installment->net_amount, 2) }}</td>
                    <td>${{ number_format($installment->paid_amount, 2) }}</td>
                    <td>${{ number_format($installment->pending_balance, 2) }}</td>
                    <td>{{ ucfirst($installment->status) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="totals">
            @php
                $totalContratoPdf = $contract->installments->sum('gross_amount') - $contract->installments->sum('discount_amount');
                $totalPagadoPdf = $contract->installments->sum('paid_amount');
                $totalPendientePdf = max(0, $totalContratoPdf - $totalPagadoPdf);
            @endphp
            Total Pagado: ${{ number_format($totalPagadoPdf, 2) }} |
            Total Pendiente: ${{ number_format($totalPendientePdf, 2) }}
        </div>
    </div>

    @if($contract->discounts && $contract->discounts->count() > 0)
    <div class="table-container">
        <div class="table-title">REGISTRO DE DESCUENTOS</div>
        <table>
            <thead>
                <tr>
                    <th>Mes / Cuota</th>
                    <th>Fecha Descuento</th>
                    <th>Concepto / Detalle</th>
                    <th>Valor Descuento</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contract->discounts as $discount)
                <tr>
                    <td>Cuota #{{ $discount->installment->installment_number ?? '-' }} ({{ $discount->installment?->due_date?->format('m/Y') ?? '-' }})</td>
                    <td>{{ $discount->discount_date->format('d/m/Y') }}</td>
                    <td>{{ $discount->concept }}</td>
                    <td>${{ number_format((float) $discount->amount, 2) }}</td>
                    <td>{{ $discount->observations ?: '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line">POR NOA SOLUTIONS<br>(ARRENDADOR)</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">{{ $contract->owner->owner_name ?? 'PROPIETARIO' }}<br>(ARRENDATARIO)</div>
        </div>
    </div>
</body>
</html>
