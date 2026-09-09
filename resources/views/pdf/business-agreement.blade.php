<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Convenio de Colaboración Empresarial - {{ $data['agreement_internal_id'] }}</title>
    <style>
        @page {
            size: letter;
            margin: 0cm 0cm;
        }

        /**
        * Define los márgenes reales del contenido de tu PDF
        * Aquí arreglarás los márgenes del encabezado y pie de página
        * De tu imagen de fondo.
        **/
        body {
            margin-top: 2.54cm;
            margin-bottom: 2.54cm;
            margin-left: 2.54cm;
            margin-right: 2.54cm;
            font-family: Arial, sans-serif;
            font-size: 10px;
            padding: 0;
            color: #000;
            background-color: #fff;
        }

        #watermark {
            position: fixed;
            top: 25%;
            left: 15%;
            width: 70%;
            height: auto;
            z-index: -1000;
        }

        #watermark img {
            width: 100%;
            height: auto;
            opacity: 0.15;
        }

        .main-container {
            width: 100%;
            border: 1.2px solid #000;
            border-radius: 8px;
            overflow: hidden;
            border-collapse: separate;
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td {
            border: 0.5px solid #000;
            padding: 2px 4px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .label {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-justify {
            text-align: justify;
        }

        h3 {
            font-size: 13px;
            margin: 2px 0;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 4px;
        }

        .header-table td {
            width: 33.33%;
            vertical-align: middle;
            text-align: center;
            border: none;
            padding: 2px;
        }

        .logo-header {
            max-width: 100%;
            height: auto;
            max-height: 45px;
            display: inline-block;
        }
    </style>
</head>

<body>
    @if (!empty($data['logo']))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $data['logo'] }}" />
        </div>
    @endif

    <table class="header-table">
        <tr>
            <td>
                @if (!empty($data['transpor']))
                    <img class="logo-header" src="data:image/png;base64,{{ $data['transpor'] }}" alt="MinTransporte">
                @endif
            </td>
            <td>
                @if (!empty($data['super']))
                    <img class="logo-header" src="data:image/png;base64,{{ $data['super'] }}" alt="Super">
                @endif
            </td>
            <td>
                @if (!empty($data['logo']))
                    <img class="logo-header" src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo">
                @endif
            </td>
        </tr>
    </table>

    <div style="text-align: center; margin-bottom: 8px; background-color: #f5f5f5; padding: 5px; border: 1.2px solid #000; border-radius: 6px;">
        <h3 style="font-size: 14pt; margin: 2px 0;">CONVENIO DE COLABORACIÓN EMPRESARIAL</h3>
    </div>

    <div class="text-justify" style="font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.0; margin-bottom: 8px;">
        Entre los suscritos <strong>{{ $data['legal_representative_name'] }}</strong>, mayor de edad,
        identificado con la
        cédula de ciudadanía número {{ $data['document_number'] }} expedida en
        {{ $data['document_number_expedition_place'] }}, quien actúa en su calidad de
        representante legal de la empresa <strong>{{ $data['company_name'] }}</strong>, sociedad legalmente
        constituida, identificada con NIT. {{ $data['nit'] }} de una parte, quien en adelante y para los
        efectos de
        este
        contrato se denominará <strong>TRANSPORTADOR CONTRACTUAL</strong> y de otra parte
        <strong>{{ $data['rep_name'] }}</strong>, también mayor de edad,
        identificado con la cédula de ciudadanía número No.
        {{ $data['rep_document_id'] }} expedida en Barranquilla, que actúa en su calidad de representante
        legal de la
        empresa
        <strong>{{ $data['contracting_entity_name'] }}</strong>, sociedad legalmente
        constituida, identificada con NIT {{ $data['contracting_entity_nit'] }}, quien en adelante y para
        los efectos
        de este contrato se
        denominará <strong>TRANSPORTADOR DE HECHO</strong>, y quienes en conjunto se denominarán LAS PARTES
        y
        que se encuentran legalmente habilitadas para la prestación de servicios de trasporte especial;
        hemos
        acordado celebrar el presente Convenio de Colaboración Empresarial conforme lo normado en el
        Decreto 1079 de 2015 y Decreto 431 de 2017, expedidas por el Ministerio de Transporte y demás normas
        complementarias o que las sustituyan, y en especial por lo dispuesto en las siguientes clausulas:
        <strong>PRIMERA - OBJETO:</strong> Por medio del presente convenio el TRANSPORTADOR DE HECHO pone a
        disposición del TRANSPORTADOR CONTRACTUAL el vehículo descrito a continuación, cuyas
        características se describen a continuación para la prestación del servicio de transporte a las
        personas
        naturales que ostenten la calidad de representantes de un grupo específico de usuarios, empresas y/o
        sociedades comerciales con las cuales TRANSPORTADOR CONTRACTUAL tenga algún tipo de
        relación.
    </div>

    <div class="main-container">
        <table>
            <tr>
                <td class="label text-center" colspan="4"
                    style="background-color: #e9e9e9;">DATOS DEL VEHÍCULO</td>
            </tr>
            <tr class="text-center">
                <td class="label">PLACA</td>
                <td style="font-size: 11px;"><strong>{{ $data['vehicle']['vehicle_license_plate'] ?? 'N/A' }}</strong></td>
                <td class="label">MODELO</td>
                <td style="font-size: 11px;">{{ $data['vehicle']['vehicle_model'] ?? 'N/A' }}</td>
            </tr>
            <tr class="text-center">
                <td class="label">MARCA</td>
                <td style="font-size: 11px;">{{ $data['vehicle']['vehicle_brand'] ?? 'N/A' }}</td>
                <td class="label">LÍNEA</td>
                <td style="font-size: 11px;">{{ $data['vehicle']['vehicle_line'] ?? 'N/A' }}</td>
            </tr>
            <tr class="text-center">
                <td class="label">CAPACIDAD</td>
                <td style="font-size: 11px;">{{ $data['vehicle']['vehicle_capacity'] ?? 'N/A' }}</td>
                <td class="label">TARJETA OPERACIÓN</td>
                <td style="font-size: 11px;">{{ $data['vehicle']['operational_card_number'] ?? 'N/A' }}</td>
            </tr>
            <tr class="text-center">
                <td class="label" colspan="2">VIGENCIA TARJETA DE OPERACIÓN</td>
                <td colspan="2" style="font-size: 11px;">
                    {{ $data['vehicle']['expiration_date'] ? \Carbon\Carbon::parse($data['vehicle']['expiration_date'])->format('d/m/Y') : 'N/A' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="text-justify" style="font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.0; margin-bottom: 8px;">
        <strong>SEGUNDA - DURACIÓN:</strong> El presente convenio de colaboración empresarial tendrá una
        duración
        de 5 (cinco) meses contados a partir de la fecha de firma el mismo pero limitado a los demás
        contratos
        suscritos con el transportador. No obstante, podrá ser terminado de forma unilateral el presente
        contrato
        por parte del TRANSPORTADOR CONTRACTUAL en cualquier momento, y previo aviso notificando
        dicha decisión por medio de correo electrónico.
        <strong>TERCERA - OBLIGACIONES DE LAS PARTES:</strong> TRANSPORTADOR CONTRACTUAL: a)
        Administrar en debida forma el vehículo b) Realizar inspecciones permanentes al vehículo para
        garantizar su buen estado d) Radicar el presente convenio una vez legalizado por las partes en el
        Ministerio de Transporte y en la Superintendencia de Puertos y Transportes. TRANSPORTADOR DE
        HECHO: a) Garantizar que los vehículos de su parque automotor destinados para prestar el transporte
        objeto del presente convenio, estén vinculados legalmente al servicio público de transporte
        terrestre
        automotor especial, con todos los documentos exigidos en la ley para la prestación del servicio
        incluyendo tarjeta de operación vigente, pólizas de responsabilidad civil contractual y
        extracontractual
        en cuantía mínima de 100 SMLMV, SOAT, REVISION TECNICO MECANICA, Y PREVENTIVAS
        de conformidad con la Resolución 315 de 2013 y demás normas concordantes b) poner a disposición del
        TRANSPORTADOR CONTRACTUAL vehículos que cuentan con las condiciones de homologación
        para la prestación de servicios de transporte público automotor especial establecidas por el
        Ministerio
        de Transporte y el Código Nacional de Tránsito c) Velar por que el propietario o quien corresponda
        se
        haga responsable por las multas, sanciones y demás costos que se generen por fallas en los
        documentos
        del vehículo o por infracciones en las normas de tránsito.

        <strong>CUARTA - EXTRACTO DE CONTRATO:</strong> EL TRANSPORTADOR CONTRACTUAL, será la
        encargada de expedir el Extracto de contrato exclusivamente sobre la prestación del servicio objeto
        de
        este convenio y previa presentación de paz y salvo emitido por el TRANSPORTADOR DE HECHO en

        <strong>QUINTA - VALOR Y FORMA DE PAGO:</strong> El valor es de cuantía indeterminada pero
        determinable por
        el valor que resulte de la operación matemática y porcentual de los servicios efectivamente
        prestados,
        los cuales se liquidan y se pactan de común acuerdo por las partes. El transportador de hecho
        autoriza
        que los dineros producto de la prestación del servicio una vez descontados los valores
        correspondientes
        a salarios, seguridad social, prestaciones sociales del conductor y demás descuentos que se deriven
        de la
        ejecución del contrato, sean consignados directamente al propietario del vehículo.

        <strong>SEXTA - INDEMNIDAD DE LA EMPRESA CONTRATISTA FRENTE AL PROPIETARIO Y/O
            TENEDOR DEL VEHICULO:</strong> Con la celebración del presente convenio de colaboración
        empresarial,
        el TRANSPORTADOR CONTRACTUAL no se obliga de ninguna manera a garantizar la contratación
        mínima o prioritaria de los vehículos objeto del presente contrato, pues se entiende que se acude a
        ellos
        por la demanda requerida sin que ello genere obligación alguna de mantenerlos en constante
        contratación
        u operación.<br><br>

        Una vez leído el presente documento por los que intervienen, lo aprueban en su integridad y en
        prueba
        de su consentimiento, firman en la ciudad de <strong>{{ $data['signing_city'] }}</strong> a los
        <strong>{{ \Carbon\Carbon::parse($data['signing_date'])->day }}</strong> días del mes de
        <strong>{{ \Carbon\Carbon::parse($data['signing_date'])->translatedFormat('F') }}</strong> de
        <strong>{{ \Carbon\Carbon::parse($data['signing_date'])->year }}</strong>.
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <tr>
            <td style="border: none; width: 50%; padding: 5px;" class="text-center">
                <div style="height: 45px; display: flex; align-items: center; justify-content: center;">
                    @if (!empty($data['firma']))
                        <img src="data:image/png;base64,{{ $data['firma'] }}"
                            style="max-height: 40px; max-width: 150px; display: block; margin: 0 auto;">
                    @endif
                </div>
                <div style="border-top: 1px solid #000; margin: 0 40px; padding-top: 5px; font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.0;">
                    <strong>{{ $data['legal_representative_name'] }}</strong><br>
                    C.C. {{ $data['document_number'] }}<br>
                    TRANSPORTADOR CONTRACTUAL
                </div>
            </td>
            <td style="border: none; width: 50%; padding: 5px;" class="text-center">
                <div style="height: 45px;"></div>
                <div style="border-top: 1px solid #000; margin: 0 40px; padding-top: 5px; font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.0;">
                    <strong>{{ $data['rep_name'] }}</strong><br>
                    C.C. {{ $data['rep_document_id'] }}<br>
                    TRANSPORTADOR DE HECHO
                </div>
            </td>
        </tr>
    </table>

</body>

</html>
