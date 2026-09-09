<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Acta de Entrega de Vehículos</title>
    <style>
        @page {
            margin: 4mm;
            size: letter;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
            page-break-inside: auto;
        }
        tr {
            page-break-inside: auto;
            page-break-after: auto;
        }
        thead {
            display: table-header-group;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 2px 4px;
            text-align: left;
            vertical-align: top;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 3px;
        }
        .subheader {
            background-color: #ddd;
            font-weight: bold;
            text-align: center;
        }
        .section-title {
            background-color: #eee;
            font-weight: bold;
        }
        .check {
            text-align: center;
            width: 10px;
        }
        .box {
            display: inline-block;
            width: 7px;
            height: 7px;
            border: 1px solid #000;
        }
        .obs {
            width: 40px;
            font-size: 7px;
        }
        .fila-item td {
            padding-top: 2px;
            padding-bottom: 2px;
        }
        .firma {
            margin-top: 8px;
            border-top: 1px solid #000;
            text-align: center;
            font-size: 7px;
            padding-top: 2px;
        }
        .footer-info {
            font-size: 6px;
            text-align: right;
            margin-top: 3px;
        }
        .company-header {
            text-align: center;
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 1px;
        }
        .logo-header {
            max-height: 20px;
            max-width: 90px;
            vertical-align: middle;
        }
        .header-container {
            width: 100%;
            border: 1px solid #000;
            margin-bottom: 3px;
        }
        .header-container td {
            border: none;
            vertical-align: middle;
        }
        .logo-cell {
            width: 20%;
            text-align: center;
            border-right: 1px solid #000 !important;
        }
        .logo-placeholder {
            font-size: 8px;
            font-weight: bold;
        }
        .title-cell {
            width: 55%;
            text-align: center;
            border-right: 1px solid #000 !important;
        }
        .info-cell {
            width: 25%;
            font-size: 7px;
        }
        .info-cell div {
            padding: 0 2px;
        }
    </style>
