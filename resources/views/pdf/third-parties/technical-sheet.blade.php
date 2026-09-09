<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Ficha Técnica del Conductor</title>
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
            margin-top: 1cm;
            margin-bottom: 1cm;
            margin-left: 1cm;
            margin-right: 1cm;
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

        .vertical-legend {
            position: absolute;
            top: 50%;
            right: -180px;
            width: 400px;
            transform: rotate(-90deg);
            font-size: 8px;
            color: #777;
            text-align: center;
        }

        @page {
            margin: 1cm;
            size: letter portrait;
        }

        .table-dashed {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .table-dashed td,
        .table-dashed th {
            border: 1px dashed #000;
            padding: 4px;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .uppercase {
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    @php
        $company = $driver->company;
        $hasLogo = isset($images) && isset($images['logo']) && !empty($images['logo']);
    @endphp

    @if (!empty($hasLogo))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $images['logo'] }}" />
        </div>
    @endif

    <div class="vertical-legend">
        Generado por NOA Transportes | Fecha: {{ date('d/m/Y H:i') }} | Página 1 de 1
    </div>
    <!-- HEADER BLOCK -->
    <table style="width: 100%; margin-bottom: 15px; border-collapse: collapse;">
        <tr>
            <td style="width: 25%; text-align: center; vertical-align: middle;">
                @if ($hasLogo)
                    <img src="data:image/png;base64, {{ $images['logo'] }}"
                        style="max-height: 60px; max-width: 100%; object-fit: contain;">
                @else
                    <div style="font-weight: bold; font-size: 14px;">
                        {{ $company ? substr($company->trade_name ?? $company->business_name, 0, 2) : 'JG' }}</div>
                    <div style="font-size: 8px;">
                        {{ $company ? $company->trade_name ?? $company->business_name : 'Transportes Especiales' }}
                    </div>
                @endif
            </td>
            <td style="width: 75%; padding-left: 10px; vertical-align: top;">
                <div style="border: 1px dashed #000; text-align: center; font-weight: bold; padding: 4px; margin-bottom: 4px;"
                    class="uppercase">
                    {{ $company->business_name ?? 'TRANSPORTES ESPECIALES' }}
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="border: 1px dashed #000; text-align: center; width: 65%; padding: 4px;">
                            <div style="font-weight: bold; font-size: 10px;">FICHA TECNICA DEL CONDUCTOR</div>
                            <div style="font-weight: bold; font-size: 9px; margin-top: 4px;">RESPONSABLE JEFE
                                OPERATIVO
                            </div>
                        </td>
                        <td style="width: 2%;"></td>
                        <td style="width: 33%; vertical-align: top;">
                            <table class="table-dashed" style="margin-bottom: 0;">
                                <tr>
                                    <td class="font-bold">Código</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="font-bold">Versión</td>
                                    <td class="text-center">01</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- PERSONAL DATA -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
        <tr>
            <td style="width: 75%; vertical-align: top; padding-right: 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td class="font-bold" style="padding: 4px 0; border-bottom: 1px dashed #000; width: 35%;">
                            CEDULA
                            DE CIUDADANIA:</td>
                        <td style="padding: 4px 0; border-bottom: 1px dashed #000;" class="uppercase">
                            {{ $driver->document_number }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="padding: 4px 0; border-bottom: 1px dashed #000;">NOMBRES Y
                            APELLIDOS:</td>
                        <td style="padding: 4px 0; border-bottom: 1px dashed #000;" class="uppercase">
                            {{ $driver->first_name }} {{ $driver->last_name }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="padding: 4px 0; border-bottom: 1px dashed #000;">FECHA Y LUGAR
                            DE
                            NACIMIENTO:</td>
                        <td style="padding: 4px 0; border-bottom: 1px dashed #000;" class="uppercase">
                            {{ optional(optional($driver->municipality)->department)->name ? optional($driver->municipality)->name . ', ' . $driver->municipality->department->name : 'N/A' }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 4px 0; border-bottom: 1px dashed #000;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td class="font-bold" style="width: 15%;">SEXO:</td>
                                    <td style="width: 35%;">MASCULINO</td>
                                    <td class="font-bold" style="width: 20%;">ESTADO CIVIL:</td>
                                    <td style="width: 30%;">N/A</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 4px 0; border-bottom: 1px dashed #000;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td class="font-bold" style="width: 15%;">TELÉFONOS:</td>
                                    <td style="width: 35%;">{{ $driver->phone }}</td>
                                    <td class="font-bold" style="width: 15%;">CARGO:</td>
                                    <td style="width: 35%;">CONDUCTOR</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="padding: 4px 0; border-bottom: 1px dashed #000;">CORREO
                            ELECTRONICO:</td>
                        <td style="padding: 4px 0; border-bottom: 1px dashed #000;" class="uppercase">
                            @if ($driver->email)
                                {{ $driver->email }}
                            @else
                                NO TIENE
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 25%; text-align: center; vertical-align: top;">
                <div style="border: 1px dashed #000; height: 135px; width: 100%; display: table;">
                    <div style="display: table-cell; vertical-align: middle; color: #000;">
                        @if (isset($images) && isset($images['photo']) && !empty($images['photo']))
                            <img src="data:image/png;base64,{{ $images['photo'] }}" style="max-height: 130px; max-width: 100%; object-fit: cover;" />
                        @else
                            FOTO
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- COMPANY AFFILIATION -->
    <div class="text-center font-bold" style="margin-bottom: 4px;">DATOS DE LA EMPRESA AFILIADA</div>
    <table class="table-dashed text-center">
        <tr>
            <td class="font-bold">VINCULADO A</td>
            <td class="font-bold">NIT</td>
            <td class="font-bold">SERVICIO</td>
        </tr>
        <tr>
            <td class="uppercase">{{ $company->business_name ?? 'N/A' }}</td>
            <td>{{ $company ? $company->document_number . ($company->verification_digit ? ' - ' . $company->verification_digit : '') : 'N/A' }}
            </td>
            <td class="uppercase">SERVICIO ESPECIAL</td>
        </tr>
    </table>

    <!-- DRIVER LICENSE -->
    <div class="text-center font-bold" style="margin-bottom: 4px; margin-top: 10px;">LICENCIA(S) DE CONDUCCIÓN</div>
    @php
        $license = $driver->driverLicenses->first();
    @endphp
    <table class="table-dashed text-center">
        <tr>
            <td class="font-bold" style="width: 20%;">NRO. LICENCIA</td>
            <td style="width: 15%;">{{ $license ? $license->number : 'N/A' }}</td>
            <td class="font-bold" style="width: 20%;">FECHA EXPEDICIÓN</td>
            <td style="width: 15%;">
                {{ $license && $license->issue_date ? \Carbon\Carbon::parse($license->issue_date)->format('d/m/Y') : 'N/A' }}
            </td>
            <td class="font-bold" style="width: 15%;">CATEGORÍA</td>
            <td style="width: 15%;">{{ $license ? $license->category : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="font-bold" colspan="2">OT EXPIDE LIC</td>
            <td colspan="2">STRIA MCPAL TTEYTTO APARTADO</td>
            <td class="font-bold">ESTADO</td>
            <td class="uppercase">{{ $license ? $license->status : 'N/A' }}</td>
        </tr>
    </table>

    <!-- CONTRACT -->
    <div class="text-center font-bold" style="margin-bottom: 4px; margin-top: 10px;">INFORMACIÓN DEL CONTRATO</div>
    <table class="table-dashed text-center">
        <tr>
            <td class="font-bold" style="width: 20%;">TIPO DE CONTRATO</td>
            <td style="width: 20%;">OBRA LABOR</td>
            <td class="font-bold" style="width: 20%;">FECHA INICIO</td>
            <td style="width: 20%;">N/A</td>
            <td class="font-bold" style="width: 20%;">No. CONTRATO</td>
        </tr>
    </table>

    <!-- SOCIAL SECURITY -->
    <div class="text-center font-bold" style="margin-bottom: 4px; margin-top: 10px;">INFORMACION DE AFILIACIONES A
        SEGURIDAD SOCIAL</div>
    @php
        $latestContribution = $driver->socialSecurityContributions->sortByDesc('created_at')->first();
        
        $epsName = $latestContribution && $latestContribution->eps_name ? $latestContribution->eps_name : ($latestContribution && $latestContribution->health_paid ? 'ACTIVA' : 'N/A');
        $riskName = $latestContribution && $latestContribution->risk_labor_name ? $latestContribution->risk_labor_name : ($latestContribution && $latestContribution->risk_labor_paid ? 'ACTIVA' : 'N/A');
        $pensionName = $latestContribution && $latestContribution->pension_name ? $latestContribution->pension_name : ($latestContribution && $latestContribution->pension_paid ? 'ACTIVA' : 'N/A');
        $compensationName = $latestContribution && $latestContribution->compensation_fund_name ? $latestContribution->compensation_fund_name : ($latestContribution && $latestContribution->compensation_fund_paid ? 'ACTIVA' : 'N/A');
    @endphp

    <table class="table-dashed text-center" style="margin-bottom: 6px;">
        <tr>
            <td colspan="6" class="font-bold">AFILIACIÓN A SALUD (EPS)</td>
        </tr>
        <tr>
            <td class="font-bold" style="width: 18%;">ADMINISTRADORA</td>
            <td style="width: 25%; text-transform: uppercase;">{{ $epsName }}</td>
            <td class="font-bold" style="width: 12%;">RÉGIMEN</td>
            <td style="width: 15%;">CONTRIBUTIVO</td>
            <td class="font-bold" style="width: 18%;">FECHA AFILIACIÓN</td>
            <td style="width: 12%;">{{ $latestContribution && $latestContribution->eps_affiliation_date ? \Carbon\Carbon::parse($latestContribution->eps_affiliation_date)->format('d/m/Y') : 'N/A' }}</td>
        </tr>
    </table>

    <table class="table-dashed text-center" style="margin-bottom: 6px;">
        <tr>
            <td colspan="4" class="font-bold">AFILIACIÓN A RIESGOS LABORALES (ARL)</td>
        </tr>
        <tr>
            <td class="font-bold" style="width: 20%;">ADMINISTRADORA</td>
            <td style="width: 40%; text-transform: uppercase;">{{ $riskName }}</td>
            <td class="font-bold" style="width: 20%;">FECHA AFILIACIÓN</td>
            <td style="width: 20%;">{{ $latestContribution && $latestContribution->risk_labor_affiliation_date ? \Carbon\Carbon::parse($latestContribution->risk_labor_affiliation_date)->format('d/m/Y') : 'N/A' }}</td>
        </tr>
    </table>

    <table class="table-dashed text-center" style="margin-bottom: 6px;">
        <tr>
            <td colspan="4" class="font-bold">AFILIACIÓN A PENSIONES</td>
        </tr>
        <tr>
            <td class="font-bold" style="width: 20%;">ADMINISTRADORA</td>
            <td style="width: 40%; text-transform: uppercase;">{{ $pensionName }}</td>
            <td class="font-bold" style="width: 20%;">FECHA AFILIACIÓN</td>
            <td style="width: 20%;">{{ $latestContribution && $latestContribution->pension_affiliation_date ? \Carbon\Carbon::parse($latestContribution->pension_affiliation_date)->format('d/m/Y') : 'N/A' }}</td>
        </tr>
    </table>

    <table class="table-dashed text-center">
        <tr>
            <td colspan="4" class="font-bold">CAJA DE COMPENSACIÓN FAMILIAR</td>
        </tr>
        <tr>
            <td class="font-bold" style="width: 20%;">ADMINISTRADORA</td>
            <td style="width: 40%; text-transform: uppercase;">{{ $compensationName }}</td>
            <td class="font-bold" style="width: 20%;">FECHA AFILIACIÓN</td>
            <td style="width: 20%;">{{ $latestContribution && $latestContribution->compensation_fund_affiliation_date ? \Carbon\Carbon::parse($latestContribution->compensation_fund_affiliation_date)->format('d/m/Y') : 'N/A' }}</td>
        </tr>
    </table>

    <!-- VEHICLES -->
    {{-- <table class="table-dashed text-center" style="margin-top: 10px;">
        <tr>
            <td colspan="6" class="font-bold">VEHÍCULOS ASIGNADOS</td>
        </tr>
        <tr>
            <td class="font-bold" style="vertical-align: middle;">PLACA</td>
            <td style="vertical-align: middle;">
                @if (isset($driver->vehicles) && $driver->vehicles->count() > 0)
                    @foreach ($driver->vehicles as $v)
                        <div>{{ $v->vehicle_license_plate }}</div>
                    @endforeach
                @else
                    <div style="padding: 20px 0;">N/A</div>
                @endif
            </td>
            <td style="vertical-align: middle;">No MOVIL</td>
            <td style="vertical-align: middle;">{{ $driver->phone ?? 'N/A' }}</td>
            <td style="vertical-align: middle;">FECHA DE ENTREGA</td>
            <td style="vertical-align: middle;">{{ date('d/m/Y') }}</td>
        </tr>
    </table> --}}
</body>

</html>
