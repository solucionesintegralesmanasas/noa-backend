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

    $statusPalettes = [
        'PENDIENTE' => ['fondo' => '#fffbeb', 'texto' => '#b45309', 'borde' => '#fcd34d'],
        'PAGADO' => ['fondo' => '#dcfce7', 'texto' => '#15803d', 'borde' => '#86efac'],
        'VENCIDO' => ['fondo' => '#fee2e2', 'texto' => '#b91c1c', 'borde' => '#fca5a5'],
        'EN_MORA' => ['fondo' => '#fee2e2', 'texto' => '#b91c1c', 'borde' => '#fca5a5'],
        'ANULADO' => ['fondo' => '#f1f5f9', 'texto' => '#475569', 'borde' => '#cbd5e1'],
    ];
    $statusPalette = $statusPalettes[$status] ?? $statusPalettes['PENDIENTE'];

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

    $initials = collect(preg_split('/\s+/', trim((string) ($company?->business_name ?? 'NOA'))))
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_substr($word, 0, 1))
        ->implode('');

    $ownerName = $owner?->full_name ?? '—';
    $ownerCity = $owner?->municipality?->name;
    $companyCity = $company?->municipality?->name;
    $concepto = $charge->concept ?: $chargeTypeLabel;
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Recibo de Caja - {{ $data['charge_internal_id'] }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.3;
            color: #0f172a;
            background-color: #ffffff;
            /* Los márgenes viven en body (no en @page) para que la marca de agua con
               position:fixed se ancle a la hoja completa, igual que pdf.layouts.base. */
            margin: 10mm 12mm;
            padding: 0;
        }

        /* La hoja de papel es la propia página del PDF: los márgenes los fija body.
           Sin fondo propio para no tapar la marca de agua (DomPDF pinta en orden). */
        .paper-sheet {
            width: 100%;
            margin: 0;
            padding: 0;
            position: relative;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td,
        th {
            vertical-align: middle;
            word-wrap: break-word;
        }

        /* 1. ENCABEZADO INSTITUCIONAL */
        .header-table {
            width: 100%;
            border: 1.5px solid #0f172a;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .header-table td {
            border: 1px solid #0f172a;
            padding: 4px 6px;
            vertical-align: middle;
        }

        .logo-cell {
            width: 22%;
            text-align: center;
            background: #ffffff;
            padding: 4px;
        }

        .logo-img {
            max-width: 100%;
            max-height: 46px;
        }

        .title-cell {
            width: 48%;
            text-align: center;
            padding: 4px 6px;
        }

        .company-name {
            font-size: 8.5pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }

        .company-sub {
            font-size: 6.2pt;
            color: #475569;
            margin-top: 1px;
        }

        .doc-main-title {
            font-size: 10.5pt;
            font-weight: 900;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
            line-height: 1.1;
        }

        .doc-sub-title {
            font-size: 6.2pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .meta-cell {
            width: 30%;
            padding: 0 !important;
            background-color: #f8fafc;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            border: none !important;
            border-bottom: 1px solid #0f172a !important;
            padding: 2.5px 6px !important;
            font-size: 7.2pt;
        }

        .meta-table tr:last-child td {
            border-bottom: none !important;
        }

        .meta-label {
            font-weight: bold;
            color: #334155;
            width: 48%;
            background-color: #f1f5f9;
            border-right: 1px solid #0f172a !important;
            text-transform: uppercase;
            font-size: 6.6pt;
        }

        .meta-value {
            font-weight: bold;
            color: #0f172a;
            text-align: center;
            font-family: 'Courier New', Courier, monospace;
            font-size: 7.8pt;
        }

        /* BANNERS DE SECCIÓN */
        .section-banner {
            width: 100%;
            border: 1px solid #0f172a;
            background-color: #e2e8f0;
            border-left: 5px solid #1e3a8a;
            margin-top: 5px;
            margin-bottom: 0;
            /* El banner no queda solo al pie de una hoja: va pegado a su tabla. */
            page-break-after: avoid;
        }

        .section-banner td {
            padding: 2.5px 6px;
            font-size: 7.4pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        /* TABLAS DE DATOS */
        .data-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-top: none;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .data-table td {
            border: 1px solid #cbd5e1;
            padding: 3px 6px;
            font-size: 7.4pt;
            line-height: 1.25;
        }

        .lbl {
            background-color: #f8fafc;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            font-size: 6.8pt;
            border-right: 1px solid #cbd5e1;
        }

        .val {
            font-weight: 600;
            color: #0f172a;
        }

        .mono {
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
        }

        .placa-badge {
            background-color: #fef08a;
            color: #854d0e;
            font-family: 'Courier New', Courier, monospace;
            font-size: 9pt;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 2px;
            border: 1px solid #facc15;
            letter-spacing: 0.8px;
            display: inline-block;
        }

        /* TABLA DE LIQUIDACIÓN Y CONCEPTOS */
        .invoice-table {
            width: 100%;
            border: 1.2px solid #0f172a;
            border-top: none;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .invoice-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 6.8pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 3.5px 6px;
            border: 1px solid #0f172a;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .invoice-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            font-size: 7.4pt;
            vertical-align: middle;
        }

        .item-row {
            background-color: #ffffff;
        }

        .item-desc {
            font-weight: bold;
            color: #0f172a;
            font-size: 7.6pt;
        }

        .item-sub {
            font-size: 6.4pt;
            color: #64748b;
            margin-top: 1px;
        }

        .total-row td {
            padding: 3px 8px;
            font-size: 7.5pt;
            border: 1px solid #cbd5e1;
        }

        .total-lbl {
            text-align: right;
            font-weight: bold;
            color: #334155;
            background-color: #f8fafc;
            text-transform: uppercase;
            font-size: 7pt;
        }

        .grand-total-row td {
            background-color: #eff6ff !important;
            border-top: 1.5px solid #1e3a8a !important;
        }

        .grand-total-lbl {
            text-align: right;
            font-weight: 900;
            color: #1e3a8a;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .grand-total-val {
            text-align: right;
            font-weight: 900;
            color: #1e3a8a;
            font-size: 11pt;
            font-family: 'Courier New', Courier, monospace;
        }

        /* TARJETA DE RECAUDO Y PAGO OPORTUNO */
        .payment-summary-box {
            width: 100%;
            border: 1.5px solid #1e3a8a;
            border-collapse: collapse;
            margin-top: 5px;
            margin-bottom: 5px;
            background-color: #ffffff;
        }

        .payment-summary-box td {
            padding: 7px 10px;
            vertical-align: middle;
        }

        .payment-notice-cell {
            width: 48%;
            border-right: 1px solid #cbd5e1;
            background-color: #f8fafc;
        }

        .payment-amount-cell {
            width: 32%;
            text-align: center;
            border-right: 1px solid #cbd5e1;
            background-color: #f0fdf4;
        }

        .payment-qr-cell {
            width: 20%;
            text-align: center;
            background-color: #ffffff;
        }

        /* REFERENCIAS DE PAGO */
        .references-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 4px;
            background-color: #ffffff;
        }

        .references-table td {
            padding: 5px 8px;
            vertical-align: middle;
            border: 1px solid #cbd5e1;
            font-size: 7pt;
        }

        /* FIRMAS Y PIE */
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 4px;
            /* El bloque de firmas se mantiene íntegro en una sola hoja. */
            page-break-inside: avoid;
        }

        .sig-cell {
            width: 50%;
            padding: 2px 20px;
            vertical-align: bottom;
            text-align: center;
        }

        .sig-img {
            max-height: 42px;
            max-width: 150px;
        }

        .sig-line {
            border-top: 1.2px solid #0f172a;
            padding-top: 2px;
        }

        .sig-name {
            font-size: 7.2pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
        }

        .sig-role {
            font-size: 6.4pt;
            color: #475569;
        }

        .footer-bar {
            margin-top: 4px;
            border-top: 1px solid #cbd5e1;
            padding-top: 3px;
            font-size: 6.3pt;
            color: #64748b;
            /* El pie institucional no se separa del documento. */
            page-break-inside: avoid;
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

<body>

    @if (empty($ocultar_marca) && empty($letterhead) && !empty($data['logo_fondo']))
        <div style="position: fixed; top: 30%; left: 12%; width: 76%; z-index: -1000;">
            <img src="data:image/png;base64,{{ $data['logo_fondo'] }}" alt="Marca de agua"
                style="width: 100%; opacity: 0.06;">
        </div>
    @endif

    <div class="paper-sheet">

        @if (!empty($letterhead))
            <div style="text-align: center; margin-bottom: 6px;">
                <img src="{{ $letterhead }}" alt="Membrete" style="max-width: 100%; max-height: 110px;">
            </div>
        @endif

        <!-- 1. ENCABEZADO INSTITUCIONAL -->
        <table class="header-table" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td class="logo-cell">
                        @if (!empty($data['logo']))
                            <img class="logo-img" src="data:image/png;base64,{{ $data['logo'] }}"
                                alt="{{ $company?->business_name }}">
                        @else
                            <div style="font-weight: 900; font-size: 15pt; color: #1e3a8a; line-height: 1;">
                                {{ $initials }}
                            </div>
                            <div style="font-size: 5.6pt; font-weight: bold; color: #0f172a; text-transform: uppercase; margin-top: 1px;">
                                {{ $company?->business_name ?? 'N/A' }}
                            </div>
                            <div style="font-size: 5pt; color: #64748b; margin-top: 1px;">
                                NIT. {{ $companyNit !== '' ? $companyNit : 'N/A' }}
                            </div>
                        @endif
                    </td>
                    <td class="title-cell">
                        <div class="company-name">{{ $company?->business_name ?? 'N/A' }}</div>
                        <div class="company-sub">
                            {{ $company?->address ?: 'Dirección no registrada' }}@if ($companyCity), {{ $companyCity }}@endif
                            @if ($company?->phone) · Tel: {{ $company->phone }} @endif
                        </div>
                        <div class="company-sub">
                            {{ $company?->email ?: 'Correo no registrado' }}@if ($company?->web_page) · {{ $company->web_page }} @endif
                        </div>
                        <div class="doc-main-title">RECIBO DE CAJA</div>
                        <div class="doc-sub-title">COMPROBANTE OFICIAL DE RECAUDO Y LIQUIDACIÓN OPERATIVA</div>
                    </td>
                    <td class="meta-cell">
                        <table class="meta-table" cellpadding="0" cellspacing="0">
                            <tbody>
                                <tr>
                                    <td class="meta-label">RECIBO N°</td>
                                    <td class="meta-value" style="color: #1e3a8a; font-size: 8.5pt;">
                                        {{ $data['charge_internal_id'] }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="meta-label">FECHA PAGO</td>
                                    <td class="meta-value">{{ $date($charge->payment_date) }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">PERÍODO</td>
                                    <td class="meta-value" style="font-size: 6.8pt;">{{ $periodo }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">FORMA PAGO</td>
                                    <td class="meta-value" style="font-size: 6.8pt;">{{ $paymentLabel }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">ESTADO</td>
                                    <td class="meta-value" style="padding: 3px !important;">
                                        <span style="display: inline-block; background-color: {{ $statusPalette['fondo'] }}; color: {{ $statusPalette['texto'] }}; border: 1px solid {{ $statusPalette['borde'] }}; border-radius: 3px; font-weight: 800; font-size: 7.2pt; padding: 1px 8px; letter-spacing: 0.8px;">
                                            ● {{ $statusLabel }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 2. DATOS DEL CLIENTE / PROPIETARIO AFILIADO -->
        <table class="section-banner" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td>1. Datos del Cliente / Propietario o Tenedor</td>
                </tr>
            </tbody>
        </table>

        <table class="data-table" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td class="lbl" style="width: 20%;">Nombre / Razón Social:</td>
                    <td class="val uppercase" style="width: 45%; color: #1e3a8a; font-weight: 700;">
                        {{ $ownerName }}
                    </td>
                    <td class="lbl" style="width: 15%;">Identificación:</td>
                    <td class="val mono" style="width: 20%;">
                        {{ $owner?->document_number ?: '—' }}
                    </td>
                </tr>
                <tr>
                    <td class="lbl">Dirección:</td>
                    <td class="val uppercase">{{ $owner?->address ?: '—' }}</td>
                    <td class="lbl">Municipio / Dpto:</td>
                    <td class="val uppercase">{{ $ownerCity ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Teléfono / Celular:</td>
                    <td class="val mono">{{ $owner?->phone ?: '—' }}</td>
                    <td class="lbl">Correo Electrónico:</td>
                    <td class="val mono" style="font-size: 7pt; color: #334155;">{{ $owner?->email ?: '—' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- 3. INFORMACIÓN DEL VEHÍCULO VINCULADO -->
        <table class="section-banner" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td>2. Información Técnica del Vehículo Vinculado</td>
                </tr>
            </tbody>
        </table>

        <table class="data-table" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td class="lbl" style="width: 15%;">Placa:</td>
                    <td class="val" style="width: 20%;">
                        <span class="placa-badge">{{ $vehicle?->vehicle_license_plate ?: '—' }}</span>
                    </td>
                    <td class="lbl" style="width: 15%;">N° Interno (Móvil):</td>
                    <td class="val text-center" style="width: 18%;">
                        <strong style="color: #1e3a8a; font-size: 8.5pt;">{{ $vehicle?->internal_number ?: '—' }}</strong>
                    </td>
                    <td class="lbl" style="width: 14%;">Clase de Vehículo:</td>
                    <td class="val uppercase" style="width: 18%;">
                        {{ $vehicle?->vehicleClass?->description ?: '—' }}
                        @if ($vehicle && (int) $vehicle->passenger_capacity > 0)
                            ({{ (int) $vehicle->passenger_capacity }} PAX)
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="lbl">Marca / Línea:</td>
                    <td class="val uppercase">
                        {{ $vehicle?->brand?->description ?: '—' }}@if ($vehicle?->line) · {{ $vehicle->line }}@endif
                    </td>
                    <td class="lbl">Modelo (Año):</td>
                    <td class="val text-center mono">{{ $vehicle?->model ?: '—' }}</td>
                    <td class="lbl">Modalidad:</td>
                    <td class="val uppercase" style="font-size: 6.8pt; color: #1e3a8a; font-weight: bold;">
                        {{ $serviceLabel }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 4. DETALLES DEL SERVICIO Y LIQUIDACIÓN DE COBROS -->
        <table class="section-banner" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td>3. Detalle de Conceptos y Liquidación Económica</td>
                </tr>
            </tbody>
        </table>

        <table class="invoice-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 16%;">Período Facturado</th>
                    <th style="width: 48%; text-align: left; padding-left: 8px;">Concepto / Descripción del Cobro</th>
                    <th style="width: 12%;">Tasa Mora</th>
                    <th style="width: 24%; text-align: right; padding-right: 8px;">Valor ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody>
                <tr class="item-row">
                    <td class="text-center mono"><strong>{{ $periodo }}</strong></td>
                    <td class="text-left" style="padding-left: 8px;">
                        <div class="item-desc">{{ $concepto }}</div>
                        <div class="item-sub">{{ $chargeTypeLabel }} · {{ $currency }}</div>
                    </td>
                    <td class="text-center mono">{{ number_format($lateFee, 2, ',', '.') }}%</td>
                    <td class="text-right mono font-bold" style="padding-right: 8px; font-size: 8.2pt;">
                        {{ $money($amount) }}
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" rowspan="3" style="vertical-align: top; background-color: #ffffff; padding: 5px 8px;">
                        <strong style="color: #334155; font-size: 6.8pt; text-transform: uppercase;">Son:</strong>
                        <span style="font-size: 7.2pt; font-weight: 600; color: #0f172a;">
                            {{ $totalEnLetras }}.
                        </span><br>
                        <div style="margin-top: 3px; font-size: 6.5pt; color: #64748b;">
                            <strong>Observaciones:</strong> {{ $charge->notes ?: 'Sin observaciones registradas.' }}
                        </div>
                    </td>
                    <td class="total-lbl">Subtotal:</td>
                    <td class="text-right mono font-bold" style="padding-right: 8px;">{{ $money($amount) }}</td>
                </tr>
                <tr class="total-row">
                    <td class="total-lbl">Recargo por mora ({{ number_format($lateFee, 2, ',', '.') }}%):</td>
                    <td class="text-right mono" style="padding-right: 8px; color: {{ $recargo > 0 ? '#b91c1c' : '#15803d' }}; font-weight: bold;">
                        {{ $money($recargo) }}
                    </td>
                </tr>
                <tr class="grand-total-row">
                    <td class="grand-total-lbl">{{ $isPagado ? 'TOTAL PAGADO:' : 'TOTAL A PAGAR:' }}</td>
                    <td class="grand-total-val" style="padding-right: 8px;">{{ $money($total) }}</td>
                </tr>
            </tfoot>
        </table>

        <!-- 5. RESUMEN DE RECAUDO Y VALIDACIÓN QR -->
        <table class="payment-summary-box" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td class="payment-notice-cell">
                        <div style="font-size: 6.8pt; font-weight: bold; color: #475569; text-transform: uppercase;">
                            Control de Vencimiento:
                        </div>
                        <div style="font-size: 9pt; font-weight: 800; color: #0f172a; margin-top: 1px;">
                            {{ $isPagado ? 'Fecha límite de pago:' : 'Pague oportunamente hasta:' }}
                            <span style="color: {{ $isPagado ? '#15803d' : '#b91c1c' }}; font-family: 'Courier New', Courier, monospace;">
                                {{ $date($charge->due_date) }}
                            </span>
                        </div>
                        <div style="font-size: 6.4pt; color: #64748b; margin-top: 2px; line-height: 1.25;">
                            @if ($isPagado)
                                Pago registrado el {{ $date($charge->payment_date) }}@if ($charge->bank_reference), referencia bancaria {{ $charge->bank_reference }}@endif.
                            @else
                                El pago posterior a esta fecha genera intereses moratorios a la tasa máxima legal vigente certificada por la Superfinanciera.
                            @endif
                        </div>
                    </td>
                    <td class="payment-amount-cell">
                        <div style="font-size: 6.8pt; font-weight: bold; color: #15803d; text-transform: uppercase; letter-spacing: 0.3px;">
                            {{ $isPagado ? 'Total Liquidado y Acreditado' : 'Total Pendiente de Pago' }}
                        </div>
                        <div style="font-size: 14pt; font-weight: 900; color: #166534; font-family: 'Courier New', Courier, monospace; margin-top: 1px;">
                            {{ $money($total) }}
                        </div>
                        <div style="font-size: 6pt; color: {{ $isPagado ? '#15803d' : '#b45309' }}; font-weight: bold;">
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
                                style="width: 58px; height: 58px; border: 1.2px solid #0f172a; padding: 2px; background: #ffffff;">
                        @endif
                        <div style="font-size: 5.5pt; font-weight: bold; color: #475569; margin-top: 1px;">
                            VALIDACIÓN DEL RECIBO
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 6. REFERENCIAS REALES DEL PAGO -->
        <table class="references-table" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td style="width: 25%; background-color: #f8fafc;">
                        <div style="font-size: 6.8pt; font-weight: bold; color: #0f172a; text-transform: uppercase;">
                            Referencia de Pago:
                        </div>
                        <div class="mono" style="font-size: 7pt; color: #1e3a8a;">{{ $charge->payment_reference ?: '—' }}</div>
                    </td>
                    <td style="width: 25%;">
                        <div style="font-size: 6.8pt; font-weight: bold; color: #0f172a; text-transform: uppercase;">
                            Referencia Bancaria:
                        </div>
                        <div class="mono" style="font-size: 7pt; color: #334155;">{{ $charge->bank_reference ?: '—' }}</div>
                    </td>
                    <td style="width: 25%;">
                        <div style="font-size: 6.8pt; font-weight: bold; color: #0f172a; text-transform: uppercase;">
                            Próximo Pago:
                        </div>
                        <div class="mono" style="font-size: 7pt; color: #334155;">{{ $date($charge->next_payment_date) }}</div>
                    </td>
                    <td style="width: 25%;">
                        <div style="font-size: 6.8pt; font-weight: bold; color: #0f172a; text-transform: uppercase;">
                            Centro de Costo:
                        </div>
                        <div style="font-size: 7pt; color: #334155;">{{ $chargeTypeLabel }}</div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 7. CONTROL DE FIRMAS Y CONFORMIDAD -->
        <table class="signatures-table" cellpadding="0" cellspacing="0">
            <tbody>
                <tr>
                    <td class="sig-cell">
                        @if (!empty($data['firma']))
                            <img class="sig-img" src="data:image/png;base64,{{ $data['firma'] }}" alt="Firma autorizada"><br>
                        @else
                            <br><br>
                        @endif
                        <div class="sig-line">
                            <div class="sig-name">
                                {{ $data['legal_representative_name'] !== 'N/A' ? $data['legal_representative_name'] : ($company?->business_name ?? 'Representante Legal') }}
                            </div>
                            <div class="sig-role">
                                Representante Legal · {{ $data['document_number'] !== 'N/A' ? $data['document_number'] : ($companyNit ?: 'N/A') }}
                            </div>
                        </div>
                    </td>
                    <td class="sig-cell">
                        <br><br>
                        <div class="sig-line">
                            <div class="sig-name">{{ $ownerName }}</div>
                            <div class="sig-role">
                                {{ $owner?->document_number ? 'Identificación '.$owner->document_number : 'Propietario / Tenedor' }}
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 8. PIE DE PÁGINA INSTITUCIONAL -->
        <div class="footer-bar">
            <table cellpadding="0" cellspacing="0" style="width: 100%;">
                <tbody>
                    <tr>
                        <td style="width: 72%; text-align: left;">
                            {{ $company?->business_name ?? 'N/A' }} · NIT {{ $companyNit !== '' ? $companyNit : 'N/A' }} · {{ $company?->address ?: 'Dirección no registrada' }}@if ($companyCity), {{ $companyCity }}@endif
                        </td>
                        <td style="width: 28%; text-align: right; font-family: 'Courier New', Courier, monospace;">
                            {{ $data['charge_internal_id'] }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

</body>

</html>
