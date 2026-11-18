@php
    $company = $charge->company;
    $vehicle = $charge->vehicle;
    $owner = $vehicle?->thirdParty;

    $status = strtoupper((string) ($charge->status ?? 'PENDIENTE'));
    $statusLabels = [
        'PENDIENTE' => 'PENDIENTE',
        'PAGADO' => 'PAGADO',
        'VENCIDO' => 'VENCIDO',
        'EN_MORA' => 'EN MORA',
        'ANULADO' => 'ANULADO',
    ];
    $statusLabel = $statusLabels[$status] ?? $status;

    $paymentLabels = [
        'EFECTIVO' => 'EFECTIVO',
        'TRANSFERENCIA' => 'TRANSFERENCIA',
        'CHEQUE' => 'CHEQUE',
        'TARJETA' => 'TARJETA',
        'CORTESIA' => 'CORTESÍA',
        'OTRO' => 'OTRO',
    ];
    $paymentLabel = $paymentLabels[strtoupper((string) $charge->payment_method)] ?? ($charge->payment_method ?: 'NO REGISTRADO');

    $chargeTypeLabels = [
        'CUOTA_ADMINISTRACION' => 'Cuota de Administración',
        'PAGO_MENSUALIDAD' => 'Pago de Mensualidad',
        'PAGO_CUPO' => 'Pago de Cupo',
    ];
    $chargeTypeLabel = $chargeTypeLabels[$charge->charge_type] ?? ($charge->charge_type ?: 'Cuota de Administración');

    $serviceLabels = [
        'PUBLICO' => 'SERVICIO PÚBLICO',
        'PARTICULAR' => 'SERVICIO PARTICULAR',
    ];
    $serviceLabel = $serviceLabels[strtoupper((string) $vehicle?->type_of_service)] ?? ($vehicle?->type_of_service ?: '—');

    $currency = strtoupper((string) ($charge->currency_code ?: 'COP'));
    $symbol = $currency === 'COP' ? '$' : $currency.' ';
    $money = fn ($value) => $symbol.' '.number_format((float) $value, 0, ',', '.');
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '—';

    $meses = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL', 5 => 'MAYO', 6 => 'JUNIO',
        7 => 'JULIO', 8 => 'AGOSTO', 9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];
    $periodo = $charge->period_date
        ? $meses[(int) $charge->period_date->format('n')].' '.$charge->period_date->format('Y')
        : '—';

    $amount = (float) $charge->amount;
    $lateFee = (float) ($charge->late_fee_percentage ?? 0);
    $aplicaMora = in_array($status, ['VENCIDO', 'EN_MORA'], true);
    $recargo = $aplicaMora ? round($amount * $lateFee / 100, 2) : 0.0;
    $total = $amount + $recargo;
    $isPagado = $status === 'PAGADO';

    $unidades = [
        0 => '', 1 => 'UNO', 2 => 'DOS', 3 => 'TRES', 4 => 'CUATRO', 5 => 'CINCO', 6 => 'SEIS',
        7 => 'SIETE', 8 => 'OCHO', 9 => 'NUEVE', 10 => 'DIEZ', 11 => 'ONCE', 12 => 'DOCE',
        13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE', 16 => 'DIECISÉIS', 17 => 'DIECISIETE',
        18 => 'DIECIOCHO', 19 => 'DIECINUEVE', 20 => 'VEINTE', 21 => 'VEINTIUNO', 22 => 'VEINTIDÓS',
        23 => 'VEINTITRÉS', 24 => 'VEINTICUATRO', 25 => 'VEINTICINCO', 26 => 'VEINTISÉIS',
        27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE',
    ];
    $decenas = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];
    $centenas = [1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS'];

    $grupo = function (int $n) use ($unidades, $decenas, $centenas): string {
        if ($n === 0) {
            return '';
        }
        if ($n === 100) {
            return 'CIEN';
        }

        $partes = [];
        $c = intdiv($n, 100);
        $r = $n % 100;

        if ($c > 0) {
            $partes[] = $centenas[$c];
        }
        if ($r > 0) {
            if ($r < 30) {
                $partes[] = $unidades[$r];
            } else {
                $d = intdiv($r, 10);
                $u = $r % 10;
                $partes[] = $decenas[$d].($u > 0 ? ' Y '.$unidades[$u] : '');
            }
        }

        return implode(' ', $partes);
    };

    $enLetras = function (int $n) use (&$enLetras, $grupo): string {
        if ($n < 1000) {
            return $grupo($n);
        }

        if ($n < 1000000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;
            $texto = $miles === 1 ? 'MIL' : $grupo($miles).' MIL';

            return $resto > 0 ? $texto.' '.$grupo($resto) : $texto;
        }

        if ($n < 1000000000) {
            $millones = intdiv($n, 1000000);
            $resto = $n % 1000000;
            $texto = $millones === 1 ? 'UN MILLÓN' : $grupo($millones).' MILLONES';

            return $resto > 0 ? $texto.' '.$enLetras($resto) : $texto;
        }

        $milesMillones = intdiv($n, 1000000000);
        $resto = $n % 1000000000;
        $texto = $milesMillones === 1 ? 'MIL MILLONES' : $grupo($milesMillones).' MIL MILLONES';

        return $resto > 0 ? $texto.' '.$enLetras($resto) : $texto;
    };

    $entero = (int) floor($total);
    $centavos = (int) round(($total - $entero) * 100);
    $letras = trim($enLetras($entero));
    if ($letras === 'UNO') {
        $letras = 'UN';
    }
    $totalEnLetras = $letras.' '.($currency === 'COP' ? 'PESOS' : $currency).' CON '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100 M/CTE';

    $companyNit = trim((string) ($company?->document_number ?? ''));
    if ($companyNit !== '' && $company?->verification_digit !== null) {
        $companyNit .= '-'.$company->verification_digit;
    }

    $initials = \App\Services\Pdf\PdfService::siglasEmpresa((string) ($company?->business_name ?? 'NOA'));
    $ownerName = $owner?->full_name ?? '—';
    $ownerCity = $owner?->municipality?->name;
    $companyCity = $company?->municipality?->name;
    $concepto = $charge->concept ?: $chargeTypeLabel;

    $detalleEmpresaPartes = array_filter([
        $companyNit !== '' ? 'NIT '.$companyNit : null,
        $company?->address ? $company->address.($companyCity ? ', '.$companyCity : '') : $companyCity,
        $company?->phone ? 'Tel: '.$company->phone : null,
    ]);
    $detalleEmpresa = implode(' · ', $detalleEmpresaPartes);

    $logoDataUrl = null;
    if (empty($letterhead ?? null) && empty($ocultar_marca ?? false) && !empty($data['logo'] ?? null)) {
        $logoDataUrl = 'data:image/png;base64,'.$data['logo'];
    }

    $esHojaMembretadaCompleta = \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Recibo de Caja - {{ $data['charge_internal_id'] }}</title>
    @include('pdf.standard.styles', ['orientation' => 'portrait', 'pdfMargen' => 'denso'])
    <style>
        @page {
            size: letter portrait;
            margin: {{ $esHojaMembretadaCompleta ? '34mm 12mm 32mm 12mm' : '10mm 12mm' }};
        }

        body {
            background-color: {{ $esHojaMembretadaCompleta ? 'transparent' : '#ffffff' }};
        }

        #watermark {
            position: fixed;
            top: 28%;
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
            top: -34mm;
            left: -12mm;
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

        /* Compactamos ligeramente los 5 renglones de metadatos para conservar 1 hoja con membrete vertical. */
        .meta-table td {
            padding: 2.8px 6px !important;
            font-size: 6.6pt;
        }

        .info-table {
            margin-bottom: 4px;
        }

        .main-table {
            border-top: none;
            margin-bottom: 4px;
        }

        .total-row td {
            padding: 3px 6px;
            font-size: 7pt;
            border: 0.7px solid var(--pdf-borde-interno);
        }

        .total-lbl {
            text-align: right;
            font-weight: 700;
            color: #111827;
            background-color: var(--pdf-tenue);
            text-transform: uppercase;
            font-size: 6.6pt;
        }

        .grand-total-row td {
            background-color: var(--pdf-encabezado) !important;
            border-top: 1.2px solid var(--pdf-borde) !important;
        }

        .grand-total-lbl {
            text-align: right;
            font-weight: 700;
            color: #000000;
            font-size: 7.8pt;
            text-transform: uppercase;
        }

        .grand-total-val {
            text-align: right;
            font-weight: 700;
            color: #000000;
            font-size: 9.6pt;
        }

        .payment-summary-box {
            width: 100%;
            border: 1px solid var(--pdf-borde);
            border-collapse: collapse;
            margin-bottom: 4px;
            background-color: #ffffff;
            page-break-inside: avoid;
        }

        .payment-summary-box td {
            padding: 6px 8px;
            vertical-align: middle;
        }

        .payment-notice-cell {
            width: 48%;
            border-right: 0.7px solid var(--pdf-borde-interno);
            background-color: var(--pdf-tenue);
        }

        .payment-amount-cell {
            width: 32%;
            text-align: center;
            border-right: 0.7px solid var(--pdf-borde-interno);
            background-color: var(--pdf-encabezado);
        }

        .payment-qr-cell {
            width: 20%;
            text-align: center;
            background-color: #ffffff;
        }

        .references-table {
            width: 100%;
            border: 1px solid var(--pdf-borde);
            border-collapse: collapse;
            margin-bottom: 5px;
            background-color: #ffffff;
        }

        .references-table td {
            padding: 4px 6px;
            vertical-align: middle;
            border: 0.7px solid var(--pdf-borde-interno);
            font-size: 7pt;
        }

        .firmas-recibo {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            page-break-inside: avoid;
        }

        .firmas-recibo td {
            width: 50%;
            padding: 2px 18px;
            vertical-align: bottom;
            text-align: center;
            border: none;
        }

        .sig-img {
            max-height: 40px;
            max-width: 145px;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-left {
            text-align: left !important;
        }

        .uppercase {
            text-transform: uppercase;
        }
    </style>
</head>

<body class="{{ !empty($letterhead ?? null) ? 'with-letterhead' : '' }}">

    @if (!empty($letterhead ?? null) && $esHojaMembretadaCompleta)
        <div id="letterhead-page-bg">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @endif

    @if (empty($ocultar_marca ?? false) && empty($letterhead ?? null) && !empty($data['logo_fondo'] ?? null))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $data['logo_fondo'] }}" alt="Marca de agua" />
        </div>
    @endif

    @if (!empty($letterhead ?? null) && ! $esHojaMembretadaCompleta)
        <div id="letterhead-banner">
            <img src="{{ $letterhead }}" alt="Membrete" />
        </div>
    @endif

    @include('pdf.standard.header', [
        'logo_data_url' => $logoDataUrl,
        'ocultar_celda_logo' => !empty($letterhead ?? null) || !empty($ocultar_marca ?? false),
        'empresa_siglas' => $initials,
        'empresa_transportadora' => $company?->business_name ?? 'N/A',
        'empresa_detalle' => $detalleEmpresa !== '' ? $detalleEmpresa : null,
        'titulo_documento' => 'RECIBO DE CAJA',
        'subtitulo_documento' => 'COMPROBANTE OFICIAL DE RECAUDO Y LIQUIDACIÓN OPERATIVA',
        'meta_sgc' => [
            'RECIBO N°' => $data['charge_internal_id'] ?? '—',
            'FECHA PAGO' => $date($charge->payment_date),
            'PERÍODO' => $periodo,
            'FORMA PAGO' => $paymentLabel,
            'ESTADO' => $statusLabel,
        ],
    ])

    {{-- 1. DATOS DEL CLIENTE / PROPIETARIO AFILIADO --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>1. Datos del Cliente / Propietario o Tenedor</td>
        </tr>
    </table>

    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 20%;">Nombre / Razón Social:</td>
            <td class="info-val uppercase" style="width: 45%; font-weight: 700;">{{ $ownerName }}</td>
            <td class="info-lbl" style="width: 15%;">Identificación:</td>
            <td class="info-val" style="width: 20%;">{{ $owner?->document_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Dirección:</td>
            <td class="info-val uppercase">{{ $owner?->address ?: '—' }}</td>
            <td class="info-lbl">Municipio / Dpto:</td>
            <td class="info-val uppercase">{{ $ownerCity ?: '—' }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Teléfono / Celular:</td>
            <td class="info-val">{{ $owner?->phone ?: '—' }}</td>
            <td class="info-lbl">Correo Electrónico:</td>
            <td class="info-val">{{ $owner?->email ?: '—' }}</td>
        </tr>
    </table>

    {{-- 2. INFORMACIÓN DEL VEHÍCULO VINCULADO --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>2. Información Técnica del Vehículo Vinculado</td>
        </tr>
    </table>

    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 15%;">Placa:</td>
            <td class="info-val" style="width: 20%; font-weight: 700;">{{ $vehicle?->vehicle_license_plate ?: '—' }}</td>
            <td class="info-lbl" style="width: 15%;">N° Interno (Móvil):</td>
            <td class="info-val text-center" style="width: 18%; font-weight: 700;">{{ $vehicle?->internal_number ?: '—' }}</td>
            <td class="info-lbl" style="width: 14%;">Clase de Vehículo:</td>
            <td class="info-val uppercase" style="width: 18%;">
                {{ $vehicle?->vehicleClass?->description ?: '—' }}
                @if ($vehicle && (int) $vehicle->passenger_capacity > 0)
                    ({{ (int) $vehicle->passenger_capacity }} PAX)
                @endif
            </td>
        </tr>
        <tr>
            <td class="info-lbl">Marca / Línea:</td>
            <td class="info-val uppercase">
                {{ $vehicle?->brand?->description ?: '—' }}@if ($vehicle?->line) · {{ $vehicle->line }}@endif
            </td>
            <td class="info-lbl">Modelo (Año):</td>
            <td class="info-val text-center">{{ $vehicle?->model ?: '—' }}</td>
            <td class="info-lbl">Modalidad:</td>
            <td class="info-val uppercase" style="font-weight: 700;">{{ $serviceLabel }}</td>
        </tr>
    </table>

    {{-- 3. DETALLE DE CONCEPTOS Y LIQUIDACIÓN ECONÓMICA --}}
    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr>
            <td>3. Detalle de Conceptos y Liquidación Económica</td>
        </tr>
    </table>

    <table class="main-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 16%;">Período Facturado</th>
                <th style="width: 48%; text-align: left; padding-left: 8px;">Concepto / Descripción del Cobro</th>
                <th style="width: 12%;">Tasa Mora</th>
                <th style="width: 24%; text-align: right; padding-right: 8px;">Valor ({{ $currency }})</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="txt-center" style="font-weight: 700;">{{ $periodo }}</td>
                <td class="text-left" style="padding-left: 8px;">
                    <div style="font-weight: 700; color: #000000; font-size: 7.4pt;">{{ $concepto }}</div>
                    <div style="font-size: 6.5pt; color: #262626; margin-top: 1px;">{{ $chargeTypeLabel }} · {{ $currency }}</div>
                </td>
                <td class="txt-center">{{ number_format($lateFee, 2, ',', '.') }}%</td>
                <td class="num" style="padding-right: 8px; font-size: 8pt;">{{ $money($amount) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" rowspan="3" style="vertical-align: top; background-color: #ffffff; padding: 5px 8px;">
                    <strong style="color: #111827; font-size: 6.8pt; text-transform: uppercase;">Son:</strong>
                    <span style="font-size: 7.2pt; font-weight: 600; color: #000000;">{{ $totalEnLetras }}.</span>
                    <div style="margin-top: 3px; font-size: 6.6pt; color: #262626;">
                        <strong>Observaciones:</strong> {{ $charge->notes ?: 'Sin observaciones registradas.' }}
                    </div>
                </td>
                <td class="total-lbl">Subtotal:</td>
                <td class="num" style="padding-right: 8px;">{{ $money($amount) }}</td>
            </tr>
            <tr class="total-row">
                <td class="total-lbl">Recargo mora ({{ number_format($lateFee, 2, ',', '.') }}%):</td>
                <td class="num" style="padding-right: 8px; color: {{ $recargo > 0 ? '#991b1b' : '#166534' }};">
                    {{ $money($recargo) }}
                </td>
            </tr>
            <tr class="grand-total-row">
                <td class="grand-total-lbl">{{ $isPagado ? 'TOTAL PAGADO:' : 'TOTAL A PAGAR:' }}</td>
                <td class="grand-total-val" style="padding-right: 8px;">{{ $money($total) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- 4. RESUMEN DE RECAUDO Y VALIDACIÓN QR --}}
    <table class="payment-summary-box" cellpadding="0" cellspacing="0">
        <tr>
            <td class="payment-notice-cell">
                <div style="font-size: 6.8pt; font-weight: 700; color: #111827; text-transform: uppercase;">
                    Control de Vencimiento:
                </div>
                <div style="font-size: 8.4pt; font-weight: 700; color: #000000; margin-top: 1px;">
                    {{ $isPagado ? 'Fecha límite de pago:' : 'Pague oportunamente hasta:' }}
                    <span style="color: {{ $isPagado ? '#166534' : '#991b1b' }};">{{ $date($charge->due_date) }}</span>
                </div>
                <div style="font-size: 6.5pt; color: #262626; margin-top: 2px; line-height: 1.25;">
                    @if ($isPagado)
                        Pago registrado el {{ $date($charge->payment_date) }}@if ($charge->bank_reference), referencia bancaria {{ $charge->bank_reference }}@endif.
                    @else
                        El pago posterior a esta fecha genera intereses moratorios a la tasa máxima legal vigente certificada por la Superfinanciera.
                    @endif
                </div>
            </td>
            <td class="payment-amount-cell">
                <div style="font-size: 6.8pt; font-weight: 700; color: #111827; text-transform: uppercase;">
                    {{ $isPagado ? 'Total Liquidado y Acreditado' : 'Total Pendiente de Pago' }}
                </div>
                <div style="font-size: 12pt; font-weight: 700; color: #000000; margin-top: 1px;">
                    {{ $money($total) }}
                </div>
                <div style="font-size: 6.5pt; color: {{ $isPagado ? '#166534' : ($aplicaMora ? '#991b1b' : '#92400e') }}; font-weight: 700;">
                    @if ($isPagado)
                        TRANSACCIÓN EXITOSA
                    @elseif ($status === 'ANULADO')
                        DOCUMENTO ANULADO
                    @elseif ($aplicaMora)
                        PAGO VENCIDO
                    @else
                        PENDIENTE DE PAGO
                    @endif
                </div>
            </td>
            <td class="payment-qr-cell">
                @if (!empty($data['qr_code']))
                    <img src="{{ $data['qr_code'] }}" alt="Código QR de validación"
                        style="width: 54px; height: 54px; border: 1px solid var(--pdf-borde); padding: 2px; background: #ffffff;">
                @endif
                <div style="font-size: 6pt; font-weight: 700; color: #262626; margin-top: 1px;">
                    VALIDACIÓN DEL RECIBO
                </div>
            </td>
        </tr>
    </table>

    {{-- 5. REFERENCIAS DEL PAGO --}}
    <table class="references-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 25%; background-color: var(--pdf-tenue);">
                <div style="font-size: 6.5pt; font-weight: 700; color: #111827; text-transform: uppercase;">
                    Referencia de Pago:
                </div>
                <div style="font-size: 7.2pt; font-weight: 700; color: #000000;">{{ $charge->payment_reference ?: '—' }}</div>
            </td>
            <td style="width: 25%;">
                <div style="font-size: 6.5pt; font-weight: 700; color: #111827; text-transform: uppercase;">
                    Referencia Bancaria:
                </div>
                <div style="font-size: 7.2pt; color: #111827;">{{ $charge->bank_reference ?: '—' }}</div>
            </td>
            <td style="width: 25%;">
                <div style="font-size: 6.5pt; font-weight: 700; color: #111827; text-transform: uppercase;">
                    Próximo Pago:
                </div>
                <div style="font-size: 7.2pt; color: #111827;">{{ $date($charge->next_payment_date) }}</div>
            </td>
            <td style="width: 25%;">
                <div style="font-size: 6.5pt; font-weight: 700; color: #111827; text-transform: uppercase;">
                    Centro de Costo:
                </div>
                <div style="font-size: 7.2pt; color: #111827;">{{ $chargeTypeLabel }}</div>
            </td>
        </tr>
    </table>

    {{-- 6. FIRMAS Y CONFORMIDAD --}}
    <table class="firmas-recibo" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                @if (!empty($data['firma']))
                    <img class="sig-img" src="data:image/png;base64,{{ $data['firma'] }}" alt="Firma autorizada"><br>
                @else
                    <br><br>
                @endif
                <div class="sig-line">
                    <div class="sig-name">
                        {{ ($data['legal_representative_name'] ?? 'N/A') !== 'N/A' ? $data['legal_representative_name'] : ($company?->business_name ?? 'Representante Legal') }}
                    </div>
                    <div class="sig-role">
                        Representante Legal · {{ ($data['document_number'] ?? 'N/A') !== 'N/A' ? $data['document_number'] : ($companyNit ?: 'N/A') }}
                    </div>
                </div>
            </td>
            <td>
                <br><br>
                <div class="sig-line">
                    <div class="sig-name">{{ $ownerName }}</div>
                    <div class="sig-role">
                        {{ $owner?->document_number ? 'Identificación '.$owner->document_number : 'Propietario / Tenedor' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

</body>

</html>
