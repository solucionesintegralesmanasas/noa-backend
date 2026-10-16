<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Carta de aceptación</title>
    @include('pdf.radicacion.partials.styles')
</head>

<body>
    @include('pdf.radicacion.partials.header')

    <div class="titulo">Carta de aceptación</div>

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

    <div class="titulo" style="font-size: 10.5pt; margin: 10px 0;">Asunto: aceptación de la vinculación al parque automotor
        @if (! empty($vehiculo['placa']))
            de la placa {{ $vehiculo['placa'] }}
        @endif
    </div>

    <p class="cuerpo">
        Yo, <strong>{{ $empresa['representante'] ?? '' }}</strong>, mayor de edad, identificado con
        cédula de ciudadanía número {{ $empresa['documento_representante'] ?? '' }}, en mi calidad de
        representante legal de la sociedad <strong>{{ $empresa['razon_social'] ?? '' }}</strong>,
        identificada con NIT {{ $empresa['nit'] ?? '' }}, por medio de la presente me permito aceptar
        la vinculación del vehículo descrito en el expediente de radicación
        @if (! empty($expediente['codigo']))
            <strong>{{ $expediente['codigo'] }}</strong>
        @endif
        al parque automotor de la empresa, para la prestación del servicio público de transporte
        terrestre automotor especial.
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
            <td>{{ $vehiculo['vin'] ?: ($vehiculo['numero_chasis'] ?? '') }}</td>
        </tr>
        <tr>
            <th>Número de motor</th>
            <td>{{ $vehiculo['numero_motor'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Clase de vehículo</th>
            <td>{{ $vehiculo['clase'] ?? '' }}</td>
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

    <p class="cuerpo">
        La empresa acepta expresamente las condiciones del servicio especial y se compromete a
        cumplir la normativa vigente, en particular la Resolución 315 de 2013 y demás disposiciones
        que la modifiquen o adicionen, así como a mantener vigentes la tarjeta de operación, el
        SOAT, la revisión técnico-mecánica y las pólizas de responsabilidad civil contractual y
        extracontractual exigidas por la ley.
    </p>

    <p class="cuerpo">
        Así mismo, la empresa se compromete a incorporar el vehículo en la capacidad transportadora
        correspondiente y a no prestarlo para servicios distintos a los autorizados, bajo su
        responsabilidad técnica y administrativa.
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