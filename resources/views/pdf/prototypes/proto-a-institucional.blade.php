@php
    $orient = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Prototipo A — Guía Estándar Institucional SGC</title>
    @include('pdf.standard.styles', ['orientation' => $orient, 'pdfMargen' => 'denso'])
</head>
<body>

    @include('pdf.standard.header', [
        'logo_data_url' => $logo_data_url ?? null,
        'empresa_siglas' => $empresa_siglas ?? \App\Services\Pdf\PdfService::siglasEmpresa((string) ($empresa_transportadora ?? '')),
        'empresa_transportadora' => $empresa_transportadora ?? '',
        'empresa_detalle' => trim(($empresa_nit ? 'NIT ' . $empresa_nit : '') . ($empresa_ciudad ? ' · ' . $empresa_ciudad : '')),
        'titulo_documento' => $titulo_documento ?? '',
        'subtitulo_documento' => $subtitulo_documento ?? '',
        'meta_sgc' => [
            'CÓDIGO' => $codigo_sgc ?? '',
            'VERSIÓN' => $version_sgc ?? '',
            'FECHA' => $fecha_sgc ?? '',
            'PERÍODO' => $periodo ?? '',
        ],
    ])

    <table class="section-banner" cellpadding="0" cellspacing="0">
        <tr><td>1. Identificación del Vehículo, Contrato y Operación</td></tr>
    </table>
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-lbl" style="width: 11%;">Vehículo:</td>
            <td class="info-val" style="width: 24%; padding-top: 1.5px; padding-bottom: 1.5px;">
                @include('pdf.standard.vehicle-line', ['placa' => $placa ?? 'N/A', 'numero_interno' => $numero_interno ?? null])
            </td>
            <td class="info-lbl" style="width: 9%;">Clase:</td>
            <td class="info-val" style="width: 26%;">{{ $clase_vehiculo }}</td>
            <td class="info-lbl" style="width: 9%;">Sede:</td>
            <td class="info-val" style="width: 21%;">{{ $sede }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Cliente:</td>
            <td class="info-val" colspan="3" style="font-weight: bold; color: #000000;">{{ $cliente }}</td>
            <td class="info-lbl">NIT:</td>
            <td class="info-val mono" style="font-weight: bold;">{{ $cliente_nit }}</td>
        </tr>
        <tr>
            <td class="info-lbl">Proyecto:</td>
            <td class="info-val" colspan="3"><strong>{{ $proyecto }}</strong></td>
            <td class="info-lbl">Vigencia:</td>
            <td class="info-val mono">{{ $vigencia }}</td>
        </tr>
    </table>

    <table class="main-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 10%;">Fecha</th>
                <th style="width: 30%;">Ruta / Descripción Operativa</th>
                <th style="width: 7%;">Inicio</th>
                <th style="width: 11%;">Descanso</th>
                <th style="width: 7%;">Fin</th>
                <th style="width: 7%;">Horas</th>
                <th style="width: 8%;">Km Total</th>
                <th style="width: 10%;">Estado</th>
                <th style="width: 10%;">Conductor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $f)
                <tr>
                    <td class="txt-center">
                        <div class="mono" style="font-weight: bold;">{{ $f['fecha'] }}</div>
                        <div style="font-size: 5.8pt; color: #262626;">{{ $f['dia_semana'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight: bold; color: #000000;">{{ $f['ruta'] }}</div>
                        <div style="font-size: 5.8pt; color: #262626;">Resp.: {{ $f['funcionario'] }}</div>
                    </td>
                    <td class="txt-center mono">{{ $f['hora_inicio'] }}</td>
                    <td class="txt-center mono" style="font-size: 6.3pt;">{{ $f['descanso'] }}</td>
                    <td class="txt-center mono">{{ $f['hora_fin'] }}</td>
                    <td class="num">{{ $f['total_horas'] }}</td>
                    <td class="num" style="color: #000000;">{{ $f['km_total'] }} km</td>
                    <td class="txt-center">
                        @if ($f['estado'] === 'CERRADA')
                            <span class="tag tag-ok">Cerrada</span>
                        @elseif ($f['estado'] === 'OBSERVACIÓN')
                            <span class="tag tag-warn">Novedad</span>
                        @else
                            <span class="tag tag-alert">Pendiente</span>
                        @endif
                    </td>
                    <td style="font-size: 6.3pt;">{{ $f['conductor'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="banda-alerta">
        <strong>PROTOTIPO A (INSTITUCIONAL SGC):</strong> {{ $alerta }}
    </div>

    <table class="cierre" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table class="totals-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 22%; font-weight: bold; text-align: left; text-transform: uppercase;">Consolidado</td>
                        <td style="width: 20%;">Días: <strong>{{ $totales['dias'] }}</strong> ({{ $totales['cerrados'] }} cerr.)</td>
                        <td style="width: 18%;">Horas: <strong class="mono">{{ $totales['horas'] }}</strong></td>
                        <td style="width: 20%;">Recorrido: <strong class="mono" style="color: #000000;">{{ number_format($totales['km'], 0, ',', '.') }} km</strong></td>
                        <td style="width: 20%;">Peajes ({{ $totales['peajes'] }}): <strong class="mono">{{ $totales['valor_peajes'] }}</strong></td>
                    </tr>
                </table>

                <table class="obs-table" cellpadding="0" cellspacing="0">
                    <tr><td class="obs-head">Observaciones y Control Operacional</td></tr>
                    <tr><td class="obs-body">{{ $observaciones }}</td></tr>
                </table>

                <table class="firmas-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 40%;">
                            <div class="sig-space">[ FIRMA DIGITAL VERIFICADA ]</div>
                            <div class="sig-line">
                                <div class="sig-name">{{ $conductor_pie }}</div>
                                <div class="sig-role">Conductor · Entrega conforme</div>
                            </div>
                        </td>
                        <td style="width: 40%;">
                            <div class="sig-space">[ CERTIFICACIÓN PENDIENTE ]</div>
                            <div class="sig-line">
                                <div class="sig-name">{{ $coordinador_pie }}</div>
                                <div class="sig-role">Coordinador Logístico · Verificación</div>
                            </div>
                        </td>
                        <td style="width: 20%; vertical-align: middle;">
                            <img src="data:image/png;base64,{{ $qr_png_base64 }}" style="width: 46px; height: 46px;" alt="QR">
                            <div style="font-size: 5.5pt; color: #262626; margin-top: 1px;">Validar documento</div>
                        </td>
                    </tr>
                </table>

                <div class="legal-box">
                    <strong>CERTIFICACIÓN PESV:</strong> Documento generado conforme a la Ley 1503 de 2011, Decreto 1079 de 2015 y Resolución 20223040040595 de 2022 del Ministerio de Transporte.
                </div>

                <table class="footer-meta" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 70%; text-align: left;">{{ $empresa_transportadora }} · Prototipo A (Institucional SGC Refinado)</td>
                        <td style="width: 30%; text-align: right;" class="mono">{{ $codigo_sgc }} · v{{ $version_sgc }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