</head>
<body>

    {{-- ============ ENCABEZADO CON LOGO Y CÓDIGO ============ --}}
    <table class="header-container">
        <tr>
            <td class="logo-cell">
                @if (!empty($images['logo']))
                    <img class="logo-header" src="data:image/png;base64,{{ $images['logo'] }}" alt="Logo">
                @else
                    <div class="logo-placeholder">LOGO<br>EMPRESA</div>
                @endif
            </td>
            <td class="title-cell">
                <div style="font-size: 10px; font-weight: bold;">{{ $company['business_name'] ?? 'NOMBRE DE LA EMPRESA' }}</div>
                <div style="font-size: 8px;">Sistema de Gestión de Calidad</div>
            </td>
            <td class="info-cell">
                <div><strong>Fecha:</strong> {{ $generation_date ?? date('d/m/Y') }}</div>
                <div><strong>Versión:</strong> 001</div>
                <div><strong>Código:</strong> M-F-059</div>
            </td>
        </tr>
    </table>

    <div class="header">FORMATO ACTA DE ENTREGA DE RECIBO DE VEHICULOS</div>

    {{-- ============ 1. INFORMACION DEL VEHICULO ============ --}}
    <table>
        <tr class="section-title"><td colspan="8">1. INFORMACION DEL VEHICULO</td></tr>
        <tr>
            <th>PLACA:</th><td>{{ $vehiculo->placa ?? '________________' }}</td>
            <th>MARCA:</th><td>{{ $vehiculo->marca ?? '________________' }}</td>
            <th>No. CHASIS:</th><td>{{ $vehiculo->chasis ?? '________________' }}</td>
            <th>MODELO:</th><td>{{ $vehiculo->modelo ?? '________________' }}</td>
        </tr>
        <tr>
            <th>VEHICULO:</th><td>{{ $vehiculo->vehiculo ?? '________________' }}</td>
            <th>COMBUSTIBLE:</th><td>{{ $vehiculo->combustible ?? '________________' }}</td>
            <th>LICENCIA TRANSITO:</th><td>{{ $vehiculo->licencia ?? '________________' }}</td>
            <th>CILINDRAJE:</th><td>{{ $vehiculo->cilindraje ?? '________________' }}</td>
        </tr>
        <tr>
            <th>CLASE SERVICIO:</th><td>{{ $vehiculo->clase_servicio ?? '________________' }}</td>
            <th>No. MOTOR:</th><td>{{ $vehiculo->motor ?? '________________' }}</td>
            <th>TIPO CARROCERIA:</th><td>{{ $vehiculo->carroceria ?? '________________' }}</td>
            <th>TECNO-MECANICA:</th><td>{{ $vehiculo->tecno ?? '________________' }}</td>
        </tr>
    </table>

    {{-- ============ 2. INFORMACION DEL PROPIETARIO ============ --}}
    <table>
        <tr class="section-title"><td colspan="4">2. INFORMACION DEL PROPIETARIO</td></tr>
        <tr>
            <th>NOMBRE DEL PROPIETARIO:</th>
            <td colspan="3">{{ $propietario->nombre ?? '________________' }}</td>
        </tr>
        <tr>
            <th>NUMERO DE CEDULA:</th>
            <td>{{ $propietario->cedula ?? '________________' }}</td>
            <th>LUGAR:</th>
            <td>{{ $propietario->lugar ?? '________________' }}</td>
        </tr>
    </table>

    {{-- ============ 3. INFORMACION DE LA ENTREGA ============ --}}
    <table>
        <tr class="section-title"><td colspan="4">3. INFORMACION DE LA ENTREGA</td></tr>
        <tr>
            <th>FECHA:</th><td>{{ $entrega->fecha ?? '____/____/______' }}</td>
            <th>CARGO:</th><td>{{ $entrega->cargo ?? '________________' }}</td>
        </tr>
        <tr>
            <th>NOMBRE DE QUIEN ENTREGA:</th>
            <td colspan="3">{{ $entrega->nombre_entrega ?? '________________' }}</td>
        </tr>
        <tr>
            <th>DELEGADO DE LA EMPRESA:</th>
            <td colspan="3">{{ $entrega->delegado ?? '________________' }}</td>
        </tr>
    </table>

    {{-- ============ 4 a 7. CHECKLISTS EN DOS COLUMNAS ============ --}}
    <table>
        <tr>
            <td style="width: 50%; border: none; vertical-align: top;">

                {{-- 4. ESTADO GENERAL DEL VEHICULO --}}
                <table>
                    <tr class="section-title"><td colspan="5">4. ESTADO GENERAL DEL VEHICULO</td></tr>
                    <tr class="subheader">
                        <th>ELEMENTO</th>
                        <th class="check">BUENO</th>
                        <th class="check">MALO</th>
                        <th class="check">N/A</th>
                        <th class="obs">OBS.</th>
                    </tr>
                    @php
                        $items4 = [
                            'ESTADO GENERAL DE LA CARROCERIA',
                            'VIDRIOS PARABRISAS',
                            'LIMPIA PARABRISAS',
                            'VIDRIOS LATERALES',
                            'ESPEJOS COMPLET',
                            'RETROVISOR LATERALES',
                            'STOP\'S',
                            'TERCER STOP\'S',
                            'LUCES DELANTERAS BAJAS',
                            'LUCES DELANTERAS ALTAS',
                            'LUCES DELANTERAS SEÑALES DIRECCIONALES',
                            'LUCES TRASERAS',
                            'LUCES TRASERAS SEÑALES DIRECCIONALES',
                            'LUCES DE REVERSA',
                            'LUCES DE ESTACIONAMIENTO',
                            'PITO',
                            'PITO DE REVERSA',
                            'CARPA DE LONA',
                            'ESTADO GENERAL DE LAS LLANTAS',
                            'ESTADO DE ELEMENTOS DE SUSPENSION',
                            'SALIDA DE EMERGENCIA (BUSETA)',
                            'ESTADO DE FRENO',
                            'ESTADO DE FRENO DE SEGURIDAD',
                            'NIVEL Y ESTADO ACEITE DE MOTOR',
                            'NIVEL Y ESTADO DE LIQUIDO REFRIGERANTE',
                            'NIVEL Y ESTADO LIQUIDO DE FRENOS',
                            'NIVEL Y ESTADO DE FLUIDO HIDR DE DIRECCION',
                            'NIVEL LIQUIDO DE BATERIA',
                            'NIVEL LIQUIDO LIMPIABRISAS',
                            'ESTADO DE CORREAS',
                            'ESTADO DE MANGUERAS',
                            'CABLES DE BATERIA',
                            'ANTENA DE RADIO'
                        ];
                    @endphp
                    @foreach($items4 as $item)
                    <tr class="fila-item">
                        <td>{{ $item }}</td>
                        <td class="check"><span class="box"></span></td>
                        <td class="check"><span class="box"></span></td>
                        <td class="check"><span class="box"></span></td>
                        <td class="obs">&nbsp;</td>
                    </tr>
                    @endforeach
                </table>

                {{-- 6. EQUIPO / CONTROL (debajo del 4) --}}
                <table>
                    <tr class="section-title"><td colspan="5">6. EQUIPO / CONTROL</td></tr>
                    <tr class="subheader">
                        <th>ELEMENTO</th>
                        <th class="check">BUENO</th>
                        <th class="check">MALO</th>
                        <th class="check">N/A</th>
                        <th class="obs">OBS.</th>
                    </tr>
                    @php
                        $items6 = [
                            'GANCHO DE REMOLQUE',
                            'BARRA ANTI-VUELCO',
                            'PALA ANTICHISPA',
                            'TACOS',
                            '10 MTS DE MANILA',
                            'CRUCETA',
                            'GATO',
                            'LINTERNA CON PILAS',
                            'KIT DE HERRAMIENTAS',
                            'CHALECO REFLECTIVO',
                            'CONOS Y/O TRIANGULOS REFLECTIVOS',
                            'EXTINTOR'
                        ];
                    @endphp
                    @foreach($items6 as $item)
                    <tr class="fila-item">
                        <td>{{ $item }}</td>
                        <td class="check"><span class="box"></span></td>
                        <td class="check"><span class="box"></span></td>
                        <td class="check"><span class="box"></span></td>
                        <td class="obs">&nbsp;</td>
                    </tr>
                    @endforeach
                </table>

            </td>
            <td style="width: 50%; border: none; vertical-align: top;">

                {{-- 5. INSPECCION DE CABINA --}}
                <table>
                    <tr class="section-title"><td colspan="5">5. INSPECCION DE CABINA</td></tr>
                    <tr class="subheader">
                        <th>ELEMENTO</th>
                        <th class="check">BUENO</th>
                        <th class="check">MALO</th>
                        <th class="check">N/A</th>
                        <th class="obs">OBS.</th>
                    </tr>
                    @php
                        $items5 = [
                            'CINTURONES DE SEGURIDAD',
                            'LUZ INTERNA',
                            'APOYA CABEZAS',
                            'INEXISTENCIA DE OBJETOS SUELTOS',
                            'PARASOLES',
                            'TABLERO DE INSTRUMENTOS',
                            'CHAPAS Y MANIJAS',
                            'ESTADO DE LAS PUERTAS',
                            'AIRBAG',
                            'ASIENTOS DELANTEROS Y TRASEROS',
                            'TAPETES',
                            'TIMON',
                            'PANELES DE CONTROL',
                            'PALANCA DE CAMBIOS',
                            'RADIO'
                        ];
                    @endphp
                    @foreach($items5 as $item)
                    <tr class="fila-item">
                        <td>{{ $item }}</td>
                        <td class="check"><span class="box"></span></td>
                        <td class="check"><span class="box"></span></td>
                        <td class="check"><span class="box"></span></td>
                        <td class="obs">&nbsp;</td>
                    </tr>
                    @endforeach
                </table>

                {{-- 7. EQUIPO / ELEMENTO DEL BOTIQUIN (debajo del 5) --}}
                <table>
                    <tr class="section-title"><td colspan="5">7. EQUIPO / ELEMENTO DEL BOTIQUIN</td></tr>
                    <tr class="subheader">
                        <th>ELEMENTO</th>
                        <th class="check">BUENO</th>
                        <th class="check">MALO</th>
                        <th class="check">N/A</th>
                        <th class="obs">OBS.</th>
                    </tr>
                    @php
                        $items7 = [
                            'VENDA ELASTICA',
                            'TIJERAS',
                            'MANTA TERMICA (1 UNIDAD)',
                            'LACTATO DE RINGER (MINIMO 1 BOLSA)',
                            'INMOVILIZADORES PARA CUELLO Y EXTREMIDADES',
                            'GUANTES DESECHABLES (MINIMO 5 PARES)',
                            'GASA (MINIMO 5 PAQUETES)',
                            'ESPARADRAPO (MINIMO 1 ROLLO)',
                            'MICROPORE (MINIMO 1 ROLLO)',
                            'ISODINE SOLUCION Y ESPUMA',
                            'APOSITO OCULAR (MINIMO 5 UNIDADES)',
                            'BAJALENGUAS (MINIMO 4 UNIDADES)',
                            'AGUA DESTILADA (MINIMO 2 BOLSAS)'
                        ];
                    @endphp
                    @foreach($items7 as $item)
                    <tr class="fila-item">
                        <td>{{ $item }}</td>
                        <td class="check"><span class="box"></span></td>
                        <td class="check"><span class="box"></span></td>
                        <td class="check"><span class="box"></span></td>
                        <td class="obs">&nbsp;</td>
                    </tr>
                    @endforeach
                </table>

                {{-- 8. OBSERVACIONES GENERALES (debajo del 7) --}}
                <table>
                    <tr class="section-title"><td>8. OBSERVACIONES GENERALES (NOVEDAD DE PINTURA, GOLPES, RAYONES)</td></tr>
                    <tr><td style="height: 145px;">&nbsp;</td></tr>
                </table>

                {{-- FIRMAS (debajo del 8) --}}
                <table style="margin-top: 70px; border: none;">
                    <tr style="border: none;">
                        <td style="width: 50%; border: none; text-align: center;">
                            <div class="firma">FIRMA DE QUIEN RECIBE</div>
                        </td>
                        <td style="width: 50%; border: none; text-align: center;">
                            <div class="firma">FIRMA DE QUIEN ENTREGA</div>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

    <div class="footer-info">
        Fecha: {{ $generation_date ?? date('d/m/Y') }} &nbsp; Versión: 001 &nbsp; Código: M-F-059 &nbsp; Página 1 de 1
    </div>

</body>
</html>
