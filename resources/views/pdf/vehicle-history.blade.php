<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Hoja de Vida Vehicular - {{ $data['vehicle']['vehicle_license_plate'] ?? 'N/A' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: letter;
            margin: 0cm 0cm;
        }

        body {
            margin-top: 1.5cm;
            margin-bottom: 1.0cm;
            margin-left: 1.5cm;
            margin-right: 1.5cm;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            line-height: 1.0;
            color: #000;
            background: #fff;
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

        .page {
            width: 100%;
        }

        /* HEADER */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .header-table td {
            border: none;
            padding: 3px 5px;
            vertical-align: middle;
        }

        .title-cell {
            text-align: center;
        }

        .title-cell .doc-title {
            font-size: 14pt;
            font-weight: bold;
        }

        .title-cell .responsible {
            font-size: 9pt;
            margin-top: 2px;
        }

        .meta-cell {
            width: 120px;
            font-size: 9pt;
        }

        .meta-cell div {
            margin-bottom: 2px;
        }

        /* SECTION LABEL */
        .section-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .section-header td {
            border: 0.5px solid #000;
            font-weight: bold;
            font-size: 9pt;
            text-align: center;
            padding: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #f5f5f5;
        }

        /* DATA TABLE */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .data-table td {
            border: 0.5px solid #000;
            padding: 3px 5px;
            vertical-align: middle;
            font-size: 9pt;
        }

        .data-table .label {
            font-weight: bold;
            width: 120px;
            white-space: nowrap;
        }

        .data-table .value {
            width: auto;
        }

        /* DOCS TABLE */
        .docs-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .docs-table th {
            border: 0.5px solid #000;
            padding: 3px 5px;
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            background-color: #f9f9f9;
        }

        .docs-table td {
            border: 0.5px solid #000;
            padding: 3px 5px;
            font-size: 9pt;
            text-align: center;
            vertical-align: middle;
        }

        .docs-table .doc-label {
            font-weight: bold;
            text-align: left;
            padding-left: 5px;
        }

        .vigente {
            font-weight: bold;
            color: #155724;
        }

        .vencido {
            font-weight: bold;
            color: #721c24;
        }

        .pendiente {
            font-weight: bold;
            color: #856404;
        }

        .no-border-table td,
        .no-border-table th {
            border: none !important;
            background-color: transparent !important;
        }

        .qr-section-container {
            display: table;
            width: 100%;
        }

        .qr-box {
            display: table-cell;
            width: 20%;
            padding: 6px;
            text-align: center;
            vertical-align: middle;
            border-right: none;
        }

        .instruction-box {
            display: table-cell;
            width: 80%;
            padding: 8px 12px;
            vertical-align: middle;
            text-align: left;
            font-size: 9pt;
            line-height: 1.0;
        }

        img.qr {
            width: 60px;
            height: 60px;
            display: block;
            margin: 0 auto;
        }

        .logo-header {
            max-width: 100%;
            height: auto;
            max-height: 45px;
            display: inline-block;
        }

        .legal-footer {
            font-size: 9pt;
            text-align: center;
            border: none;
            margin-top: 15px;
        }
    </style>
</head>

<body>
    @php
        $v = $data['vehicle'] ?? [];
        $brand = $data['brand'] ?? null;
        $vehicleClass = $data['vehicle_class'] ?? null;
        $company = $data['company'] ?? null;
        $thirdParty = $data['third_party'] ?? null;
        $branch = $data['branch'] ?? null;
        $owners = collect($data['owners'] ?? []);
        $documents = collect($data['documents'] ?? []);
        $operationCards = collect($data['operation_cards'] ?? []);
        $businessAgreements = collect($data['business_collaboration_agreements'] ?? []);
        $maintenances = collect($data['maintenances'] ?? []);
        $inspections = collect($data['inspections'] ?? []);
        $vehicleBranches = collect($data['vehicle_branches'] ?? []);
        $affiliateCharges = collect($data['affiliate_admin_charges'] ?? []);
        $activityLog = collect($data['activity_log'] ?? []);

        $companyName = $company->business_name ?? ($company->name ?? 'N/A');
        $brandLine = ($brand->description ?? 'N/A') . ' ' . ($v['line'] ?? '');
        $plate = $v['vehicle_license_plate'] ?? 'N/A';
        $modelYear = $v['model'] ?? 'N/A';
        $chassisVin = $v['chassis_number'] ?? ($v['vin_number'] ?? 'N/A');
        $serviceType = $v['type_of_service'] ?? 'N/A';
        $generationDate = now()->format('d/m/Y');
        $registrationDate = isset($v['registration_date'])
            ? \Carbon\Carbon::parse($v['registration_date'])->format('d/m/Y')
            : 'N/A';

        $linkageDate = isset($v['created_at'])
            ? \Carbon\Carbon::parse($v['created_at'])->format('d/m/Y')
            : $registrationDate;

        $creationActivity = $activityLog->sortBy('created_at')->first();
        $responsibleName =
            $creationActivity && $creationActivity->causer
                ? $creationActivity->causer->name
                : 'Administrador del Sistema';

        $firstInspection = $inspections->sortBy('inspection_date')->first();
        $firstMaintenance = $maintenances->sortBy('maintenance_date')->first();

        $mileageAtLinkage = 'Sin Registro';
        if ($firstInspection && isset($firstInspection->mileage) && $firstInspection->mileage > 0) {
            $mileageAtLinkage = number_format($firstInspection->mileage, 0, ',', '.') . ' km';
        } elseif ($firstMaintenance && isset($firstMaintenance->mileage) && $firstMaintenance->mileage > 0) {
            $mileageAtLinkage = number_format($firstMaintenance->mileage, 0, ',', '.') . ' km';
        }

        $previousOwner = 'Vehículo Propio / Afiliado Directo';
        if ($owners->count() > 1) {
            $previousOwner = $owners->sortByDesc('created_at')->skip(1)->first()->owner_name ?? 'N/A';
        } elseif ($owners->count() === 1) {
            $previousOwner = $owners->first()->owner_name;
        }

        $latestActiveCard = $operationCards->where('status', 'VIGENTE')->sortByDesc('expiration_date')->first();
        $activeCardNumber =
            $latestActiveCard->operating_card_number ?? ($latestActiveCard->operation_card_number ?? 'N/A');
        $activeCardExpiry =
            $latestActiveCard && $latestActiveCard->expiration_date
                ? \Carbon\Carbon::parse($latestActiveCard->expiration_date)->format('d/m/Y')
                : 'N/A';

        $rtmDoc = $documents->where('document_type', 'RTM')->first();
        $soatDoc = $documents->where('document_type', 'SOAT')->first();
        $propertyDoc = $documents->where('document_type', 'TARJETA_PROPIEDAD')->first();
        $hasRtm = $rtmDoc && $rtmDoc->status === 'VIGENTE';
        $hasActiveSoat = $soatDoc && $soatDoc->status === 'VIGENTE';
        $hasActiveProperty = $propertyDoc && $propertyDoc->status === 'VIGENTE';

        // Documentos extra (RCE, RCC, etc.) — excluye los que ya se muestran
        $extraDocs = $documents->filter(fn($d) => !in_array($d->document_type, ['SOAT', 'RTM', 'TARJETA_PROPIEDAD']));

        // Conteo de documentos vigentes (todos los tipos)
        $vigentesCount =
            $documents->where('status', 'VIGENTE')->count() +
            ($latestActiveCard ? 1 : 0) +
            ($businessAgreements->isNotEmpty() ? 1 : 0);
    @endphp

    @if (!empty($images['logo']))
        <div id="watermark">
            <img src="data:image/png;base64,{{ $images['logo'] }}" />
        </div>
    @endif

    <div class="vertical-legend">
        Generado por NOA Transportes | Fecha: {{ $generationDate }}
    </div>

    <!-- MEMBRETE -->
    <table class="header-table">
        <tr>
            <td style="width: 140px; text-align: left; vertical-align: middle; padding: 5px;">
                @if (!empty($images['logo']))
                    <img class="logo-header" src="data:image/png;base64,{{ $images['logo'] }}" alt="Logo">
                @endif
            </td>
            <td class="title-cell" style="text-align: center; vertical-align: middle;">
                <div class="doc-title">HOJA DE VIDA VEHICULAR</div>
            </td>
            <td style="width: 140px;"></td>
        </tr>
    </table>

    <table class="section-header">
        <tr>
            <td>Datos del Vehículo</td>
        </tr>
    </table>

    <table class="data-table">
        <tr>
            <td class="label">PLACA</td>
            <td class="value"><strong>{{ $plate }}</strong></td>
            <td class="label">MARCA / LÍNEA</td>
            <td class="value">{{ $brandLine }}</td>
        </tr>
        <tr>
            <td class="label">MODELO (AÑO)</td>
            <td class="value">{{ $modelYear }}</td>
            <td class="label">NO. HOJA DE VIDA</td>
            <td class="value">HVV-{{ now()->format('Y') }}-{{ $plate }}</td>
        </tr>
        <tr>
            <td class="label">VIN / NO. CHASIS</td>
            <td class="value">{{ $chassisVin }}</td>
            <td class="label">CATEGORÍA SERVICIO</td>
            <td class="value">{{ $serviceType }}</td>
        </tr>
        <tr>
            <td class="label">EMPRESA / TITULAR</td>
            <td class="value">{{ $previousOwner }}</td>
            <td class="label">FECHA VINCULACIÓN</td>
            <td class="value">{{ $linkageDate }}</td>
        </tr>
    </table>

    <table class="section-header no-border-table">
        <tr>
            <td>I. Naturaleza y Alcance del Registro</td>
        </tr>
    </table>
    <table class="data-table">
        <tr>
            <td style="padding: 10px; text-align: justify; line-height: 1.0;">
                <p style="margin-bottom: 8px;">La presente Hoja de Vida Vehicular constituye el instrumento oficial de
                    registro técnico y administrativo del vehículo identificado con placa
                    <strong>{{ $plate }}</strong>, propiedad de <strong>{{ $previousOwner }}</strong>.
                    Dicho vehículo se encuentra vinculado a la flota de <strong>{{ $companyName }}</strong>
                    a partir del {{ $linkageDate }}, operando bajo la modalidad de
                    <strong>{{ $businessAgreements->isNotEmpty() ? 'CONVENIO DE COLABORACIÓN EMPRESARIAL' : 'VINCULACIÓN DIRECTA' }}</strong>.
                </p>
                <p>Este documento se rige por lo dispuesto en la normativa aplicable y deberá conservarse actualizado
                    durante toda la vida útil del vehículo dentro de la empresa. La apertura fue registrada y validada
                    en plataforma por <strong>{{ $responsibleName }}</strong>.</p>
            </td>
        </tr>
    </table>

    <table class="section-header no-border-table">
        <tr>
            <td>II. Historial Previo a la Vinculación</td>
        </tr>
    </table>
    <table class="data-table">
        <tr>
            <td style="padding: 10px; text-align: justify; line-height: 1.0;">
                <p style="margin-bottom: 8px;">El vehículo ingresó a la flota el <strong>{{ $linkageDate }}</strong>
                    bajo la titularidad de <strong>{{ $previousOwner }}</strong>. Al momento de su alta en el sistema,
                    la documentación aportada no reporta novedades que comprometan la integridad estructural del chasis
                    o sistemas mecánicos principales.</p>
                <p>Respecto al control métrico, el odómetro al momento de la vinculación indicó
                    <strong>{{ $mileageAtLinkage }}</strong>, cifra que constituye el punto de partida del seguimiento
                    de kilometraje bajo la administración de <strong>{{ $companyName }}</strong>.
                </p>
            </td>
        </tr>
    </table>

    <table class="section-header">
        <tr>
            <td>III. Estado de la Documentación Reglamentaria</td>
        </tr>
    </table>
    <table class="docs-table">
        <tr>
            <th>Documento</th>
            <th>Estado</th>
            <th>Detalle / Vencimiento</th>
        </tr>

        <tr>
            <td class="doc-label">Tarjeta de Operación</td>
            <td class="{{ $latestActiveCard ? 'vigente' : 'vencido' }}">
                {{ $latestActiveCard ? 'VIGENTE' : 'SIN REGISTRO' }}</td>
            <td>{{ $latestActiveCard ? 'No. ' . $activeCardNumber . ' — Vence: ' . $activeCardExpiry : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="doc-label">SOAT</td>
            <td class="{{ $hasActiveSoat ? 'vigente' : 'vencido' }}">{{ $hasActiveSoat ? 'VIGENTE' : 'VENCIDO' }}</td>
            <td>{{ $soatDoc && $soatDoc->expiry_date ? 'Vence: ' . \Carbon\Carbon::parse($soatDoc->expiry_date)->format('d/m/Y') : 'N/A' }}
            </td>
        </tr>
        <tr>
            <td class="doc-label">Revisión Técnico-Mecánica (RTM)</td>
            <td class="{{ $rtmDoc ? ($rtmDoc->status === 'VIGENTE' ? 'vigente' : 'vencido') : 'pendiente' }}">
                {{ $rtmDoc ? $rtmDoc->status : 'SIN REGISTRO' }}
            </td>
            <td>{{ $rtmDoc && $rtmDoc->expiry_date ? 'Vence: ' . \Carbon\Carbon::parse($rtmDoc->expiry_date)->format('d/m/Y') : 'Sin programar' }}
            </td>
        </tr>
        @if ($businessAgreements->isNotEmpty())
            @foreach ($businessAgreements as $agreement)
                <tr>
                    <td class="doc-label">Convenio de Colaboración</td>
                    <td class="vigente">VIGENTE</td>
                    <td>{{ $agreement->contracting_entity_name ?? 'N/A' }} —
                        {{ $agreement->agreement_internal_id ?? '' }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td class="doc-label">Convenio de Colaboración</td>
                <td class="pendiente" style="color: #555;">NO APLICA</td>
                <td>VINCULACIÓN DIRECTA A LA FLOTA</td>
            </tr>
        @endif
        @foreach ($extraDocs as $doc)
            <tr>
                <td class="doc-label">{{ str_replace('_', ' ', $doc->document_type ?? ($doc->name ?? 'DOCUMENTO')) }}
                </td>
                <td class="{{ $doc->status === 'VIGENTE' ? 'vigente' : 'vencido' }}">{{ $doc->status ?? 'N/A' }}</td>
                <td>{{ $doc->expiry_date ? 'Vence: ' . \Carbon\Carbon::parse($doc->expiry_date)->format('d/m/Y') : $doc->issuing_entity ?? '' }}
                </td>
            </tr>
        @endforeach
    </table>

    <table class="section-header no-border-table">
        <tr>
            <td>IV. Registro de Inspecciones Técnicas</td>
        </tr>
    </table>
    <table class="docs-table no-border-table">
        @if ($inspections->isNotEmpty())
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Kilometraje</th>
                <th>Resultado / Observaciones</th>
            </tr>
            @foreach ($inspections->take(3) as $inspection)
                <tr>
                    <td>{{ $inspection->inspection_date ? \Carbon\Carbon::parse($inspection->inspection_date)->format('d/m/Y') : 'N/A' }}
                    </td>
                    <td>{{ $inspection->inspection_type ?? 'N/A' }}</td>
                    <td>{{ isset($inspection->mileage) && $inspection->mileage > 0 ? number_format($inspection->mileage, 0, ',', '.') . ' km' : 'N/A' }}
                    </td>
                    <td style="text-align: left;">
                        <strong>{{ $inspection->result ?? ($inspection->status ?? 'N/A') }}</strong> —
                        {{ $inspection->observations ?? ($inspection->notes ?? 'Sin observaciones') }}
                    </td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="4" style="padding: 10px;">Aún no presenta historial de inspecciones técnicas registradas
                    en plataforma.</td>
            </tr>
        @endif
    </table>

    <table class="section-header no-border-table">
        <tr>
            <td>V. Programa de Mantenimientos Recientes</td>
        </tr>
    </table>
    <table class="docs-table no-border-table">
        @if ($maintenances->isNotEmpty())
            <tr>
                <th>Tipo de Intervención</th>
                <th>Fecha / KM</th>
                <th>Próximo Mnto.</th>
                <th>Observaciones</th>
            </tr>
            @foreach ($maintenances->take(5) as $mt)
                <tr>
                    <td>{{ $mt->maintenance_type ?? 'N/A' }}</td>
                    <td>
                        {{ $mt->maintenance_date ? \Carbon\Carbon::parse($mt->maintenance_date)->format('d/m/Y') : 'N/A' }}<br>
                        <span
                            style="font-size:8px;color:#555;">{{ isset($mt->mileage) && $mt->mileage > 0 ? number_format($mt->mileage, 0, ',', '.') . ' km' : '' }}</span>
                    </td>
                    <td>{{ $mt->next_maintenance_date ? \Carbon\Carbon::parse($mt->next_maintenance_date)->format('d/m/Y') : 'N/A' }}
                    </td>
                    <td style="text-align: left;">
                        {{ current(explode("\n", wordwrap($mt->status ?? ($mt->observations ?? ''), 50))) }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="4" style="padding: 10px;">Aún no presenta historial de mantenimiento registrado en
                    plataforma.</td>
            </tr>
        @endif
    </table>

    @if ($affiliateCharges->isNotEmpty())
        <table class="section-header no-border-table">
            <tr>
                <td>VI. Cargos de Administración y Afiliación</td>
            </tr>
        </table>
        <table class="docs-table no-border-table">
            <tr>
                <th>Concepto / Referencia</th>
                <th>Último Pago</th>
                <th>Próximo Pago</th>
                <th>Estado</th>
            </tr>
            @foreach ($affiliateCharges->take(5) as $charge)
                <tr>
                    <td style="text-align: left;">{{ $charge->payment_reference ?? 'Cuota de Administración' }}</td>
                    <td>{{ $charge->payment_date ? \Carbon\Carbon::parse($charge->payment_date)->format('d/m/Y') : 'N/A' }}
                    </td>
                    <td>{{ $charge->next_payment_date ? \Carbon\Carbon::parse($charge->next_payment_date)->format('d/m/Y') : 'N/A' }}
                    </td>
                    <td>{{ $charge->status ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table class="section-header no-border-table">
        <tr>
            <td>EVALUACIÓN TÉCNICA GENERAL DEL VEHÍCULO</td>
        </tr>
    </table>
    <table class="data-table no-border-table">
        <tr>
            <td style="padding: 10px 0; text-align: justify; line-height: 1.0;">
                <p style="margin-bottom: 8px;">Al cierre de este reporte consolidado, el vehículo
                    <strong>{{ $plate }}</strong> perteneciente a la operación de
                    <strong>{{ $companyName }}</strong> presenta un estado técnico general y documental
                    <strong>SATISFACTORIO</strong>. Con {{ $vigentesCount }} documentos vigentes validados en el
                    sistema, el vehículo cuenta con la trazabilidad reglamentaria necesaria para su operación.
                </p>
                <p>El cumplimiento estricto del programa de mantenimiento, así como de las revisiones preoperacionales
                    diarias, es condición necesaria para garantizar la continuidad operativa, la seguridad de los
                    usuarios y el cumplimiento normativo exigido por el Ministerio de Transporte.</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 0 0 0; border: none; text-align: center;">
                <div class="qr-section-container" style="width: 100%; overflow: hidden; margin-bottom: 10px;">
                    <div class="qr-box">
                        @if (isset($images['qrcode']) && $images['qrcode'])
                            <img class="qr" src="data:image/svg+xml;base64,{{ $images['qrcode'] }}" alt="QR">
                        @else
                            <div style="font-size: 8px; color: #777;">Firma <br>Digital</div>
                        @endif
                    </div>
                    <div class="instruction-box">
                        <strong style="font-size: 13px;">CÓDIGO DE VERIFICACIÓN</strong><br>
                        Escanee el código QR para validar la autenticidad y vigencia de este reporte en la plataforma
                        central de la compañía operadora.
                    </div>
                </div>

                @if (!empty($images['firma']))
                    <div style="height: 60px;">
                        <img src="data:image/png;base64,{{ $images['firma'] }}"
                            style="max-height: 60px; max-width: 150px; display: block; margin: 0 auto;">
                    </div>
                @else
                    <div style="height: 60px;"></div>
                @endif
                <div style="border-top: 1px solid #000; margin: 0 30%; padding-top: 5px; text-align: center;">
                    <strong>Sello y Firma de la Empresa</strong><br>
                    {{ $companyName }}<br>
                    NIT: {{ $company->document_number ?? '___________________________' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="legal-footer">
        <p style="margin: 2px 0;">La presente Hoja de Vida Vehicular fue expedida el {{ $generationDate }}, conforme a
            los procedimientos internos de gestión documental de {{ $companyName }} y en estricto cumplimiento de la
            normativa de transporte automotor vigente en Colombia.</p>
    </div>

</body>

</html>
