<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Pagaré y carta de instrucciones</title>
    @include('pdf.radicacion.partials.styles')
</head>

<body>
    @include('pdf.radicacion.partials.header')

    <div class="titulo">Pagaré No. {{ $vinculacion['numero'] ?? '' }}</div>

    <p class="cuerpo">
        Yo, <strong>{{ $propietario['nombre'] ?? '' }}</strong>, identificado con cédula de
        ciudadanía número {{ $propietario['documento'] ?? '' }}, en mi calidad de
        {{ $propietario['es_propietario'] ? 'propietario' : 'afiliado' }} del vehículo descrito más
        adelante, declaro que debo y pagaré incondicionalmente a la sociedad
        <strong>{{ $empresa['razon_social'] ?? '' }}</strong>, identificada con NIT
        {{ $empresa['nit'] ?? '' }}, en su calidad de acreedora, la suma de dinero que determinen los
        saldos pendientes a mi favor por concepto de la ejecución del contrato de vinculación por
        administración de flota, más los intereses moratorios a la tasa máxima legal vigente y los
        gastos de cobre, si a ello hubiere lugar.
    </p>

    <table class="datos">
        <tr>
            <th style="width: 38%;">Deudor</th>
            <td style="width: 62%;">{{ $propietario['nombre'] ?? '' }}
                @if (! empty($propietario['documento']))
                    — C.C. {{ $propietario['documento'] }}
                @endif
            </td>
        </tr>
        <tr>
            <th>Acreedor</th>
            <td>{{ $empresa['razon_social'] ?? '' }}
                @if (! empty($empresa['nit']))
                    — NIT. {{ $empresa['nit'] }}
                @endif
            </td>
        </tr>
        <tr>
            <th>Vehículo garantía</th>
            <td>
                @if (! empty($vehiculo['placa']))
                    Placa {{ $vehiculo['placa'] }} ·
                @endif
                {{ $vehiculo['marca'] ?? '' }} {{ $vehiculo['linea'] ?? '' }} {{ $vehiculo['modelo'] ?? '' }}
                @if (! empty($vehiculo['numero_motor']))
                    · Motor {{ $vehiculo['numero_motor'] }}
                @endif
                @if (! empty($vehiculo['numero_chasis']))
                    · Chasis {{ $vehiculo['numero_chasis'] }}
                @endif
            </td>
        </tr>
        <tr>
            <th>Fecha de emisión</th>
            <td>{{ $vinculacion['fecha_emision'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Lugar de celebración</th>
            <td>{{ $expediente['ciudad'] ?? $empresa['ciudad'] ?? '' }}</td>
        </tr>
    </table>

    <p class="cuerpo"><strong>Condiciones.</strong> El pago es incondicional, tanto del capital
        como de los intereses. Los intereses moratorios se causarán a la tasa máxima legal vigente.
        El presente título está sujeto a cláusula aceleratoria. El impuesto de timbre que se cause
        con ocasión del cobro corresponde al deudor.</p>

    <h3 style="font-size: 10.5pt; margin: 14px 0 6px;">Carta de instrucciones (artículo 622 del
        Código de Comercio)</h3>

    <p class="cuerpo">
        Autorizo irrevocablemente a <strong>{{ $empresa['razon_social'] ?? '' }}</strong> para que,
        en caso de incumplimiento de mis obligaciones, llene los espacios en blanco de este pagaré
        con la suma que adeude, de acuerdo con la contabilidad de la acreedora, y para que lo cobre
        por vía judicial o extrajudicial,oraussándose del requisito de demanda en firme y de la
        citación previa al deudor, a costa y riesgo del deudor.
    </p>

    <p class="cuerpo">
        Declaro que el presente pagaré se firma con separación de fecha, que los espacios en blanco
        que se llenen no alteran su validez y que el título cumple los requisitos del artículo 622
        del Código de Comercio.
    </p>

    @include('pdf.radicacion.partials.signatures', [
        'izquierda' => [
            'firma' => $vinculacion['firma_propietario'] ?? null,
            'nombre' => $propietario['nombre'] ?? '',
            'documento' => $propietario['documento'] ?? '',
            'cargo' => 'Deudor',
        ],
        'derecha' => [
            'firma' => $empresa['firma_representante'] ?? null,
            'nombre' => $empresa['representante'] ?? '',
            'documento' => $empresa['documento_representante'] ?? '',
            'cargo' => 'Acepto — Acreedor',
        ],
    ])

    <div class="pie">
        {{ $empresa['razon_social'] ?? '' }}
        @if (! empty($empresa['nit']))
            · NIT. {{ $empresa['nit'] }}
        @endif
        · Pagaré {{ $vinculacion['numero'] ?? '' }}
    </div>
</body>

</html>