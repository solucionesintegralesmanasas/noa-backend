@php
    $empresa = $company ?? ($contract->company ?? null);
    $nombreEmpresa = $empresa->business_name ?? '';
    $nitEmpresa = trim((string) ($empresa->document_number ?? ''));
    if ($nitEmpresa !== '' && isset($empresa->verification_digit) && $empresa->verification_digit !== null && $empresa->verification_digit !== '') {
        $nitEmpresa .= '-' . $empresa->verification_digit;
    }
    $empresaSiglas = $empresa_siglas ?? \App\Services\Pdf\PdfService::siglasEmpresa((string) $nombreEmpresa);
    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
    $sinMarcaVisual = ! empty($letterhead ?? null) || ! empty($ocultar_marca ?? false);

    $estadosContrato = [
        'active' => 'ACTIVO',
        'finished' => 'FINALIZADO',
        'cancelled' => 'CANCELADO',
    ];
    $estadosCuota = [
        'paid' => 'PAGADA',
        'partial' => 'PARCIAL',
        'overdue' => 'VENCIDA',
        'pending' => 'PENDIENTE',
        'cancelled' => 'CANCELADA',
    ];
    $estadoTexto = $estadosContrato[strtolower((string) ($contract->status ?? ''))] ?? strtoupper((string) ($contract->status ?? '—'));

    $fechaInicio = $contract->start_date ? \Carbon\Carbon::parse($contract->start_date) : null;
    $fechaFin = $contract->end_date ? \Carbon\Carbon::parse($contract->end_date) : null;
    $fechaInicioLarga = $fechaInicio ? $fechaInicio->locale('es')->translatedFormat('d \d\e F \d\e Y') : '—';
    $fechaFinLarga = $fechaFin ? $fechaFin->locale('es')->translatedFormat('d \d\e F \d\e Y') : '—';

    $totalContratoPdf = (float) $contract->installments->sum('gross_amount') - (float) $contract->installments->sum('discount_amount');
    $totalPagadoPdf = (float) $contract->installments->sum('paid_amount');
    $totalPendientePdf = max(0.0, $totalContratoPdf - $totalPagadoPdf);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Contrato de Alquiler de Vehículo</title>
    @include('pdf.standard.styles', ['orientation' => 'portrait', 'pdfMargen' => 'denso'])
    <style>
        @if ($esHojaMembretadaCompleta)
        @page {
            size: letter portrait;
            margin: 34mm 10mm 32mm 10mm;
        }

        body {
            background-color: transparent;
        }
        @endif

        #letterhead-page-bg {
            position: fixed;
            top: -34mm;
            left: -10mm;
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
            margin-bottom: 6px;
        }

        #letterhead-banner img {
            max-width: 100%;
            max-height: 2.2cm;
        }

        .clauses-box {
            border: 1px solid var(--pdf-borde);
            border-top: none;
            padding: 6px 8px;
            margin-bottom: 5px;
            text-align: justify;
            font-size: 7.2pt;
            line-height: 1.35;
            color: #111827;
        }

        .clause-item {
            margin-bottom: 5px;
        }

        .clause-item:last-child {
            margin-bottom: 0;
        }

        .clause-title {
            font-weight: 700;
            color: #000000;
            text-transform: uppercase;
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
    @elseif (empty($ocultar_marca ?? false) && ! empty($logo_fondo ?? null))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $logo_fondo }}" alt="Marca de agua" />
        </div>
    @endif

    @include('pdf.standard.header', [
        'logo_data_url' => $sinMarcaVisual ? null : ($logo_data_url ?? null),
        'ocultar_celda_logo' => $sinMarcaVisual,
        'empresa_siglas' => $empresaSiglas,
        'empresa_transportadora' => $nombreEmpresa !== '' ? $nombreEmpresa : 'EMPRESA ARRENDADORA',
        'empresa_detalle' => $nitEmpresa !== '' ? 'NIT ' . $nitEmpresa : null,
        'titulo_documento' => 'CONTRATO DE ALQUILER DE VEHÍCULO',
        'subtitulo_documento' => 'GESTIÓN DE FLOTA · CRONOGRAMA DE CANON Y CUOTAS',
        'meta_sgc' => [
            'CONTRATO N°' => $contract->contract_number ?: $contract->uuid,
            'EMISIÓN' => $fechaEmision ?? now()->format('d/m/Y'),
            'ESTADO' => $estadoTexto,
        ],
    ])

    {{-- 1. DATOS GENERALES DEL CONTRATO, VEHÍCULO Y PROPIETARIO --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>1. Identificación del Contrato, Vehículo y Arrendatario</td></tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 18%;">Arrendador:</td>
            <td class="info-val" style="width: 34%;"><strong>{{ $nombreEmpresa !== '' ? $nombreEmpresa : '—' }}</strong></td>
            <td class="info-lbl" style="width: 16%;">NIT Arrendador:</td>
            <td class="info-val mono" style="width: 32%;">{{ $nitEmpresa !== '' ? $nitEmpresa : '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Propietario / Arrendatario:</td>
            <td class="info-val"><strong>{{ $contract->owner->owner_name ?? '—' }}</strong></td>
            <td class="info-lbl">Documento:</td>
            <td class="info-val mono">{{ $contract->owner->document_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Placa / Clase:</td>
            <td class="info-val">
                <strong>{{ $contract->vehicle->vehicle_license_plate ?? '—' }}</strong>
                @if (! empty($contract->vehicle?->vehicleClass?->description))
                    · {{ $contract->vehicle->vehicleClass->description }}
                @endif
            </td>
            <td class="info-lbl">Marca / Modelo:</td>
            <td class="info-val">
                {{ $contract->vehicle->brand->description ?? '—' }} · {{ $contract->vehicle->model ?? '—' }}
            </td>
        </tr>
        <tr>
            <td class="info-lbl">Capacidad / Servicio:</td>
            <td class="info-val">
                {{ $contract->vehicle->passenger_capacity ?? '—' }} pasajeros · {{ $contract->vehicle->type_of_service ?? '—' }}
            </td>
            <td class="info-lbl">Vigencia / Plazo:</td>
            <td class="info-val mono">
                {{ $fechaInicio ? $fechaInicio->format('d/m/Y') : '—' }} al {{ $fechaFin ? $fechaFin->format('d/m/Y') : '—' }} ({{ $contract->monthly_term }} meses)
            </td>
        </tr>
        <tr>
            <td class="info-lbl">Canon Mensual:</td>
            <td class="info-val mono"><strong>$ {{ number_format((float) $contract->monthly_fee, 2, ',', '.') }}</strong></td>
            <td class="info-lbl">Valor Total Contrato:</td>
            <td class="info-val mono"><strong>$ {{ number_format((float) $canonTotal, 2, ',', '.') }}</strong></td>
        </tr>
    </table>

    {{-- 2. CLÁUSULAS CONTRACTUALES --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>2. Cláusulas del Contrato</td></tr>
    </table>
    <div class="clauses-box">
        <div class="clause-item">
            <span class="clause-title">Primera - Objeto:</span>
            El ARRENDADOR entrega en arriendo al ARRENDATARIO el vehículo descrito anteriormente para ser utilizado exclusivamente en transporte de personal y logística operativa.
        </div>
        <div class="clause-item">
            <span class="clause-title">Segunda - Canon y Forma de Pago:</span>
            El ARRENDATARIO se obliga a pagar un canon mensual de $ {{ number_format((float) $contract->monthly_fee, 2, ',', '.') }} COP, pagadero por anticipado cada mes conforme al cronograma anexo.
        </div>
        <div class="clause-item">
            <span class="clause-title">Tercera - Obligaciones del Arrendatario:</span>
            1) Realizar los pagos puntuales según cronograma establecido; 2) Utilizar el vehículo conforme a su naturaleza y destino; 3) Mantener el vehículo en buen estado de conservación; 4) No subarrendar sin autorización escrita; 5) Cubrir multas, infracciones de tránsito y daños causados durante la operación.
        </div>
        <div class="clause-item">
            <span class="clause-title">Cuarta - Duración:</span>
            El presente contrato tendrá una vigencia de {{ $contract->monthly_term }} meses, iniciando el {{ $fechaInicioLarga }} y finalizando el {{ $fechaFinLarga }}, salvo renovación expresa por escrito.
        </div>
        <div class="clause-item">
            <span class="clause-title">Quinta - Terminación y Mora:</span>
            Cualquiera de las partes podrá dar por terminado el contrato mediante notificación escrita con treinta (30) días calendario de anticipación. El incumplimiento en el pago del canon generará intereses moratorios a la tasa máxima legal vigente desde la fecha de vencimiento hasta el pago efectivo.
        </div>
    </div>

    {{-- 3. ANEXO: CRONOGRAMA DE CUOTAS --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>3. Anexo: Cronograma de Cuotas</td></tr>
    </table>
    <table class="main-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 6%;">N°</th>
                <th style="width: 11%;">Vencimiento</th>
                <th style="width: 13%;">Canon</th>
                <th style="width: 12%;">Saldo Ant.</th>
                <th style="width: 12%;">Descuentos</th>
                <th style="width: 13%;">Valor Neto</th>
                <th style="width: 12%;">Pagado</th>
                <th style="width: 12%;">Saldo Pend.</th>
                <th style="width: 9%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($contract->installments as $installment)
                @php($estadoCuota = $estadosCuota[strtolower((string) ($installment->status ?? ''))] ?? strtoupper((string) ($installment->status ?? '—')))
                <tr>
                    <td class="txt-center mono">{{ $installment->installment_number }}</td>
                    <td class="txt-center mono">{{ $installment->due_date ? \Carbon\Carbon::parse($installment->due_date)->format('d/m/Y') : '—' }}</td>
                    <td class="num">$ {{ number_format((float) $installment->gross_amount, 2, ',', '.') }}</td>
                    <td class="num">$ {{ number_format((float) $installment->previous_balance, 2, ',', '.') }}</td>
                    <td class="num">$ {{ number_format((float) $installment->discount_amount, 2, ',', '.') }}</td>
                    <td class="num">$ {{ number_format((float) $installment->net_amount, 2, ',', '.') }}</td>
                    <td class="num">$ {{ number_format((float) $installment->paid_amount, 2, ',', '.') }}</td>
                    <td class="num">$ {{ number_format((float) $installment->pending_balance, 2, ',', '.') }}</td>
                    <td class="txt-center"><strong>{{ $estadoCuota }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="txt-center">Sin cuotas registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($contract->discounts && $contract->discounts->count() > 0)
        {{-- 4. REGISTRO DE DESCUENTOS --}}
        <table class="section-banner" cellpadding="0" cellspacing="0">
            <tr><td>4. Registro de Descuentos Aplicados</td></tr>
        </table>
        <table class="main-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 18%;">Cuota / Mes</th>
                    <th style="width: 13%;">Fecha</th>
                    <th style="width: 31%;">Concepto / Detalle</th>
                    <th style="width: 15%;">Valor</th>
                    <th style="width: 23%;">Observaciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contract->discounts as $discount)
                    <tr>
                        <td class="txt-center mono">Cuota #{{ $discount->installment->installment_number ?? '-' }} ({{ $discount->installment?->due_date?->format('m/Y') ?? '-' }})</td>
                        <td class="txt-center mono">{{ $discount->discount_date ? \Carbon\Carbon::parse($discount->discount_date)->format('d/m/Y') : '—' }}</td>
                        <td>{{ $discount->concept }}</td>
                        <td class="num">$ {{ number_format((float) $discount->amount, 2, ',', '.') }}</td>
                        <td>{{ $discount->observations ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- CIERRE INDIVISIBLE: TOTALES Y FIRMAS --}}
    <table class="cierre" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table class="totals-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 34%;"><strong>Cuotas:</strong> {{ $totalCuotas ?? $contract->installments->count() }}</td>
                        <td style="width: 33%;"><strong>Total Pagado:</strong> $ {{ number_format($totalPagadoPdf, 2, ',', '.') }}</td>
                        <td style="width: 33%;"><strong>Total Pendiente:</strong> $ {{ number_format($totalPendientePdf, 2, ',', '.') }}</td>
                    </tr>
                </table>

                <table class="firmas-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 50%;">
                            <div class="sig-space"></div>
                            <div class="sig-line">
                                <div class="sig-name">{{ $nombreEmpresa !== '' ? $nombreEmpresa : 'EMPRESA ARRENDADORA' }}</div>
                                <div class="sig-role">ARRENDADOR @if ($nitEmpresa !== '') · NIT {{ $nitEmpresa }} @endif</div>
                            </div>
                        </td>
                        <td style="width: 50%;">
                            <div class="sig-space"></div>
                            <div class="sig-line">
                                <div class="sig-name">{{ $contract->owner->owner_name ?? 'PROPIETARIO' }}</div>
                                <div class="sig-role">ARRENDATARIO @if (! empty($contract->owner?->document_number)) · Doc. {{ $contract->owner->document_number }} @endif</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
