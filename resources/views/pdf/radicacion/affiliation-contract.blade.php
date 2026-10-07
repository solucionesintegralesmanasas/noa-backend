<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Contrato de vinculación por administración de flota</title>
    @include('pdf.radicacion.partials.styles')
</head>

<body>
    @include('pdf.radicacion.partials.header')

    <div class="titulo">Contrato de vinculación por administración de flota</div>

    <p class="cuerpo">
        Suscritos entre <strong>{{ $empresa['razon_social'] ?? '' }}</strong>, sociedad
        legalmente constituida, identificada con NIT {{ $empresa['nit'] ?? '' }}, representada
        legalmente por <strong>{{ $empresa['representante'] ?? '' }}</strong>, identificado con
        cédula de ciudadanía número {{ $empresa['documento_representante'] ?? '' }}, en adelante
        la <strong>EMPRESA</strong>; y por otra parte <strong>{{ $propietario['nombre'] ?? '' }}</strong>,
        identificado con cédula de ciudadanía número {{ $propietario['documento'] ?? '' }},
        {{ $propietario['es_propietario'] ? 'en su calidad de propietario del vehículo' : 'en su calidad de afiliado' }},
        en adelante el <strong>PROPIETARIO</strong>; hemos acordado celebrar el presente contrato,
        que se regirá por las siguientes cláusulas:
    </p>

    <div class="referencia">
        <table class="datos">
            <tr>
                <th style="width: 50%;">Contrato número</th>
                <td style="width: 50%;">{{ $vinculacion['numero'] ?? '' }}</td>
            </tr>
            <tr>
                <th>Fecha de emisión</th>
                <td>{{ $vinculacion['fecha_emision'] ?? '' }}</td>
            </tr>
            <tr>
                <th>Fecha de inicio</th>
                <td>{{ $vinculacion['fecha_inicio'] ?? '' }}</td>
            </tr>
            <tr>
                <th>Fecha de terminación</th>
                <td>{{ $vinculacion['fecha_fin'] ?? '' }}</td>
            </tr>
            <tr>
                <th>Lugar</th>
                <td>{{ $expediente['ciudad'] ?? $empresa['ciudad'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    <h3 style="font-size: 10.5pt; margin: 12px 0 6px;">1. Identificación del vehículo</h3>
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

    <p class="clausula"><strong>Primero: objeto.</strong> Incorporar al parque automotor de la
        empresa el vehículo descrito en la cláusula anterior. El propietario deberá mantenerlo en
        óptimas condiciones. La custodia material y la administración por parte de la empresa se
        ejercerán cuando el vehículo esté en poder efectivo de la empresa; de lo contrario, la
        responsabilidad es del propietario.</p>

    <p class="clausula"><strong>Segundo: estado del vehículo.</strong> El propietario suministrará
        el vehículo en perfectas condiciones. Los imprevistos deberán repararse en el menor tiempo
        posible. El mantenimiento preventivo se hará en centros técnicos designados por la empresa
        conforme a la Resolución 315 de 2013. Los daños no reparados dejarán el vehículo cesante y
        sin pago de tiempo de standby. El mantenimiento y las reparaciones son por cuenta del
        propietario. Si no existe póliza todo riesgo, la empresa no responde por los daños; los
        deducibles son por cuenta del propietario.</p>

    <p class="clausula"><strong>Tercera: obligaciones de la empresa.</strong> La empresa se obliga a:
        (1) cumplir las leyes de transporte; (2) incorporar el vehículo en la capacidad
        transportadora del servicio especial nacional; (3) obtener la tarjeta de operación;
        (4) ejercer control efectivo; (5) no prestar el servicio a personas no autorizadas;
        (6) garantizar la utilización del vehículo y facilitar los cambios de empresa, llevando un
        plan de rodamiento actualizado; (7) capacitar al personal; (8) realizar el mantenimiento
        preventivo y las revisiones; (9) abstenerse de incluir vehículos en mal estado;
        (10) permitir la operación en las rutas autorizadas; (11) solicitar las pólizas de
        responsabilidad civil contractual y extracontractual por el monto exigido por la ley;
        (12) informar los contratos asignados; (13) informar las resoluciones expedidas;
        (14) suministrar paz y salvos; (15) suministrar los extractos conforme a la Resolución 6652
        de 2019; (16) informar los accidentes; (17) asumir la responsabilidad como empleador o
        coempleador, con derecho de repetir contra el propietario; y (18) generar el Formato
        Único de Extracto del Contrato (FUEC).</p>

    <p class="clausula"><strong>Cuarta: obligaciones del propietario.</strong> El propietario se
        obliga a: (1) entregar los documentos para la renovación de la tarjeta de operación con tres
        (3) meses de anticipación; (2) no suscribir contratos bajo su propio nombre; (3) no usar el
        vehículo para servicios no autorizados; (4) no operar sin conductor autorizado; (5) cancelar
        sus propias obligaciones financieras; (6) no cambiar el vehículo, salvo reposición por
        chatarrización o hurto dentro de los seis (6) meses siguientes; (7) pagar la cuota
        administrativa mensual pactada; (8) solicitar la desvinculación con dos (2) meses de
        antelación; (9) pagar los daños no cubiertos por los seguros; (10) ser leal con la
        empresa; (11) suministrar las autorizaciones para la entrega de vehículos inmovilizados;
        (12) efectuar la chatarrización al cumplir la vida útil, que no podrá exceder de veinte (20)
        años; (13) reponer el vehículo por pérdida o hurto dentro de los seis (6) meses siguientes;
        (14) realizar las adecuaciones para transporte escolar si fuere el caso; (15) aportar los
        documentos iniciales y cumplir con el mantenimiento; (16) pagar las infracciones;
        (17) pagar las multas impuestas por la Superintendencia y la autoridad de tránsito;
        (18) pagar las primas de los seguros de responsabilidad civil contractual y
        extracontractual, así como del SOAT; (19) implementar los emblemas de la empresa;
        (20) no usar el vehículo para publicidad sin autorización; (21) actualizar la documentación
        para entrar al plan de rodamiento; (22) no reproducir obras musicales o audiovisuales de
        terceros; (23) instalar el módulo de control de flota (GPS) en un plazo de diez (10) días;
        (24) mantener los datos de contacto actualizados; (25) cumplir las rutas y el plan de
        rodamiento; (26) acatar las políticas de seguridad vial y del sistema de gestión de
        seguridad y salud en el trabajo; (27) cumplir las medidas de bioseguridad; y (28) no
        negociar la capacidad transportadora con terceros.</p>

    <p class="clausula"><strong>Parágrafo de buenas prácticas.</strong> Queda prohibido contratar
        directamente con clientes de la empresa. El incumplimiento de esta disposición constituye
        causal de terminación del contrato y de ejecución del pagaré.</p>

    <p class="clausula"><strong>Quinta: duración.</strong> El presente contrato tendrá una duración
        de dos (2) años contados a partir de su fecha de inicio. No procederá prórroga automática;
        cualquier renovación deberá pactarse expresamente por escrito.</p>

    <p class="clausula"><strong>Sexta: valores a cancelar.</strong> La empresa reconoce al propietario
        un mínimo del sesenta y cinco por ciento (65 %) de los servicios prestados, pagaderos a noventa
        (90) días. El tiempo en que el vehículo esté cesante no se reconoce. De esos valores se
        descontarán los gastos operacionales tales como salarios, combustible y mantenimientos.</p>

    <p class="clausula"><strong>Séptima: datos personales.</strong> El propietario autoriza el
        tratamiento de sus datos personales conforme a la Ley 1581 de 2012 y el reporte a centrales de
        riesgo.</p>

    <p class="clausula"><strong>Octava: convenios.</strong> En los convenios de colaboración la
        responsabilidad corresponde al transportador contractual, pero la vinculación del personal la
        realiza el transportador de hecho, esto es, la empresa.</p>

    <p class="clausula"><strong>Novena: terminación.</strong> El contrato podrá terminarse por mutuo
        acuerdo, por cambio de propiedad sin aprobación, por desvinculación, por vencimiento del
        término, por violación de sus cláusulas, por perjuicios causados a la empresa, por
        irrespeto, por riñas, por liquidación de la empresa, por sentencia judicial o por
        terminación anticipada decretada por la empresa con dos (2) meses de aviso previo.</p>

    <p class="clausula"><strong>Décima: incremento de costos.</strong> La cuota administrativa es
        pagadera en los primeros cinco (5) días de cada mes y se incrementa anualmente conforme al
        índice de precios al consumidor y al salario mínimo legal vigente. El derecho de admisión no
        es reembolsable.</p>

    <p class="clausula"><strong>Decimoprimera: no ingreso al plan de rodamiento.</strong> Por
        incumplimiento económico, la empresa puede abstenerse de expedir el extracto y de poner el
        vehículo en operación.</p>

    <p class="clausula"><strong>Decimosegunda a decimoquinta.</strong> Cuotas extraordinarias;
        retiro del vehículo, sujeto a la presentación del paz y salvo; y afectación de otros
        vehículos en garantía general.</p>

    <p class="clausula"><strong>Decimosexta: vida útil.</strong> La vida útil máxima del vehículo
        será de veinte (20) años. La reposición debe manifestarse con dos (2) meses de anticipación.</p>

    <p class="clausula"><strong>Decimoséptima: cesión.</strong> La cesión del contrato requiere
        consentimiento previo y escrito de la empresa. La venta del vehículo implica el pago de un
        (1) salario mínimo legal mensual vigente a favor de la empresa por derecho de cesión.</p>

    <p class="clausula"><strong>Decimoctava a vigésima: indemnidad y obligaciones monetarias.</strong>
        Las indemnizaciones por terminación anticipada, la sustitución del contrato y las
        obligaciones monetarias derivatives, incluido el cobro de hasta el diez por ciento (10 %)
        del valor cuando el propietario gestione el contrato por su cuenta.</p>

    <p class="clausula"><strong>Vigésima primera a vigésima cuarta:</strong> autorización para
        deducir o retener sumas de dinero, constitución de garantías, vigencia del pagaré por diez
        (10) años y expedición del paz y salvo.</p>

    <p class="clausula"><strong>Vigésima quinta: intereses y renuncia.</strong> Los intereses
        moratorios se causarán a la tasa máxima legal vigente. El propietario renuncia expresamente a
        la presentación de requerimientos judiciales y extrajudiciales.</p>

    <p class="clausula"><strong>Vigésima sexta y vigésima séptima: notificación y mérito ejecutivo.</strong>
        Las notificaciones se harán por escrito a las direcciones registradas. El presente contrato
        presta mérito ejecutivo como título valor.</p>

    <p class="clausula"><strong>Vigésima octava: cancelación de póliza.</strong> Por falta de pago
        de la prima se cancelará la tarjeta de operación y se desvinculará el vehículo.</p>

    <p class="clausula"><strong>Vigésima novena: obligaciones posteriores a la desvinculación.</strong>
        El propietario deberá retirar los emblemas de la empresa en un plazo de cinco (5) días.</p>

    <p class="clausula"><strong>Trigésima: cláusula penal.</strong> El incumplimiento de las
        obligaciones pactadas genera a favor de la empresa, como mínimo, las siguientes sumas:</p>

    <table class="datos">
        <thead>
            <tr>
                <th style="width: 76%;">Hecho</th>
                <th style="width: 24%;">Sanción</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Incumplimiento de la habilitación</td>
                <td>3 salarios mínimos legales vigentes</td>
            </tr>
            <tr>
                <td>Incumplimiento de las políticas de seguridad vial y del SG-SST</td>
                <td>5 salarios mínimos legales vigentes</td>
            </tr>
            <tr>
                <td>Reclamaciones civiles o no reporte de accidentes</td>
                <td>20 salarios mínimos legales vigentes</td>
            </tr>
            <tr>
                <td>Terminación unilateral de conductores</td>
                <td>20 salarios mínimos legales vigentes</td>
            </tr>
            <tr>
                <td>Adulteración de documentos</td>
                <td>30 salarios mínimos legales vigentes</td>
            </tr>
        </tbody>
    </table>

    <p class="clausula"><strong>Trigésima primera: contratación del conductor.</strong> El personal
        se afilia por la empresa, pero su pago lo asume el propietario. Los conductores deben pasar
        selección, exámenes médicos y pruebas psicosensométricas.</p>

    <p class="clausula"><strong>Trigésima segunda y compromisoria.</strong> El presente contrato
        reemplaza todos los contratos anteriores entre las mismas partes. Las diferencias que se
        susciten se dirimirán en la jurisdicción ordinaria de {{ $expediente['ciudad'] ?? 'Sincelejo' }}.</p>

    @include('pdf.radicacion.partials.signatures', [
        'izquierda' => [
            'firma' => $empresa['firma_representante'] ?? null,
            'nombre' => $empresa['representante'] ?? '',
            'documento' => $empresa['documento_representante'] ?? '',
            'cargo' => 'Representante Legal — ' . ($empresa['razon_social'] ?? ''),
        ],
        'derecha' => [
            'firma' => $vinculacion['firma_propietario'] ?? null,
            'nombre' => $propietario['nombre'] ?? '',
            'documento' => $propietario['documento'] ?? '',
            'cargo' => $propietario['es_propietario'] ? 'Propietario del vehículo' : 'Afiliado',
        ],
    ])

    <div class="pie">
        {{ $empresa['razon_social'] ?? '' }}
        @if (! empty($empresa['nit']))
            · NIT. {{ $empresa['nit'] }}
        @endif
        · Contrato {{ $vinculacion['numero'] ?? '' }}
    </div>
</body>

</html>