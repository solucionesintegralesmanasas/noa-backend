@extends('pdf.layouts.base')

{{--
    Carta de solicitud de capacidad transportadora: paso CAPACIDAD_TRANSPORTADORA
    del expediente de radicación (clave CARTA_CAPACIDAD en RadicacionDocumentoService).

    Se arma sobre el layout base (pdf.layouts.base) para heredar el tamaño de hoja,
    los márgenes, la marca de agua y el sello de paginado; por eso no define `@page`
    ni `body` propios. Consume únicamente los datos reales que entrega
    RadicacionDocumentoService::datos(): $expediente, $empresa, $destinatario,
    $vehiculo, $propietario, $polizas e $imagenes.

    TIPOGRAFÍA: todos los tamaños van en `pt`, no en `px`. El servicio renderiza con
    `dpi => 120` y DomPDF convierte `px` como `px * 72 / dpi` (0.6 pt/px), mientras el
    diseño de referencia usa 0.75 pt/px (96 dpi): con `px` la letra salía ~20 % más
    pequeña. Los valores de abajo son los del diseño (px) × 0.75, en `pt`.
    Fuentes: DomPDF no tiene las del diseño (Inter, Cinzel, Space Grotesk…); se usan
    las base‑14 Helvetica (=Arial) para texto y Courier para códigos.
--}}

@section('header')
    {{-- Mismo encabezado membretado que las demás cartas de radicación. Definir la
         sección también suprime el encabezado default de tres logos del layout, que
         aquí saldría vacío porque estas vistas no reciben `$data`. --}}
    @include('pdf.radicacion.partials.header')
@endsection

