@extends('pdf.layouts.base')

@php
    // Se resuelve antes de las secciones: `@section('header')` se ejecuta
    // antes que `@section('content')` y ambas usan la empresa.
    $company = $driver->company;
@endphp

{{-- Ficha técnica del conductor. Sigue el mismo estándar que
     `pdf.fleet.technical-sheet-vehicles`: layout base (encabezado, membrete y
     marca de agua compartidos), logo desde `$data['logo']` y sin CSS duplicado. --}}

@push('styles')
    body {
        margin-top: 0.6cm;
        margin-bottom: 0.6cm;
        margin-left: 0.6cm;
        margin-right: 0.6cm;
        font-size: 9px;
    }

    /* La base aplica borde y padding a todas las celdas; esta ficha usa
       bordes solo en las tablas de datos (.table-solid) y líneas de campo. */
    table {
        table-layout: auto;
    }

    td,
    th {
        border: none;
        padding: 0;
        vertical-align: middle;
    }

    .table-solid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 5px;
    }

    .table-solid td,
    .table-solid th {
        border: 1px solid #000;
        padding: 2px 3px;
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
@endpush

@section('header')
    <table style="width: 100%; margin-bottom: 6px; border-collapse: collapse;">
        <tr>
            <td style="width: 25%; text-align: center; vertical-align: middle;">
                @if (!empty($data['logo']))
                    <img src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo"
                        style="max-height: 60px; max-width: 100%;">
                @else
                    <div style="font-weight: bold; font-size: 14px;">
                        {{ $company ? substr($company->trade_name ?? $company->business_name, 0, 2) : 'JG' }}</div>
                    <div style="font-size: 8px;">
                        {{ $company ? $company->trade_name ?? $company->business_name : 'Transportes Especiales' }}
                    </div>
                @endif
            </td>
            <td style="width: 75%; padding-left: 10px; vertical-align: top;">
                <div style="border: 1px solid #000; text-align: center; font-weight: bold; padding: 4px; margin-bottom: 4px;"
                    class="uppercase">
                    {{ $company->business_name ?? 'TRANSPORTES ESPECIALES' }}
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="border: 1px solid #000; text-align: center; width: 65%; padding: 4px;">
                            <div style="font-weight: bold; font-size: 10px;">FICHA TECNICA DEL CONDUCTOR</div>
                            <div style="font-weight: bold; font-size: 9px; margin-top: 4px;">RESPONSABLE JEFE
                                OPERATIVO
                            </div>
                        </td>
                        <td style="width: 2%;"></td>
                        <td style="width: 33%; vertical-align: top;">
                            <table class="table-solid" style="margin-bottom: 0;">
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
@endsection

@section('content')
    @php
        $license = $driver->licenciaActual();
        $contract = $driver->currentEmploymentContract();
        $contractTypeName = $contract ? str_replace('_', ' ', strtolower($contract->contract_type)) : 'N/A';

        $latestContribution = $driver->socialSecurityContributions->sortByDesc('created_at')->first();

        $epsName = $latestContribution && $latestContribution->eps_name ? $latestContribution->eps_name : ($latestContribution && $latestContribution->health_paid ? 'ACTIVA' : 'N/A');
        $riskName = $latestContribution && $latestContribution->risk_labor_name ? $latestContribution->risk_labor_name : ($latestContribution && $latestContribution->risk_labor_paid ? 'ACTIVA' : 'N/A');
        $pensionName = $latestContribution && $latestContribution->pension_name ? $latestContribution->pension_name : ($latestContribution && $latestContribution->pension_paid ? 'ACTIVA' : 'N/A');
        $compensationName = $latestContribution && $latestContribution->compensation_fund_name ? $latestContribution->compensation_fund_name : ($latestContribution && $latestContribution->compensation_fund_paid ? 'ACTIVA' : 'N/A');
    @endphp

    <!-- PERSONAL DATA -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
        <tr>
            <td style="width: 75%; vertical-align: top; padding-right: 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td class="font-bold" style="padding: 2px 0; border-bottom: 1px solid #000; width: 35%;">
                            CEDULA DE CIUDADANIA:</td>
                        <td style="padding: 2px 0; border-bottom: 1px solid #000;" class="uppercase">
                            {{ $driver->document_number }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="padding: 2px 0; border-bottom: 1px solid #000;">NOMBRES Y
                            APELLIDOS:</td>
                        <td style="padding: 2px 0; border-bottom: 1px solid #000;" class="uppercase">
                            {{ $driver->first_name }} {{ $driver->last_name }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="padding: 2px 0; border-bottom: 1px solid #000;">FECHA Y LUGAR
                            DE NACIMIENTO:</td>
                        <td style="padding: 2px 0; border-bottom: 1px solid #000;" class="uppercase">
                            {{ optional(optional($driver->municipality)->department)->name ? optional($driver->municipality)->name . ', ' . $driver->municipality->department->name : 'N/A' }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding: 2px 0; border-bottom: 1px solid #000;">
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
                        <td colspan="2" style="padding: 2px 0; border-bottom: 1px solid #000;">
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
                        <td class="font-bold" style="padding: 2px 0; border-bottom: 1px solid #000;">CORREO
                            ELECTRONICO:</td>
                        <td style="padding: 2px 0; border-bottom: 1px solid #000;" class="uppercase">
                            {{ $driver->email ?: 'NO TIENE' }}
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 25%; text-align: center; vertical-align: top;">
                <div style="border: 1px solid #000; height: 110px; width: 100%; display: table;">
                    <div style="display: table-cell; vertical-align: middle; color: #000;">
                        @if (!empty($data['photo']))
                            <img src="data:image/png;base64,{{ $data['photo'] }}" alt="Foto"
                                style="max-height: 105px; max-width: 100%;" />
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
    <table class="table-solid text-center">
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
    <div class="text-center font-bold" style="margin-bottom: 4px; margin-top: 6px;">LICENCIA(S) DE CONDUCCIÓN</div>
    <table class="table-solid text-center">
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
    <div class="text-center font-bold" style="margin-bottom: 4px; margin-top: 6px;">INFORMACIÓN DEL CONTRATO</div>
    <table class="table-solid text-center">
        <tr>
            <td class="font-bold" style="width: 17%;">TIPO DE CONTRATO</td>
            <td style="width: 33%;" class="uppercase">{{ $contractTypeName }}</td>
            <td class="font-bold" style="width: 17%;">CARGO</td>
            <td colspan="3" class="uppercase">{{ $contract ? ($contract->position ?: 'Conductor') : 'Conductor' }}</td>
        </tr>
        <tr>
            <td class="font-bold" style="width: 17%;">FECHA INICIO</td>
            <td style="width: 33%;">
                {{ $contract && $contract->start_date ? \Carbon\Carbon::parse($contract->start_date)->format('d/m/Y') : 'N/A' }}
            </td>
            <td class="font-bold" style="width: 17%;">FECHA FIN</td>
            <td style="width: 33%;">
                {{ $contract && $contract->end_date ? \Carbon\Carbon::parse($contract->end_date)->format('d/m/Y') : ($contract ? 'Indefinido' : 'N/A') }}
            </td>
            <td class="font-bold" style="width: 17%;">ESTADO</td>
            <td style="width: 33%;" class="uppercase">{{ $contract ? $contract->status : 'N/A' }}</td>
        </tr>
    </table>

    <!-- SOCIAL SECURITY -->
    <div class="text-center font-bold" style="margin-bottom: 4px; margin-top: 6px;">INFORMACION DE AFILIACIONES A
        SEGURIDAD SOCIAL</div>

    <table class="table-solid text-center" style="margin-bottom: 6px;">
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

    <table class="table-solid text-center" style="margin-bottom: 6px;">
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

    <table class="table-solid text-center" style="margin-bottom: 6px;">
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

    <table class="table-solid text-center">
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
@endsection
