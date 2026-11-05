<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Acta de desvinculación y paz y salvo</title>
    @include('pdf.radicacion.partials.styles')
</head>

<body>
    @include('pdf.radicacion.partials.header')

    <div class="titulo">Acta de desvinculación y paz y salvo</div>

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
        En la fecha indicada, <strong>{{ $empresa['razon_social'] ?? '' }}</strong>, identificada con NIT
        {{ $empresa['nit'] ?? '' }}, representada legalmente por
        <strong>{{ $empresa['representante'] ?? '' }}</strong>, hace constar la desvinculación del vehículo
        de placa <strong>{{ $vehiculo['placa'] ?? '' }}</strong> del parque automotor de la empresa, por la
        causal de <strong>{{ $expediente['paso'] === 'DESVINCULACION_UNILATERAL' ? 'desvinculación unilateral' : 'mutuo acuerdo' }}</strong>.
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
            <th>Propietario / afiliado</th>
            <td>{{ $propietario['nombre'] ?? '' }}
                @if (! empty($propietario['documento']))
                    — {{ $propietario['documento'] }}
                @endif
            </td>
        </tr>
        <tr>
            <th>Paz y salvo</th>
            <td>{{ $expediente['paz_y_salvo'] ?? 'Expedido con la presente acta' }}</td>
        </tr>
        @if (! empty($expediente['resolucion']))
            <tr>
                <th>Resolución</th>
                <td>{{ $expediente['resolucion'] }}</td>
            </tr>
        @endif
    </table>

    <p class="cuerpo">
        La empresa declara que el vehículo se encuentra a paz y salvo por todo concepto con la compañía y
        que no existen obligaciones pendientes derivadas de la vinculación. Como consecuencia de la
        desvinculación, se libera el cupo de capacidad transportadora correspondiente y se solicita la
        cancelación de la tarjeta de operación ante el organismo de tránsito competente.
    </p>

    @include('pdf.radicacion.partials.signatures', [
        'izquierda' => [
            'firma' => $empresa['firma_representante'] ?? null,
            'nombre' => $empresa['representante'] ?? '',
            'documento' => $empresa['documento_representante'] ?? '',
            'cargo' => 'Representante Legal',
        ],
        'derecha' => [
            'firma' => $propietario['firma'] ?? null,
            'nombre' => $propietario['nombre'] ?? '',
            'documento' => $propietario['documento'] ?? '',
            'cargo' => 'Propietario / Afiliado',
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
