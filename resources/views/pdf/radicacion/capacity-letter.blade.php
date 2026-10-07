<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Solicitud de capacidad transportadora</title>
    @include('pdf.radicacion.partials.styles')
</head>

<body>
    @include('pdf.radicacion.partials.header')

    <div class="titulo">Solicitud de capacidad transportadora</div>

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

    <div class="titulo" style="font-size: 10.5pt; margin: 10px 0;">Asunto: solicitud de capacidad transportadora</div>

    <p class="cuerpo">
        En mi calidad de representante legal de la sociedad
        <strong>{{ $empresa['razon_social'] ?? '' }}</strong>, con identificación
        tributaria NIT {{ $empresa['nit'] ?? '' }}, respetuosamente me permito solicitar
        se expida el certificado de disponibilidad de capacidad transportadora para el
        siguiente vehículo:
    </p>

    <table class="datos">
        @if (! empty($vehiculo['placa']))
            <tr>
                <th>Placa</th>
                <td>{{ $vehiculo['placa'] }}</td>
            </tr>
        @endif
        <tr>
            <th>Marca</th>
            <td>{{ $vehiculo['marca'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Línea</th>
            <td>{{ $vehiculo['linea'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Modelo</th>
            <td>{{ $vehiculo['modelo'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Servicio</th>
            <td>{{ $vehiculo['servicio'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Cilindraje</th>
            <td>{{ $vehiculo['cilindraje'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Tipo de carrocería</th>
            <td>{{ $vehiculo['carroceria'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Clase de vehículo</th>
            <td>{{ $vehiculo['clase'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Número de motor</th>
            <td>{{ $vehiculo['numero_motor'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Número de chasis / VIN</th>
            <td>{{ $vehiculo['vin'] ?: ($vehiculo['numero_chasis'] ?? '') }}</td>
        </tr>
    </table>

    <p class="cuerpo">
        La presente solicitud se radica con el fin de incorporarlo al parque automotor de la
        empresa y expedir la correspondiente tarjeta de operación, de conformidad con la
        normativa vigente aplicable al servicio público de transporte terrestre automotor
        especial.
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