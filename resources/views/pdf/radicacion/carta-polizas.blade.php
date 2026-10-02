<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Inclusión de pólizas</title>
    @include('pdf.radicacion._estilos')
</head>

<body>
    @include('pdf.radicacion._encabezado')

    <div class="titulo">Inclusión de pólizas</div>

    <div class="destinatario">
        <p><strong>{{ $polizas['rcc_aseguradora'] ?? 'Compañía aseguradora' }}</strong></p>
        <p>Ciudad</p>
    </div>

    <div class="referencia">
        <p><strong>Fecha:</strong>
            {{ $expediente['fecha_radicacion'] ?? \Carbon\Carbon::now()->format('d/m/Y') }}
            &nbsp;&nbsp;&nbsp;&nbsp;
            <strong>Ciudad:</strong> {{ $expediente['ciudad'] ?? $empresa['ciudad'] ?? '' }}
        </p>
        @if (! empty($vehiculo['placa']))
            <p><strong>Asunto:</strong> inclusión de pólizas del vehículo de placa
                {{ $vehiculo['placa'] }}</p>
        @endif
    </div>

    <p class="cuerpo">
        Muy respetuosamente me dirijo a ustedes en mi calidad de representante legal de la empresa
        <strong>{{ $empresa['razon_social'] ?? '' }}</strong>, con identificación tributaria NIT
        {{ $empresa['nit'] ?? '' }}, para solicitar la inclusión de las pólizas del vehículo
        relacionado en el asunto.
    </p>

    <table class="datos">
        <thead>
            <tr>
                <th style="width: 42%;">Tomador y/o asegurado</th>
                <th style="width: 20%;">NIT</th>
                <th style="width: 22%;">N.° de póliza</th>
                <th style="width: 16%;">Ramo</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $empresa['razon_social'] ?? '' }}</td>
                <td>{{ $empresa['nit'] ?? '' }}</td>
                <td>{{ $polizas['rcc_numero'] ?? '' }}</td>
                <td>RCC</td>
            </tr>
            <tr>
                <td>{{ $empresa['razon_social'] ?? '' }}</td>
                <td>{{ $empresa['nit'] ?? '' }}</td>
                <td>{{ $polizas['rce_numero'] ?? '' }}</td>
                <td>RCE</td>
            </tr>
        </tbody>
    </table>

    <p class="cuerpo">
        Lo anterior con el fin de dar cumplimiento a lo exigido para la operación del vehículo en
        el servicio público de transporte terrestre automotor especial, y para que las pólizas queden
        vinculadas a la tarjeta de operación del vehículo
        @if (! empty($vehiculo['placa']))
            de placa {{ $vehiculo['placa'] }}
        @endif
        @if (! empty($vehiculo['numero_motor']))
            , cuyo número de motor es {{ $vehiculo['numero_motor'] }}
        @endif
        .
    </p>

    <p class="cuerpo">
        Agradezco su atención y les solicito enviar la confirmación de la inclusión y el certificado
        de cobertura a nombre de la empresa.
    </p>

    @include('pdf.radicacion._firmas', [
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