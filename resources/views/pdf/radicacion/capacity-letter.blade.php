<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Carta de solicitud de capacidad transportadora</title>
    @include('pdf.radicacion.partials.styles')
    <style>
        .cl-carta {
            font-size: 8.1pt;
            line-height: 1.28;
            color: #111827;
            text-align: left;
        }

        .cl-carta .encabezado {
            margin-bottom: 6px;
        }

        /* Bloque destinatario neutro */
        .cl-destinatario {
            background-color: var(--pdf-tenue);
            border: 0.8px solid var(--pdf-borde-interno);
            border-left: 2.5px solid var(--pdf-borde);
            padding: 4.5px 8px;
            margin-bottom: 6px;
        }

        .cl-destinatario .cl-etiqueta {
            font-size: 6.8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #4b5563;
            margin-bottom: 1px;
        }

        .cl-destinatario .cl-entidad {
            font-size: 8.5pt;
            font-weight: 700;
            color: #000000;
            line-height: 1.18;
        }

        .cl-destinatario .cl-ministerio {
            font-size: 7.9pt;
            color: #374151;
            font-weight: 600;
            line-height: 1.18;
            margin-top: 1px;
        }

        .cl-destinatario .cl-atn {
            font-size: 7.5pt;
            color: #4b5563;
            margin-top: 1px;
        }

        /* Asunto formal sin badge en caja */
        .cl-asunto {
            border-bottom: 0.8px solid var(--pdf-borde-interno);
            padding-bottom: 3px;
            margin-bottom: 5px;
            font-size: 8.4pt;
            color: #000000;
        }

        .cl-asunto-label {
            font-weight: 700;
            text-transform: uppercase;
            margin-right: 4px;
        }

        .cl-parrafo {
            font-size: 7.8pt;
            line-height: 1.25;
            text-align: justify;
            color: #111827;
            margin: 0 0 3.5px 0;
        }

        .cl-fuerte {
            font-weight: 700;
            color: #000000;
        }

        /* Tabla técnica del vehículo */
        .cl-tabla {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid var(--pdf-borde);
            margin: 4px 0;
            font-size: 7.7pt;
            background-color: #ffffff;
        }

        .cl-tabla th,
        .cl-tabla td {
            border: none;
            border-bottom: 0.8px solid var(--pdf-borde-interno);
            padding: 1.6px 6px;
            color: #111827;
            text-align: left;
            vertical-align: middle;
        }

        .cl-tabla th {
            width: 32%;
            border-right: 0.8px solid var(--pdf-borde-interno);
            background-color: var(--pdf-encabezado);
            color: #000000;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7pt;
        }

        .cl-tabla td {
            width: 68%;
        }

        .cl-tabla .cl-fila-par td {
            background-color: var(--pdf-tenue);
        }

        .cl-tabla .cl-fila-ultima th,
        .cl-tabla .cl-fila-ultima td {
            border-bottom: none;
        }

        .cl-estado-texto {
            font-size: 7.4pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #000000;
        }

        /* Cierre y firmas */
        .cl-cierre {
            margin-top: 5px;
            page-break-inside: avoid;
        }

        .cl-constancia {
            font-size: 7.6pt;
            color: #374151;
            font-style: italic;
            margin-bottom: 4px;
            text-align: left;
            line-height: 1.25;
        }

        .cl-firmas {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .cl-firma-celda {
            border: none;
            padding: 0;
            vertical-align: bottom;
        }

        .cl-espacio-firma {
            height: 24px;
        }

        .cl-espacio-firma img {
            max-height: 26px;
            max-width: 140px;
            vertical-align: bottom;
        }

        .cl-firma-escrita {
            font-family: 'Inter', DejaVu Sans, sans-serif;
            font-size: 11pt;
            font-style: italic;
            font-weight: 600;
            line-height: 28px;
            color: #1f2937;
        }

        .cl-renglon-firma {
            border-top: 1px solid var(--pdf-borde);
            padding-top: 3px;
            margin-top: 2px;
        }

        .cl-firmante {
            font-size: 8.2pt;
            font-weight: 700;
            color: #000000;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .cl-cargo {
            font-size: 8pt;
            color: #374151;
            font-weight: 600;
            line-height: 1.2;
            margin-top: 1px;
        }

        .cl-doc {
            font-size: 7.4pt;
            color: #4b5563;
            line-height: 1.2;
            margin-top: 1px;
        }

        .cl-empresa-firma {
            font-size: 7pt;
            color: #111827;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.2;
            margin-top: 1px;
        }

        /* Sello de verificación neutro */
        .cl-sello {
            width: 100%;
            border-collapse: collapse;
            border: 0.8px solid var(--pdf-borde-interno);
            background-color: var(--pdf-tenue);
        }

        .cl-sello td {
            border: none;
            padding: 4.5px 7px;
            vertical-align: middle;
            text-align: left;
        }

        .cl-sello .cl-sello-titulo {
            color: #000000;
            font-weight: 700;
            font-size: 7pt;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .cl-sello .cl-sello-texto {
            font-size: 6.5pt;
            line-height: 1.25;
            color: #374151;
            margin-top: 1px;
        }

        .cl-sello .cl-sello-mono {
            color: #111827;
            font-weight: 600;
            font-size: 6.6pt;
            margin-top: 2px;
        }

        /* Pie en flujo */
        .cl-pie {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-top: 0.8px solid var(--pdf-borde-interno);
            padding-top: 2px;
            margin-top: 3px;
            font-size: 7pt;
            color: #4b5563;
        }

        .cl-pie td {
            border: none;
            padding: 2px 0 0 0;
        }
    </style>
</head>

<body>
    <div class="cl-carta">
        @include('pdf.radicacion.partials.header', ['sin_encabezado' => true])

        @php
            $dependencia = $destinatario['dependencia'] ?? null;
            $entidad = $destinatario['entidad'] ?? 'Ministerio de Transporte';
            $ciudadFirma = $expediente['ciudad'] ?? null ?: $empresa['ciudad'] ?? '';

            $fechaRadicacion = $expediente['fecha_radicacion'] ?? null;
            try {
                $fechaFirma = $fechaRadicacion
                    ? \Carbon\Carbon::createFromFormat('d/m/Y', $fechaRadicacion)
                    : \Carbon\Carbon::now();
            } catch (\Throwable) {
                $fechaFirma = \Carbon\Carbon::now();
            }
            $fechaTexto = $fechaFirma->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

            $chasis = ($vehiculo['vin'] ?? '') ?: ($vehiculo['numero_chasis'] ?? '');

            $capacidad = [];
            if (! empty($vehiculo['pasajeros'])) {
                $capacidad[] = $vehiculo['pasajeros'] . ' pasajeros';
            }
            if (! empty($vehiculo['carga'])) {
                $capacidad[] = $vehiculo['carga'] . ' kg de carga';
            }
            $capacidadTexto = implode(' · ', $capacidad);

            $firma = $empresa['firma_representante'] ?? ['imagen' => null, 'texto' => null];

            $polizasTexto = [];
            if (! empty($polizas['rcc_numero'])) {
                $polizasTexto[] =
                    'RCC N.º ' .
                    $polizas['rcc_numero'] .
                    (! empty($polizas['rcc_aseguradora']) ? ' de ' . $polizas['rcc_aseguradora'] : '');
            }
            if (! empty($polizas['rce_numero'])) {
                $polizasTexto[] =
                    'RCE N.º ' .
                    $polizas['rce_numero'] .
                    (! empty($polizas['rce_aseguradora']) ? ' de ' . $polizas['rce_aseguradora'] : '');
            }
            $polizasDetalle =
                'Contractual (RCC) y Extracontractual (RCE)' .
                ($polizasTexto ? ' identificadas así: ' . implode('; ', $polizasTexto) : '');
        @endphp

        {{-- BLOQUE DESTINATARIO --}}
        <div class="cl-destinatario">
            <div class="cl-etiqueta">Destinatario:</div>
            <div class="cl-entidad">{{ $dependencia ?: $entidad }}</div>
            @if ($dependencia)
                <div class="cl-ministerio">{{ $entidad }}</div>
            @endif
            @if (! empty($destinatario['director']))
                <div class="cl-atn">Atn: {{ $destinatario['director'] }}</div>
            @endif
        </div>

        {{-- ASUNTO FORMAL --}}
        <div class="cl-asunto">
            <span class="cl-asunto-label">Asunto:</span>
            <span class="cl-fuerte">Aceptación de la vinculación al parque automotor del vehículo con placa {{ $vehiculo['placa'] ?? '' }}</span>
        </div>

        {{-- CONTENIDO LEGAL --}}
        <p class="cl-parrafo">
            Yo, <span class="cl-fuerte">{{ $empresa['representante'] ?? '' }}</span>, mayor de edad, identificado con
            cédula de ciudadanía número <span class="cl-fuerte">{{ $empresa['documento_representante'] ?? '' }}</span>
            @if (! empty($empresa['ciudad']))
                expedida en {{ $empresa['ciudad'] }},
            @endif
            en mi condición de representante legal de la empresa de transporte
            <span class="cl-fuerte">{{ $empresa['razon_social'] ?? '' }}</span>,
            identificada formalmente con NIT <span class="cl-fuerte">{{ $empresa['nit'] ?? '' }}</span>, por medio del
            presente documento me permito formalizar y manifestar la
            <span class="cl-fuerte">ACEPTACIÓN DE VINCULACIÓN</span> del automotor relacionado a continuación bajo el
            expediente de radicación
            <span class="cl-fuerte">{{ $expediente['codigo'] ?? '' }}</span> al parque automotor autorizado de la
            compañía, para la prestación exclusiva del Servicio Público de Transporte Terrestre Automotor Especial:
        </p>

        {{-- TABLA TÉCNICA DEL VEHÍCULO --}}
        @php($fila = 0)
        <table class="cl-tabla" cellpadding="0" cellspacing="0">
            <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                <th>Placa del Vehículo</th>
                <td class="cl-fuerte" style="font-size: 8.8pt; letter-spacing: 0.3px;">
                    {{ $vehiculo['placa'] ?? '' }}
                </td>
            </tr>
            <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                <th>Marca, Línea y Modelo</th>
                <td>
                    <span class="cl-fuerte">
                        {{ trim(($vehiculo['marca'] ?? '') . ' ' . ($vehiculo['linea'] ?? '')) }}
                    </span>
                    @if (! empty($vehiculo['modelo']))
                        · Modelo {{ $vehiculo['modelo'] }}
                    @endif
                </td>
            </tr>
            @if ($chasis)
                <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                    <th>Número de Chasis / VIN</th>
                    <td>{{ $chasis }}</td>
                </tr>
            @endif
            @if (! empty($vehiculo['numero_motor']))
                <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                    <th>Número de Motor</th>
                    <td>{{ $vehiculo['numero_motor'] }}</td>
                </tr>
            @endif
            @if (! empty($vehiculo['clase']) || ! empty($vehiculo['carroceria']))
                <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                    <th>Clase y Carrocería</th>
                    <td>
                        {{ $vehiculo['clase'] ?? '' }}
                        @if (! empty($vehiculo['carroceria']))
                            · {{ $vehiculo['carroceria'] }}
                        @endif
                    </td>
                </tr>
            @endif
            @if (! empty($vehiculo['tarjeta_operacion']))
                <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                    <th>Tarjeta de Operación</th>
                    <td>{{ $vehiculo['tarjeta_operacion'] }}</td>
                </tr>
            @endif
            @if ($capacidadTexto !== '')
                <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                    <th>Capacidad Autorizada</th>
                    <td>{{ $capacidadTexto }}</td>
                </tr>
            @endif
            @if (! empty($expediente['tipo_tramite']))
                <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                    <th>Tipo de Trámite / Origen</th>
                    <td>
                        {{ str_replace('_', ' ', (string) $expediente['tipo_tramite']) }}
                        @if (! empty($expediente['empresa_origen']))
                            · Proviene de: {{ $expediente['empresa_origen'] }}
                        @endif
                    </td>
                </tr>
            @endif
            @if (! empty($expediente['estado_paso']))
                <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                    <th>Estado del Trámite</th>
                    <td class="cl-estado-texto">{{ str_replace('_', ' ', (string) $expediente['estado_paso']) }}</td>
                </tr>
            @endif
            <tr class="cl-fila-ultima {{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                <th>Propietario / Afiliado</th>
                <td>
                    <span class="cl-fuerte">{{ $propietario['nombre'] ?? '' }}</span>
                    @if (! empty($propietario['documento']))
                        — {{ $propietario['documento'] }}
                    @endif
                </td>
            </tr>
        </table>

        {{-- CLÁUSULAS DE CUMPLIMIENTO NORMATIVO --}}
        <p class="cl-parrafo">
            La empresa contratante declara expresamente aceptar las condiciones técnico-mecánicas y operacionales del
            vehículo, obligándose a dar cabal observancia y cumplimiento a lo estipulado en el
            <span class="cl-fuerte">Decreto 1079 de 2015</span>, la
            <span class="cl-fuerte">Resolución 315 de 2013</span> y el Plan Estratégico de Seguridad Vial (PESV)
            adoptado por la entidad. Asimismo, asume el compromiso de velar por la permanente vigencia de la tarjeta
            de operación, la póliza de Seguro Obligatorio de Accidentes de Tránsito (SOAT), el certificado de
            Revisión Técnico-Mecánica y de emisiones contaminantes, así como las pólizas de Responsabilidad Civil
            {{ $polizasDetalle }}, requeridas por la ley.
        </p>
        <p class="cl-parrafo">
            Se deja expresa constancia de que el automotor objeto del presente acto se encuentra debidamente
            incorporado en el cupo y capacidad transportadora autorizada por el Ministerio de Transporte a la
            sociedad, destinándose única y exclusivamente a los servicios convenidos conforme al marco regulatorio
            aplicable.
        </p>

        {{-- SECCIÓN DE FIRMAS Y VERIFICACIÓN --}}
        <div class="cl-cierre">
            <div class="cl-constancia">
                Para constancia y trámite ante
                {{ $dependencia ?: 'la Dirección Territorial del Ministerio de Transporte' }}, se firma en
                {{ $ciudadFirma }} a los {{ $fechaTexto }}.
            </div>

            <table class="cl-firmas" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 58%; padding-right: 14px;" class="cl-firma-celda">
                        <div class="cl-espacio-firma">
                            @if (! empty($firma['imagen']))
                                <img src="{{ $firma['imagen'] }}" alt="Firma del representante legal">
                            @elseif (! empty($firma['texto']))
                                <span class="cl-firma-escrita">{{ $firma['texto'] }}</span>
                            @endif
                        </div>
                        <div class="cl-renglon-firma">
                            <div class="cl-firmante">{{ $empresa['representante'] ?? '' }}</div>
                            <div class="cl-cargo">Representante Legal</div>
                            @if (! empty($empresa['documento_representante']))
                                <div class="cl-doc">C.C. {{ $empresa['documento_representante'] }}</div>
                            @endif
                            <div class="cl-empresa-firma">{{ $empresa['razon_social'] ?? '' }}</div>
                        </div>
                    </td>

                    <td style="width: 42%;" class="cl-firma-celda" align="right">
                        <table class="cl-sello" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <div class="cl-sello-titulo">Verificación de Firma</div>
                                    <div class="cl-sello-texto">
                                        Firma digital amparada por la Ley 527 de 1999 y el Decreto 2364 de 2012.
                                    </div>
                                    <div class="cl-sello-mono">
                                        Expediente: {{ $expediente['codigo'] ?? '' }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        {{-- PIE DEL DOCUMENTO --}}
        <table class="cl-pie" cellpadding="0" cellspacing="0">
            <tr>
                <td align="left" style="color: #374151; font-weight: 700;">
                    {{ $empresa['razon_social'] ?? '' }}
                    @if (! empty($empresa['nit']))
                        · NIT. {{ $empresa['nit'] }}
                    @endif
                    · Expediente {{ $expediente['codigo'] ?? '' }}
                </td>
                <td align="right" style="color: #4b5563; font-size: 7pt;">
                    @if (! empty($expediente['fecha_radicacion']))
                        Radicado el {{ $expediente['fecha_radicacion'] }}
                    @endif
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
