<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Solicitud de renovación de la tarjeta de operación</title>
    @include('pdf.radicacion.partials.styles')
</head>

<body>
    @include('pdf.radicacion.partials.header')

    <div class="titulo">Solicitud de renovación de la tarjeta de operación</div>

    <div class="destinatario">
        @if (! empty($destinatario['dependencia']))
            <p>{{ $destinatario['dependencia'] }}</p>
        @endif
        <p>{{ $destinatario['entidad'] }}</p>
    </div>

    <div class="referencia">
        <p><strong>Fecha:</strong>
            {{ $expediente['fecha_radicacion'] ?? \Carbon\Carbon::now()->format('d/m/Y') }}
            &nbsp;&nbsp;&nbsp;&nbsp;
            <strong>Ciudad:</strong> {{ $expediente['ciudad'] ?? $empresa['ciudad'] ?? '' }}
        </p>
        @if (! empty($expediente['codigo']))
            <p><strong>Referencia:</strong> {{ $expediente['codigo'] }}</p>
        @endif
    </div>

    <p class="cuerpo">
        Yo, <strong>{{ $empresa['representante'] ?? '' }}</strong>, identificado con cédula de ciudadanía
        número {{ $empresa['documento_representante'] ?? '' }}, en mi calidad de representante legal de
        <strong>{{ $empresa['razon_social'] ?? '' }}</strong>, identificada con NIT
        {{ $empresa['nit'] ?? '' }}, solicito la renovación de la tarjeta de operación del vehículo que se
        relaciona a continuación, por encontrarse dentro del término previsto para tal efecto.
    </p>

    <table class="datos">
        <tr>
            <th>Placa</th>
            <td>{{ $vehiculo['placa'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Marca, línea y modelo</th>
            <td>{{ $vehiculo['marca'] ?? '' }} {{ $vehiculo['linea'] ?? '' }} {{ $vehiculo['modelo'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Número de chasis / VIN</th>
            <td>{{ ($vehiculo['vin'] ?? '') ?: ($vehiculo['numero_chasis'] ?? '') }}</td>
        </tr>
        <tr>
            <th>Tarjeta de operación actual</th>
            <td>{{ $expediente['tarjeta_anterior'] ?? ($vehiculo['tarjeta_operacion'] ?? '') }}</td>
        </tr>
        <tr>
            <th>Propietario / afiliado</th>
            <td>{{ $propietario['nombre'] ?? '' }}
                @if (! empty($propietario['documento']))
                    — {{ $propietario['documento'] }}
                @endif
            </td>
        </tr>
    </table>

    <p class="cuerpo">La empresa declara que el vehículo cumple los siguientes requisitos para la renovación:</p>

    <table class="datos">
        <tr>
            <th>SOAT vigente</th>
            <td>{{ $expediente['soat_valid'] === null ? 'Sí' : ($expediente['soat_valid'] ? 'Sí' : 'No') }}</td>
        </tr>
        <tr>
            <th>Revisión técnico-mecánica vigente</th>
            <td>{{ $expediente['rtm_valid'] === null ? 'Sí' : ($expediente['rtm_valid'] ? 'Sí' : 'No') }}</td>
        </tr>
        <tr>
            <th>SIMIT sin comparendos pendientes</th>
            <td>{{ $expediente['simit_clear'] === null ? 'Sí' : ($expediente['simit_clear'] ? 'Sí' : 'No') }}</td>
        </tr>
    </table>

    <p class="cuerpo">
        La renovación no modifica el cupo de capacidad transportadora de la empresa y se radica con el
        pago de los derechos correspondientes. Cualquier cambio en la información aquí consignada será
        informado oportunamente al Ministerio de Transporte.
    </p>

    @include('pdf.radicacion.partials.signatures', [
        'izquierda' => [
            'firma' => $empresa['firma_representante'] ?? null,
            'nombre' => $empresa['representante'] ?? '',
            'documento' => $empresa['documento_representante'] ?? '',
            'cargo' => 'Representante Legal',
        ],
    ])

    <div class="pie">
        {{ $empresa['razon_social'] ?? '' }}
        @if (! empty($empresa['nit']))
            · NIT. {{ $empresa['nit'] }}
        @endif
        · Expediente {{ $expediente['codigo'] ?? '' }}
    </div>
</body>

</html>
