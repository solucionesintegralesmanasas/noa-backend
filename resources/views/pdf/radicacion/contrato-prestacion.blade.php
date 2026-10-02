<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Contrato de prestación de servicios de transporte especial</title>
    @include('pdf.radicacion._estilos')
</head>

<body>
    @include('pdf.radicacion._encabezado')

    <div class="titulo">Contrato de prestación de servicios de transporte especial</div>

    <p class="cuerpo">
        Suscritos entre <strong>{{ $empresa['razon_social'] ?? '' }}</strong>, sociedad legalmente
        constituida, identificada con NIT {{ $empresa['nit'] ?? '' }}, representada legalmente por
        <strong>{{ $empresa['representante'] ?? '' }}</strong>, identificado con cédula de ciudadanía
        número {{ $empresa['documento_representante'] ?? '' }}, en adelante el
        <strong>CONTRATANTE</strong>; y por otra parte <strong>{{ $propietario['nombre'] ?? '' }}</strong>,
        identificado con cédula de ciudadanía número {{ $propietario['documento'] ?? '' }},
        {{ $propietario['es_propietario'] ? 'en su calidad de propietario del vehículo' : 'en su calidad de afiliado' }},
        en adelante el <strong>CONTRATISTA</strong>; hemos acordado celebrar el presente contrato,
        que se regirá por las siguientes cláusulas:
    </p>

    <div class="referencia">
        <table class="datos">
            <tr>
                <th style="width: 50%;">Contrato número</th>
                <td style="width: 50%;">{{ $prestacion['numero'] ?? '' }}</td>
            </tr>
            <tr>
                <th>Fecha de emisión</th>
                <td>{{ $prestacion['fecha_emision'] ?? '' }}</td>
            </tr>
            <tr>
                <th>Fecha de inicio</th>
                <td>{{ $prestacion['fecha_inicio'] ?? '' }}</td>
            </tr>
            <tr>
                <th>Fecha de terminación</th>
                <td>{{ $prestacion['fecha_fin'] ?? '' }}</td>
            </tr>
            <tr>
                <th>Cobertura</th>
                <td>{{ $prestacion['cobertura'] ?? '' }}</td>
            </tr>
            <tr>
                <th>Lugar</th>
                <td>{{ $expediente['ciudad'] ?? $empresa['ciudad'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    <h3 style="font-size: 10.5pt; margin: 12px 0 6px;">1. Vehículo contratado</h3>
    <table class="datos">
        <tr>
            <th>Placa</th>
            <td>{{ $vehiculo['placa'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Clase de vehículo</th>
            <td>{{ $vehiculo['clase'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Marca y línea</th>
            <td>{{ $vehiculo['marca'] ?? '' }} {{ $vehiculo['linea'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Modelo</th>
            <td>{{ $vehiculo['modelo'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Número de motor</th>
            <td>{{ $vehiculo['numero_motor'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Número de chasis</th>
            <td>{{ $vehiculo['numero_chasis'] ?? '' }}</td>
        </tr>
    </table>

    <h3 style="font-size: 10.5pt; margin: 12px 0 6px;">2. Cláusulas</h3>

    <p class="clausula"><strong>Primera: vehículo contratado.</strong> Se contrata el vehículo
        automotor con las características detalladas en la tabla anterior.</p>

    <p class="clausula"><strong>Segunda: valor y forma de pago.</strong> La prestación del servicio
        tendrá el valor que se registre en la plataforma para el presente contrato. El pago se realizará
        dentro del plazo que se indique en el cronograma de pagos, contado a partir del vencimiento del
        mes que se presta.</p>

    <p class="clausula"><strong>Parágrafo primero.</strong> El término del contrato es el que
        resulte de la fecha de inicio y la fecha de terminación registradas en este documento, y
        podrá ser prorrogado de mutuo acuerdo entre las partes. En caso de retiro por alguna de las
        partes, esta deberá avisar con un (1) mes de anticipación.</p>

    <p class="clausula"><strong>Tercera: conductor.</strong> El contratista destinará un conductor
        para el presente contrato, con todos los documentos de ley vigentes. El contratista se
        responsabilizará del pago de los aportes a seguridad social, así como de los pagos de salarios
        y demás prestaciones que hagan parte de esta relación.</p>

    <p class="clausula"><strong>Cuarta: destinación.</strong> El contratante destinará el vehículo
        al transporte de personas y materiales propio del servicio contratado, y no podrán violarse
        los límites de carga o de pasajeros establecidos por el fabricante. El vehículo será destinado
        únicamente al servicio objeto de este contrato.</p>

    <p class="clausula"><strong>Quinta: incumplimiento.</strong> El incumplimiento de cualquier
        obligación o prohibición descrita en este contrato da derecho a las partes a declararlo
        rescindido.</p>

    @include('pdf.radicacion._firmas', [
        'izquierda' => [
            'firma' => $prestacion['firma_propietario'] ?? null,
            'nombre' => $propietario['nombre'] ?? '',
            'documento' => $propietario['documento'] ?? '',
            'cargo' => 'El Contratista',
        ],
        'derecha' => [
            'firma' => $prestacion['firma_empresa'] ?? null,
            'nombre' => $empresa['representante'] ?? '',
            'documento' => $empresa['documento_representante'] ?? '',
            'cargo' => 'Representante Legal — ' . ($empresa['razon_social'] ?? ''),
        ],
    ])

    <div class="pie">
        {{ $empresa['razon_social'] ?? '' }}
        @if (! empty($empresa['nit']))
            · NIT. {{ $empresa['nit'] }}
        @endif
        · Contrato {{ $prestacion['numero'] ?? '' }}
    </div>
</body>

</html>