@push('styles')
    /* Margen propio de carta formal: el layout trae 0.8 cm en el body, demasiado
    estrecho para una carta (y cerca del area no imprimible). Se sobrescribe
    solo para este documento; el layout compartido no se toca. Se incluye la
    variante `body.with-letterhead` para que el margen no se reduzca si algún
    día la vista recibe un membrete. */
    body,
    body.with-letterhead {
    margin: 2cm 2cm 2cm 2cm;
    }

    /* El encabezado compartido viene de pdf.radicacion.partials.header, cuyos
    estilos viven en pdf.radicacion.partials.styles (que no se puede incluir
    entero: redefine @page y body y chocaría con el layout). Se declaran aquí
    solo las reglas del encabezado, con los mismos valores que las cartas
    hermanas para que el membretado se vea idéntico. */
    .encabezado {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 1.5px solid #000;
    padding-bottom: 6px;
    margin-bottom: 14px;
    }

    .encabezado td {
    vertical-align: middle;
    border: none;
    padding: 0;
    }

    .encabezado .marca {
    font-size: 13pt;
    font-weight: bold;
    line-height: 1.1;
    }

    .encabezado .submarca {
    font-size: 8.5pt;
    color: #333;
    }

    .encabezado .logo {
    max-height: 52px;
    max-width: 170px;
    }

    /* Cuerpo de la carta (diseño: 11.5px -> 8.5pt). */
    .cl-carta {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 8.5pt;
    line-height: 1.35;
    color: #1e293b;
    text-align: left;
    }

    /* El layout fuerza strong a 11px; dentro de la carta hereda el tamaño del texto. */
    .cl-carta strong {
    font-size: inherit;
    }

    /* Bloque destinatario. */
    .cl-destinatario {
    background-color: #f8fafc;
    border-top: 0.75pt solid #e2e8f0;
    border-right: 0.75pt solid #e2e8f0;
    border-bottom: 0.75pt solid #e2e8f0;
    border-left: 3pt solid #1d4ed8;
    padding: 9pt 10.5pt;
    margin-bottom: 10pt;
    }

    .cl-destinatario .cl-etiqueta {
    font-size: 7.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.4pt;
    color: #64748b;
    margin-bottom: 2pt;
    }

    .cl-destinatario .cl-entidad {
    font-size: 9pt;
    font-weight: bold;
    color: #0f172a;
    line-height: 1.2;
    }

    .cl-destinatario .cl-ministerio {
    font-size: 8.5pt;
    color: #334155;
    font-weight: bold;
    line-height: 1.2;
    margin-top: 1.5pt;
    }

    .cl-destinatario .cl-atn {
    font-size: 8pt;
    color: #64748b;
    margin-top: 2pt;
    }

    /* Asunto formal en una sola línea. Se evita la tabla: DomPDF reparte las
    columnas del <td> 50/50 (ignora el ancho en pt) y el texto se partía en
        dos líneas a mitad de hoja. Con flujo inline (badge + texto) queda en una
        línea mientras quepa en el ancho útil. */
        .cl-asunto {
        margin-bottom: 9pt;
        }

        .cl-badge {
        background-color: #e2e8f0;
        color: #0f172a;
        font-size: 8pt;
        font-weight: bold;
        padding: 2pt 6pt;
        text-transform: uppercase;
        letter-spacing: 0.4pt;
        }

        .cl-asunto .cl-texto {
        margin-left: 6pt;
        font-size: 9pt;
        font-weight: bold;
        color: #1e293b;
        }

        /* Texto legal. */
        .cl-parrafo {
        font-size: 8.5pt;
        line-height: 1.35;
        text-align: justify;
        color: #1e293b;
        margin: 0 0 6pt 0;
        }

        .cl-fuerte {
        font-weight: bold;
        color: #091322;
        }

        .cl-resalta {
        font-family: "Courier New", Courier, monospace;
        font-weight: bold;
        color: #1e40af;
        }

        /* Tabla de especificaciones del vehículo (borde exterior + separadores de fila). */
        .cl-tabla {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border: 0.75pt solid #cbd5e1;
        margin: 7pt 0;
        font-size: 8.5pt;
        }

        .cl-tabla th,
        .cl-tabla td {
        border: none;
        border-bottom: 0.75pt solid #e2e8f0;
        padding: 4pt 7.5pt;
        color: #0f172a;
        background-color: transparent;
        text-align: left;
        vertical-align: middle;
        }

        .cl-tabla th {
        width: 32%;
        border-right: 0.75pt solid #e2e8f0;
        color: #334155;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 7pt;
        }

        .cl-tabla td {
        width: 68%;
        }

        /* Banda alterna (zebra) y supresión del separador en la última fila.
        DomPDF no pinta el fondo del <tr>: hay que aplicarlo a sus celdas. */
            .cl-tabla .cl-fila-par th,
            .cl-tabla .cl-fila-par td {
            background-color: #f8fafc;
            }

            .cl-tabla .cl-fila-ultima th,
            .cl-tabla .cl-fila-ultima td {
            border-bottom: none;
            }

            .cl-mono {
            font-family: "Courier New", Courier, monospace;
            font-size: 8.5pt;
            }

            .cl-estado {
            background-color: #d1fae5;
            color: #065f46;
            font-size: 7pt;
            font-weight: bold;
            padding: 2pt 5pt;
            text-transform: uppercase;
            }

            /* Cierre y firmas. */
            .cl-cierre {
            margin-top: 8pt;
            }

            .cl-constancia {
            font-size: 8pt;
            color: #334155;
            font-style: italic;
            margin-bottom: 8pt;
            text-align: left;
            line-height: 1.35;
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
            height: 26pt;
            }

            .cl-espacio-firma img {
            max-height: 25pt;
            max-width: 130pt;
            vertical-align: bottom;
            }

            .cl-firma-escrita {
            font-family: "Segoe Script", "Brush Script MT", cursive;
            font-size: 12pt;
            font-style: italic;
            line-height: 26pt;
            }

            .cl-renglon-firma {
            border-top: 1pt solid #0f172a;
            padding-top: 4pt;
            margin-top: 3pt;
            }

            .cl-firmante {
            font-size: 8.5pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.2;
            }

            .cl-cargo {
            font-size: 8.5pt;
            color: #334155;
            font-weight: bold;
            line-height: 1.2;
            margin-top: 1pt;
            }

            .cl-doc {
            font-family: "Courier New", Courier, monospace;
            font-size: 7.5pt;
            color: #64748b;
            line-height: 1.2;
            margin-top: 1pt;
            }

            .cl-empresa-firma {
            font-size: 7pt;
            color: #1e40af;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            margin-top: 1pt;
            }

            /* Sello de verificación de firma. */
            .cl-sello {
            width: 100%;
            border-collapse: collapse;
            border: 0.75pt solid #cbd5e1;
            background-color: #f8fafc;
            }

            .cl-sello td {
            border: none;
            padding: 4.5pt 6pt;
            vertical-align: middle;
            text-align: left;
            }

            .cl-sello .cl-sello-titulo {
            color: #0f172a;
            font-weight: bold;
            font-size: 7pt;
            line-height: 1.2;
            }

            .cl-sello .cl-sello-texto {
            font-size: 6.5pt;
            line-height: 1.25;
            color: #475569;
            margin-top: 1pt;
            }

            .cl-sello .cl-sello-mono {
            font-family: "Courier New", Courier, monospace;
            color: #64748b;
            font-size: 6.5pt;
            margin-top: 1.5pt;
            }

            /* Pie en flujo (el número de página real lo estampa PdfService::sellarPaginado). */
            .cl-pie {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-top: 0.75pt solid #e2e8f0;
            padding-top: 6pt;
            margin-top: 4.5pt;
            font-size: 7.5pt;
            color: #64748b;
            }

            .cl-pie td {
            border: none;
            padding: 0;
            }
        @endpush

        @section('content')
            @php
                $dependencia = $destinatario['dependencia'] ?? null;
                $entidad = $destinatario['entidad'] ?? 'Ministerio de Transporte';
                $ciudadFirma = $expediente['ciudad'] ?? null ?: $empresa['ciudad'] ?? '';

                // Fecha en texto a partir de la fecha de radicación (llega como d/m/Y).
                $fechaRadicacion = $expediente['fecha_radicacion'] ?? null;
                try {
                    $fechaFirma = $fechaRadicacion
                        ? \Carbon\Carbon::createFromFormat('d/m/Y', $fechaRadicacion)
                        : \Carbon\Carbon::now();
                } catch (\Throwable) {
                    $fechaFirma = \Carbon\Carbon::now();
                }
                $fechaTexto = $fechaFirma->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

                // Chasis: se prefiere el VIN y se cae al número de chasis.
                $chasis = $vehiculo['vin'] ?? '' ?: $vehiculo['numero_chasis'] ?? '';

                // Capacidad autorizada según el tipo de vehículo.
                $capacidad = [];
                if (!empty($vehiculo['pasajeros'])) {
                    $capacidad[] = $vehiculo['pasajeros'] . ' pasajeros';
                }
                if (!empty($vehiculo['carga'])) {
                    $capacidad[] = $vehiculo['carga'] . ' kg de carga';
                }
                $capacidadTexto = implode(' &middot; ', $capacidad);

                $firma = $empresa['firma_representante'] ?? ['imagen' => null, 'texto' => null];

                // Frase de pólizas: si el expediente trae RCC/RCE se citan sus números;
                // si no, se deja la mención genérica de la cláusula.
                $polizasTexto = [];
                if (!empty($polizas['rcc_numero'])) {
                    $polizasTexto[] =
                        'RCC N.º ' .
                        $polizas['rcc_numero'] .
                        (!empty($polizas['rcc_aseguradora']) ? ' de ' . $polizas['rcc_aseguradora'] : '');
                }
                if (!empty($polizas['rce_numero'])) {
                    $polizasTexto[] =
                        'RCE N.º ' .
                        $polizas['rce_numero'] .
                        (!empty($polizas['rce_aseguradora']) ? ' de ' . $polizas['rce_aseguradora'] : '');
                }
                $polizasDetalle =
                    'Contractual (RCC) y Extracontractual (RCE)' .
                    ($polizasTexto ? ' identificadas así: ' . implode('; ', $polizasTexto) : '');
            @endphp

            <div class="cl-carta">

                {{-- BLOQUE DESTINATARIO --}}
                <div class="cl-destinatario">
                    <div class="cl-etiqueta">Destinatario:</div>
                    <div class="cl-entidad">{{ $dependencia ?: $entidad }}</div>
                    @if ($dependencia)
                        <div class="cl-ministerio">{{ $entidad }}</div>
                    @endif
                    @if (!empty($destinatario['director']))
                        <div class="cl-atn">Atn: {{ $destinatario['director'] }}</div>
                    @endif
                </div>

                {{-- ASUNTO FORMAL (una sola línea) --}}
                <div class="cl-asunto">
                    <span class="cl-badge">Asunto:</span><span class="cl-texto">Aceptación de la vinculación al parque
                        automotor del vehículo con placa {{ $vehiculo['placa'] ?? '' }}</span>
                </div>

                {{-- CONTENIDO LEGAL --}}
                <p class="cl-parrafo">
                    Yo, <span class="cl-fuerte">{{ $empresa['representante'] ?? '' }}</span>, mayor de edad, identificado
                    con
                    cédula de ciudadanía número <span
                        class="cl-fuerte">{{ $empresa['documento_representante'] ?? '' }}</span>
                    @if (!empty($empresa['ciudad']))
                        expedida en {{ $empresa['ciudad'] }},
                    @endif
                    en mi condición de representante legal de la empresa de transporte
                    <span class="cl-fuerte">{{ $empresa['razon_social'] ?? '' }}</span>,
                    identificada formalmente con NIT <span class="cl-fuerte">{{ $empresa['nit'] ?? '' }}</span>, por medio
                    del
                    presente documento me permito formalizar y manifestar la
                    <span class="cl-fuerte">ACEPTACIÓN DE VINCULACIÓN</span> del automotor relacionado a continuación bajo
                    el
                    expediente de radicación
                    <span class="cl-resalta">{{ $expediente['codigo'] ?? '' }}</span> al parque automotor autorizado de la
                    compañía, para la prestación exclusiva del Servicio Público de Transporte Terrestre Automotor Especial:
                </p>

                {{-- TABLA TÉCNICA DEL VEHÍCULO --}}
                @php($fila = 0)
                <table class="cl-tabla">
                    <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                        <th>Placa del Vehículo</th>
                        <td class="cl-mono cl-fuerte" style="font-size: 9pt; letter-spacing: 0.4pt;">
                            {{ $vehiculo['placa'] ?? '' }}
                        </td>
                    </tr>
                    <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                        <th>Marca, Línea y Modelo</th>
                        <td>
                            <span class="cl-fuerte">
                                {{ trim(($vehiculo['marca'] ?? '') . ' ' . ($vehiculo['linea'] ?? '')) }}
                            </span>
                            @if (!empty($vehiculo['modelo']))
                                &middot; Modelo {{ $vehiculo['modelo'] }}
                            @endif
                        </td>
                    </tr>
                    @if ($chasis)
                        <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                            <th>Número de Chasis / VIN</th>
                            <td class="cl-mono">{{ $chasis }}</td>
                        </tr>
                    @endif
                    @if (!empty($vehiculo['numero_motor']))
                        <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                            <th>Número de Motor</th>
                            <td class="cl-mono">{{ $vehiculo['numero_motor'] }}</td>
                        </tr>
                    @endif
                    @if (!empty($vehiculo['clase']) || !empty($vehiculo['carroceria']))
                        <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                            <th>Clase y Carrocería</th>
                            <td>
                                {{ $vehiculo['clase'] ?? '' }}
                                @if (!empty($vehiculo['carroceria']))
                                    &middot; {{ $vehiculo['carroceria'] }}
                                @endif
                            </td>
                        </tr>
                    @endif
                    @if (!empty($vehiculo['tarjeta_operacion']))
                        <tr class="{{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                            <th>Tarjeta de Operación</th>
                            <td class="cl-mono">{{ $vehiculo['tarjeta_operacion'] }}</td>
                        </tr>
                    @endif
                    <tr class="cl-fila-ultima {{ ++$fila % 2 ? 'cl-fila-par' : '' }}">
                        <th>Propietario / Afiliado</th>
                        <td>
                            <span class="cl-fuerte">{{ $propietario['nombre'] ?? '' }}</span>
                            @if (!empty($propietario['documento']))
                                — {{ $propietario['documento'] }}
                            @endif
                        </td>
                    </tr>
                </table>

                {{-- CLÁUSULAS DE CUMPLIMIENTO NORMATIVO --}}
                <p class="cl-parrafo">
                    La empresa contratante declara expresamente aceptar las condiciones técnico-mecánicas y operacionales
                    del
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

                    <table class="cl-firmas">
                        <tr>
                            {{-- Firma del representante legal --}}
                            <td width="58%" class="cl-firma-celda" style="padding-right: 15pt;">
                                <div class="cl-espacio-firma">
                                    @if (!empty($firma['imagen']))
                                        <img src="{{ $firma['imagen'] }}" alt="Firma del representante legal">
                                    @elseif (!empty($firma['texto']))
                                        <span class="cl-firma-escrita">{{ $firma['texto'] }}</span>
                                    @endif
                                </div>
                                <div class="cl-renglon-firma">
                                    <div class="cl-firmante">{{ $empresa['representante'] ?? '' }}</div>
                                    <div class="cl-cargo">Representante Legal</div>
                                    @if (!empty($empresa['documento_representante']))
                                        <div class="cl-doc">C.C. {{ $empresa['documento_representante'] }}</div>
                                    @endif
                                    <div class="cl-empresa-firma">{{ $empresa['razon_social'] ?? '' }}</div>
                                </div>
                            </td>

                            {{-- Sello de verificación --}}
                            <td width="42%" class="cl-firma-celda" align="right">
                                <table class="cl-sello">
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
                <table class="cl-pie">
                    <tr>
                        <td align="left" style="color: #475569; font-weight: bold;">
                            {{ $empresa['razon_social'] ?? '' }}
                            @if (!empty($empresa['nit']))
                                &middot; NIT. {{ $empresa['nit'] }}
                            @endif
                            &middot; Expediente {{ $expediente['codigo'] ?? '' }}
                        </td>
                        <td align="right" class="cl-mono" style="color: #94a3b8; font-size: 7pt;">
                            @if (!empty($expediente['fecha_radicacion']))
                                Radicado el {{ $expediente['fecha_radicacion'] }}
                            @endif
                        </td>
                    </tr>
                </table>

            </div>
        @endsection
