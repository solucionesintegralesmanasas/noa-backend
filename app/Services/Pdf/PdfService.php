<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\Signature;
use App\Models\Vehicle;
use App\Services\Administrations\CompanyService;
use App\Services\ContractExtraction\FuecService;
use App\Services\Fleet\AffiliateAdminChargeService;
use App\Services\Fleet\BusinessCollaborationAgreementService;
use App\Services\Fleet\VehicleService;
use App\Services\ThirdParties\ThirdPartyService;
use App\Utils\Logger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio centralizado para la generación y manejo de documentos PDF utilizando Dompdf.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 */
class PdfService
{
    public function __construct(
        private readonly VehicleService $vehicleService,
        private readonly CompanyService $companyService,
        private readonly AffiliateAdminChargeService $affiliateAdminChargeService
    ) {}

    /**
     * Método generateFromView.
     *
     *
     * @return Barryvdh\DomPDF\PDF
     */
    public function generateFromView(string $view, array $data = [], array $options = []): \Barryvdh\DomPDF\PDF
    {
        $pdf = Pdf::loadView($view, $data);

        $paper = $options['paper'] ?? 'letter';
        $orientation = $options['orientation'] ?? 'portrait';
        $pdf->setPaper($paper, $orientation);

        if (! empty($options['warnings'])) {
            $pdf->setWarnings($options['warnings']);
        }

        return $pdf;
    }

    /**
     * Método downloadFromView.
     */
    public function downloadFromView(string $view, array $data = [], string $filename = 'document.pdf', array $options = []): Response
    {
        return $this->generateFromView($view, $data, $options)->download($filename);
    }

    /**
     * Método streamFromView.
     */
    public function streamFromView(string $view, array $data = [], string $filename = 'document.pdf', array $options = []): Response
    {
        return $this->generateFromView($view, $data, $options)->stream($filename);
    }

    /**
     * Método outputFromView.
     */
    public function outputFromView(string $view, array $data = [], array $options = []): string
    {
        return $this->generateFromView($view, $data, $options)->output();
    }

    /**
     * Método generateTechnicalSheetPdf.
     *
     * @param  App\Services\ThirdParties\ThirdPartyService  $thirdPartyService
     */
    public function generateTechnicalSheetPdf(
        ThirdPartyService $thirdPartyService,
        string $companyUuid,
        string $thirdPartyUuid,
        ?string $vehicleUuid = null
    ): Response {
        // Obtener la data usando el servicio de terceros
        $driverData = $thirdPartyService->technicalSheet($companyUuid, $thirdPartyUuid, $vehicleUuid);

        $company = $driverData->company;
        $logoModel = $company ? ($company->getFirstMedia('logos') ?? $company->logo ?? null) : null;
        $photoModel = $driverData->getFirstMedia('FOTO_PERFIL');

        $base64Images = $this->getBase64Parallel([
            'logo' => $logoModel,
            'photo' => $photoModel,
        ]);

        // Preparamos los datos para la vista
        $data = [
            'driver' => $driverData,
            'images' => [
                'logo' => $base64Images['logo'],
                'photo' => $base64Images['photo'],
            ],
            'generation_date' => now()->format('Y-m-d H:i:s'),
        ];

        // Definimos la vista que contendrá el diseño del PDF
        $view = 'pdf.third-parties.technical-sheet';
        $filename = 'Ficha_Tecnica_'.($driverData->document_number ?? $thirdPartyUuid).'.pdf';

        // Generamos y mostramos el PDF en el navegador (stream)
        return $this->streamFromView($view, $data, $filename, [
            'paper' => 'letter',
            'orientation' => 'portrait',
        ]);
    }

    /**
     * Método generateBusinessAgreementPdf.
     *
     * @param  App\Services\Fleet\BusinessCollaborationAgreementService  $agreementService
     */
    public function generateBusinessAgreementPdf(
        BusinessCollaborationAgreementService $agreementService,
        string $uuid
    ): Response {
        $agreement = $agreementService->getBusinessCollaborationAgreementByUuid($uuid);

        if (! $agreement) {
            abort(404, 'El registro de Convenio de Colaboración Empresarial no existe.');
        }

        $operationCard = null;
        if ($agreement->vehicle) {
            $operationCard = $agreement->vehicle->operationCards()->latest('expiration_date')->first();
        }

        $logoModel = $agreement->company ? ($agreement->company->getFirstMedia('logos') ?? $agreement->company->logo ?? null) : null;
        $signatureModel = $agreement->company ? ($agreement->company->getFirstMedia('signatures') ?? $agreement->company->signature ?? null) : null;

        $base64Images = $this->getBase64Parallel([
            'logo' => $logoModel,
            'firma' => $signatureModel,
        ]);

        $superPath = public_path('img/super2.png');
        $transporPath = public_path('img/transporte.png');

        $superBase64 = file_exists($superPath) ? base64_encode(file_get_contents($superPath)) : null;
        $transporBase64 = file_exists($transporPath) ? base64_encode(file_get_contents($transporPath)) : null;

        $data = [
            'data' => [
                'agreement_internal_id' => $agreement->agreement_internal_id,
                'logo' => $base64Images['logo'],
                'firma' => $base64Images['firma'],
                'super' => $superBase64,
                'transpor' => $transporBase64,
                'signing_date' => $agreement->effective_date ? $agreement->effective_date->format('Y-m-d') : now()->format('Y-m-d'),
                'legal_representative_name' => $agreement->company ? ($agreement->company->legal_representative_name.' '.$agreement->company->legal_representative_last_name) : 'N/A',
                'document_number' => $agreement->company ? $agreement->company->legal_representative_document_number : 'N/A',
                'document_number_expedition_place' => $agreement->company && $agreement->company->municipality ? $agreement->company->municipality->name : 'N/A',
                'company_name' => $agreement->company ? $agreement->company->business_name : 'N/A',
                'nit' => $agreement->company ? $agreement->company->document_number : 'N/A',
                'rep_name' => $agreement->rep_name,
                'rep_document_id' => $agreement->rep_document_id,
                'contracting_entity_name' => $agreement->contracting_entity_name,
                'contracting_entity_nit' => $agreement->contracting_entity_nit,
                'vehicle' => [
                    'vehicle_license_plate' => $agreement->vehicle ? $agreement->vehicle->vehicle_license_plate : 'N/A',
                    'vehicle_brand' => $agreement->vehicle && $agreement->vehicle->brand ? $agreement->vehicle->brand->name : 'N/A',
                    'vehicle_line' => $agreement->vehicle ? $agreement->vehicle->line : 'N/A',
                    'vehicle_model' => $agreement->vehicle ? $agreement->vehicle->model : 'N/A',
                    'vehicle_capacity' => $agreement->vehicle ? $agreement->vehicle->passenger_capacity : 'N/A',
                    'operational_card_number' => $operationCard ? $operationCard->operation_card_number : 'N/A',
                    'expiration_date' => $operationCard ? $operationCard->expiration_date->format('Y-m-d') : null,
                ],
                'signing_city' => $agreement->company && $agreement->company->municipality ? $agreement->company->municipality->name : 'N/A',
            ],
        ];

        $view = 'pdf.business-agreement';
        $placa = $agreement->vehicle ? $agreement->vehicle->vehicle_license_plate : 'Sin_Placa';
        $filename = 'Convenio de Colaboración Empresarial - '.($agreement->agreement_internal_id ?? $uuid).' - '.$placa.'.pdf';

        return $this->streamFromView($view, $data, $filename, [
            'paper' => 'letter',
            'orientation' => 'portrait',
        ]);
    }

    /**
     * Método getFuecPdf.
     */
    public function getFuecPdf(string $verificationCode, array $options = []): array
    {
        try {
            $fuecService = app(FuecService::class);
            $fuec = $fuecService->getPdfData($verificationCode);

            if (! $fuec) {
                throw new ModelNotFoundException(
                    "FUEC con código de verificación [{$verificationCode}] no encontrado."
                );
            }

            $orientation = $options['orientation'] ?? 'portrait';
            $paperSize = $options['paper_size'] ?? 'a4';

            $vCode = strtolower($fuec->verification_code);

            $qrUrl = (config('app.env') === 'production' ? 'https://' : 'http://').
                ($fuec->company->web_page ?? config('app.url')).
                '/#/validacion-de-fuec/'.$vCode;

            // Generar código QR usando BaconQrCode (disponible nativamente en composer.lock)
            $renderer = new ImageRenderer(
                new RendererStyle(150),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);
            $qrCodeSvg = $writer->writeString($qrUrl);
            $qrCodeBase64 = base64_encode($qrCodeSvg);

            // ✅ Logos desde la configuración del sistema (con fallback a archivos estáticos)
            $systemConfig = \App\Models\SystemConfiguration::where('company_uuid', $fuec->company_uuid)->first();

            // Logo del Ministerio de Transporte
            $ministryMedia = $systemConfig?->getFirstMedia('MINISTRY_LOGO');
            if ($ministryMedia && $ministryMedia->getPath() && file_exists($ministryMedia->getPath())) {
                $transporBase64 = $this->optimizeAndEncodeImage($ministryMedia->getPath());
            } else {
                $transporPath = public_path('img/transporte.png');
                $transporBase64 = file_exists($transporPath) ? base64_encode(file_get_contents($transporPath)) : null;
            }

            // Logo de la Superintendencia
            $superMedia = $systemConfig?->getFirstMedia('SUPER_LOGO');
            if ($superMedia && $superMedia->getPath() && file_exists($superMedia->getPath())) {
                $superBase64 = $this->optimizeAndEncodeImage($superMedia->getPath());
            } else {
                $superPath = public_path('img/super2.png');
                $superBase64 = file_exists($superPath) ? base64_encode(file_get_contents($superPath)) : null;
            }

            // ✅ Logo y firma de la empresa en paralelo
            $images = $this->getBase64Parallel([
                'logo' => $fuec->company->logo ?? null,
                'firma' => $fuec->company->signature ?? null,
            ]);

            // Verificar si hay documentos vencidos (RTM, SOAT, RCC, RCE, Tarjeta de Operación)
            // Solo se considera el documento más reciente de cada tipo
            $canceladoBase64 = null;
            $hasExpired = false;
            $today = now();

            if (isset($fuec->vehicle_documents) && $fuec->vehicle_documents->isNotEmpty()) {
                $latestPerType = $fuec->vehicle_documents->groupBy('document_type')->map(function ($docs) {
                    return $docs->sortByDesc('expiry_date')->first();
                });
                foreach ($latestPerType as $doc) {
                    if ($doc->expiry_date && $doc->expiry_date->lt($today)) {
                        $hasExpired = true;
                        break;
                    }
                }
            }

            if (!$hasExpired && isset($fuec->vehicle->operationCards) && $fuec->vehicle->operationCards->isNotEmpty()) {
                $latestOpCard = $fuec->vehicle->operationCards->sortByDesc('expiration_date')->first();
                if ($latestOpCard->expiration_date && $latestOpCard->expiration_date->lt($today)) {
                    $hasExpired = true;
                }
            }

            if ($hasExpired) {
                $canceladoPath = public_path('img/cancelado.png');
                if (file_exists($canceladoPath)) {
                    $canceladoBase64 = base64_encode(file_get_contents($canceladoPath));
                }
            }

            $data = [
                'qrcode' => $qrCodeBase64,
                'firma' => $images['firma'],
                'super' => $superBase64,
                'transpor' => $transporBase64,
                'logo' => $images['logo'],
                'qr_url' => $qrUrl,
                'cancelado' => $canceladoBase64,
            ];

            $pdf = Pdf::loadView('pdf.fuec', [
                'fuec' => $fuec,
                'data' => $data,
            ])
                ->setPaper($paperSize, $orientation)
                ->setOption('defaultFont', 'Arial')
                ->setOption('isRemoteEnabled', false)        // ✅ ya usamos base64
                ->setOption('isHtml5ParserEnabled', true)    // ✅ parser más rápido
                ->setOption('isFontSubsettingEnabled', true); // ✅ fuentes más livianas

            return [
                'pdf' => $pdf,
                'file_name' => 'FUEC-'.$fuec->number_fuec.'.pdf',
            ];
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    // ✅ Nuevo método paralelo
    /**
     * Método getBase64Parallel.
     */
    public function getBase64Parallel(array $fileModels): array
    {
        $results = [];
        $pendingHttp = [];

        foreach ($fileModels as $key => $fileModel) {
            if (! $fileModel) {
                $results[$key] = null;

                continue;
            }

            if (is_string($fileModel)) {
                if (file_exists($fileModel)) {
                    $results[$key] = $this->optimizeAndEncodeImage($fileModel);
                } else {
                    $pendingHttp[$key] = $fileModel;
                }

                continue;
            }

            // Spatie MediaLibrary
            if (is_object($fileModel) && method_exists($fileModel, 'getPath')) {
                try {
                    $path = $fileModel->getPath();
                    if ($path && file_exists($path)) {
                        $results[$key] = $this->optimizeAndEncodeImage($path);

                        continue;
                    }
                } catch (\Exception $e) {
                    Logger::warning('No se pudo cargar Spatie Media local: '.$e->getMessage());
                }
            }

            try {
                if (isset($fileModel->disk) && isset($fileModel->path)) {
                    if (Storage::disk($fileModel->disk)->exists($fileModel->path)) {
                        $path = Storage::disk($fileModel->disk)->path($fileModel->path);
                        $results[$key] = $this->optimizeAndEncodeImage($path);

                        continue;
                    }
                }
            } catch (\Exception $e) {
                Logger::warning('No se pudo cargar archivo local: '.$e->getMessage());
            }

            if (isset($fileModel->url)) {
                $pendingHttp[$key] = $fileModel->url;
            } else {
                $results[$key] = null;
            }
        }

        if (! empty($pendingHttp)) {
            $multi = curl_multi_init();
            $handles = [];

            foreach ($pendingHttp as $key => $url) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 5,
                ]);
                curl_multi_add_handle($multi, $ch);
                $handles[$key] = $ch;
            }

            do {
                curl_multi_exec($multi, $running);
                curl_multi_select($multi);
            } while ($running > 0);

            foreach ($handles as $key => $ch) {
                $content = curl_multi_getcontent($ch);
                $results[$key] = $content ? base64_encode($this->optimizeBinaryImage($content)) : null;
                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
            }

            curl_multi_close($multi);
        }

        return $results;
    }

    /**
     * Método generateVehicleHistoryPdf.
     */
    public function generateVehicleHistoryPdf(string $vehicleUuid): array
    {
        try {
            $history = $this->vehicleService->getVehicleHistory($vehicleUuid);

            $superPath = public_path('img/super2.png');
            $transporPath = public_path('img/transporte.png');

            $superBase64 = file_exists($superPath) ? base64_encode(file_get_contents($superPath)) : null;
            $transporBase64 = file_exists($transporPath) ? base64_encode(file_get_contents($transporPath)) : null;

            $company = $history['company'] ?? null;
            $fullCompany = $company && isset($company->uuid) ? $this->companyService->getCompanyByUuid($company->uuid) : $company;

            $logoModel = $fullCompany ? ($fullCompany->getFirstMedia('logos') ?? $fullCompany->logo ?? null) : null;
            $signatureModel = $fullCompany ? ($fullCompany->getFirstMedia('signatures') ?? $fullCompany->signature ?? null) : null;

            $base64Images = $this->getBase64Parallel([
                'logo' => $logoModel,
                'firma' => $signatureModel,
            ]);

            $qrUrl = 'https://'.($fullCompany->web_page ?? 'falcon-fuec.com').'/validar-vehiculo/'.$vehicleUuid;
            $renderer = new ImageRenderer(
                new RendererStyle(150),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);
            $qrCodeSvg = $writer->writeString($qrUrl);
            $qrCodeBase64 = base64_encode($qrCodeSvg);

            $images = [
                'super' => $superBase64,
                'transpor' => $transporBase64,
                'logo' => $base64Images['logo'],
                'firma' => $base64Images['firma'],
                'qrcode' => $qrCodeBase64,
            ];

            $vehicle = $history['vehicle'] ?? [];

            $pdf = Pdf::loadView('pdf.vehicle-history', [
                'data' => $history,
                'images' => $images,
            ]);

            $fileName = 'Hoja_Vida_Vehicular_'.($vehicle['vehicle_license_plate'] ?? $vehicleUuid).'.pdf';

            return [
                'pdf' => $pdf,
                'file_name' => $fileName,
            ];
        } catch (\Exception $e) {
            Logger::error('PdfService@generateVehicleHistoryPdf: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método generateVehicleTechnicalSheetPdf.
     */
    public function generateVehicleTechnicalSheetPdf(string $vehicleUuid): array
    {
        try {
            $vehicle = $this->vehicleService->getTechnicalSheetData($vehicleUuid);

            // ✅ Cargar imágenes estáticas locales
            $superPath = public_path('img/super2.png');
            $transporPath = public_path('img/transporte.png');

            $superBase64 = file_exists($superPath) ? base64_encode(file_get_contents($superPath)) : null;
            $transporBase64 = file_exists($transporPath) ? base64_encode(file_get_contents($transporPath)) : null;

            // ✅ Intentar obtener el logo y firma de la empresa y la página web
            $webPage = 'falcon-fuec.com';
            $logoModel = null;
            $signatureModel = null;

            if ($vehicle->company_uuid) {
                $company = $this->companyService->getCompanyByUuid($vehicle->company_uuid);
                if ($company) {
                    $webPage = $company->web_page ?? 'falcon-fuec.com';
                    $logoModel = $company->getFirstMedia('logos') ?? $company->logo ?? null;
                    $signatureModel = $company->getFirstMedia('signatures') ?? $company->signature ?? null;
                }
            }

            $base64Images = $this->getBase64Parallel([
                'logo' => $logoModel,
                'firma' => $signatureModel,
            ]);

            // ✅ Generar Código QR usando BaconQrCode (disponible nativamente)
            $qrUrl = 'https://'.$webPage.'/validar-vehiculo/'.$vehicle->uuid;

            $renderer = new ImageRenderer(
                new RendererStyle(150),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);
            $qrCodeSvg = $writer->writeString($qrUrl);
            $qrCodeBase64 = base64_encode($qrCodeSvg);

            $data = [
                'super' => $superBase64,
                'transpor' => $transporBase64,
                'logo' => $base64Images['logo'],
                'firma' => $base64Images['firma'],
                'qrcode' => $qrCodeBase64,
            ];

            $pdf = Pdf::loadView('pdf.technical-sheet-vehicles', [
                'vehicle' => $vehicle,
                'data' => $data,
            ]);

            $fileName = 'Ficha_Tecnica_'.($vehicle->vehicle_license_plate ?? $vehicleUuid).'.pdf';

            return [
                'pdf' => $pdf,
                'file_name' => $fileName,
            ];
        } catch (\Exception $e) {
            Logger::error('PdfGenerationService@generateVehicleTechnicalSheetPdf: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método generateHandoverRecordPdf.
     *
     * Genera el PDF del Acta de Entrega del vehículo. La plantilla precarga
     * los datos del vehículo y del propietario/tercero; el resto del contenido
     * se diligencia a mano al momento de imprimir.
     *
     * @return array<string, mixed> Array con la instancia del PDF y el nombre del archivo.
     */
    public function generateHandoverRecordPdf(string $vehicleUuid): array
    {
        try {
            $dataVehicle = $this->vehicleService->getHandoverRecordData($vehicleUuid);

            $logoModel = null;
            $signatureModel = null;

            // Obtener logo/firma desde la empresa del vehículo (se busca por company_uuid)
            $companyModel = null;
            if (! empty($dataVehicle['company_uuid'])) {
                $companyModel = $this->companyService->getCompanyByUuid($dataVehicle['company_uuid']);
            }
            if ($companyModel) {
                $logoModel = $companyModel->getFirstMedia('logos') ?? $companyModel->logo ?? null;
                $signatureModel = $companyModel->getFirstMedia('signatures') ?? $companyModel->signature ?? null;
            }

            $base64Images = $this->getBase64Parallel([
                'logo' => $logoModel,
                'firma' => $signatureModel,
            ]);

            $data = [
                'vehiculo' => (object) $dataVehicle['vehiculo'],
                'propietario' => (object) $dataVehicle['propietario'],
                'entrega' => (object) $dataVehicle['entrega'],
                'company' => $dataVehicle['company'],
                'images' => [
                    'logo' => $base64Images['logo'],
                    'firma' => $base64Images['firma'],
                ],
                'generation_date' => now()->format('d/m/Y'),
            ];

            $pdf = Pdf::loadView('pdf.handover-record', $data);
            $pdf->setPaper('letter', 'portrait');

            $fileName = 'Acta_Entrega_'.($dataVehicle['vehiculo']['placa'] ?: $vehicleUuid).'.pdf';

            return [
                'pdf' => $pdf,
                'file_name' => $fileName,
            ];
        } catch (\Exception $e) {
            Logger::error('PdfService@generateHandoverRecordPdf: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método generateAffiliateAdminChargePdf.
     */
    public function generateAffiliateAdminChargePdf(
        string $uuid
    ): Response {
        $charge = $this->affiliateAdminChargeService->getForReceiptPdf($uuid);

        if (! $charge) {
            abort(404, 'El registro de Administración no existe.');
        }

        $logoModel = $charge->company ? ($charge->company->getFirstMedia('logos') ?? $charge->company->logo ?? null) : null;
        $signatureModel = $charge->company ? ($charge->company->getFirstMedia('signatures') ?? $charge->company->signature ?? null) : null;

        $base64Images = $this->getBase64Parallel([
            'logo' => $logoModel,
            'firma' => $signatureModel,
        ]);

        $superPath = public_path('img/super2.png');
        $transporPath = public_path('img/transporte.png');

        $superBase64 = file_exists($superPath) ? base64_encode(file_get_contents($superPath)) : null;
        $transporBase64 = file_exists($transporPath) ? base64_encode(file_get_contents($transporPath)) : null;

        $receiptNumber = $charge->charge_internal_id ?? $uuid;
        $qrData = "Recibo: {$receiptNumber} | Fecha: ".($charge->payment_date ? $charge->payment_date->format('Y-m-d') : 'N/A')." | Valor: {$charge->amount}";
        $renderer = new ImageRenderer(
            new RendererStyle(120),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $qrBase64 = base64_encode($writer->writeString($qrData));

        $data = [
            'charge' => $charge,
            'data' => [
                'charge_internal_id' => $receiptNumber,
                'logo' => $base64Images['logo'],
                'firma' => $base64Images['firma'],
                'super' => $superBase64,
                'transpor' => $transporBase64,
                'qr_code' => 'data:image/svg+xml;base64,'.$qrBase64,
                'signing_date' => $charge->created_at->format('Y-m-d'),
                'payment_date' => $charge->payment_date ? $charge->payment_date->format('Y-m-d') : 'N/A',
                'legal_representative_name' => $charge->company ? ($charge->company->legal_representative_name.' '.$charge->company->legal_representative_last_name) : 'N/A',
                'document_number' => $charge->company ? $charge->company->legal_representative_document_number : 'N/A',
                'document_number_expedition_place' => $charge->company && $charge->company->municipality ? $charge->company->municipality->name : 'N/A',
                'company_name' => $charge->company ? $charge->company->business_name : 'N/A',
                'company_document_number' => $charge->company ? $charge->company->nit : 'N/A',
                'vehicle_license_plate' => $charge->vehicle ? $charge->vehicle->vehicle_license_plate : 'N/A',
                'vehicle_model' => $charge->vehicle ? $charge->vehicle->model : 'N/A',
                'vehicle_brand' => ($charge->vehicle && $charge->vehicle->brand) ? $charge->vehicle->brand->description : 'N/A',
                'vehicle_type' => $charge->vehicle ? $charge->vehicle->type_of_service : 'N/A',
                'vehicle_capacity' => $charge->vehicle ? $charge->vehicle->passenger_capacity : 'N/A',
                'office_name' => $charge->office ? $charge->office->name : 'N/A',
                'office_code' => $charge->office ? $charge->office->code : 'N/A',
            ],
        ];

        $view = 'pdf.admin-charge';
        $filename = 'Constancia_de_Administracion_'.($charge->charge_internal_id ?? $uuid).'.pdf';

        return $this->streamFromView($view, $data, $filename, [
            'paper' => 'letter',
            'orientation' => 'portrait',
        ]);
    }

    /**
     * Método generateMaintenanceHistoryPdf.
     */
    public function generateMaintenanceHistoryPdf(string $vehicleUuid): array
    {
        try {
            $vehicle = $this->vehicleService->getVehicleByUuid($vehicleUuid);
            if (! $vehicle) {
                throw new ModelNotFoundException("Vehículo con UUID {$vehicleUuid} no encontrado.");
            }

            $vehicle->load([
                'brand',
                'vehicleClass',
                'company',
                'maintenance' => function ($q) {
                    $q->orderBy('maintenance_date', 'asc');
                },
            ]);

            $logoModel = $vehicle->company ? ($vehicle->company->getFirstMedia('logos') ?? $vehicle->company->logo ?? null) : null;

            $base64Images = $this->getBase64Parallel([
                'logo' => $logoModel,
            ]);

            $data = [
                'vehicle' => $vehicle,
                'company' => $vehicle->company,
                'maintenances' => $vehicle->maintenance ?? collect(),
                'company_logo_base64' => $base64Images['logo'],
            ];

            $pdf = Pdf::loadView('pdf.maintenance', $data);
            $pdf->setPaper('letter', 'portrait');

            $fileName = 'Hoja_Vida_Mantenimiento_'.($vehicle->vehicle_license_plate ?? $vehicleUuid).'.pdf';

            return [
                'pdf' => $pdf,
                'file_name' => $fileName,
            ];
        } catch (\Exception $e) {
            Logger::error('PdfService@generateMaintenanceHistoryPdf: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Genera el PDF de la hoja de control de prestación de servicios para un día específico (Diario).
     *
     * @param  string  $uuid  UUID de la hoja de control de entrega de servicios.
     * @return array<string, mixed> Array con la instancia del PDF y el nombre del archivo.
     *
     * @throws ModelNotFoundException Si la hoja de control no existe.
     * @throws \Exception Si ocurre un error en la generación del PDF.
     */
    public function generateDailyServiceControlSheetPdf(string $uuid): array
    {
        try {
            $sheet = ServiceDeliveryControlSheet::where('uuid', $uuid)->firstOrFail();

            // Obtener datos del vehículo, conductor, contratista y empresa de transporte
            $company = $sheet->company;
            $logoModel = $company ? ($company->getFirstMedia('logos') ?? $company->logo ?? null) : null;
            $empresaTransportadora = $company?->business_name ?? 'TRANSPORTES SIN BARRERAS S.A.S.';

            $placa = $sheet->vehicle_license_plate;
            $area = $sheet->official_name_and_surname; // Area o dependencia del funcionario que firma

            // Determinar clase del vehículo
            $clase = 'N/A';
            if ($sheet->type_of_control_sheet === 'DIRECTO_CON_LA_EMPRESA' && $sheet->internalControl?->vehicle?->vehicleClass) {
                $clase = $sheet->internalControl->vehicle->vehicleClass->name;
            } elseif (($sheet->type_of_control_sheet === 'SUBCONTRATADO' || $sheet->type_of_control_sheet === 'CON_VEHICULO_CONTRATADO') && $sheet->subcontractedControl?->vehicleClass) {
                $clase = $sheet->subcontractedControl->vehicleClass->name;
            }

            // Determinar contratista/cliente y su NIT
            $empresa = 'N/A';
            $nit = 'N/A';
            if ($sheet->type_of_control_sheet === 'DIRECTO_CON_LA_EMPRESA' && $sheet->internalControl?->fuec?->contractor) {
                $empresa = $sheet->internalControl->fuec->contractor->company_name;
                $nit = $sheet->internalControl->fuec->contractor->document_number;
            } else {
                $empresa = $company?->business_name ?? 'N/A';
                $nit = $company?->document_number ?? 'N/A';
            }

            // Cargar las firmas asociadas a esta hoja de control
            $signatures = Signature::query()
                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheet')
                ->where('entity_id', $sheet->id)
                ->orderBy('id', 'asc')
                ->get();

            // Si este registro es hijo de un servicio multi-día y no tiene firmas propias,
            // usar las firmas del registro padre
            if ($signatures->isEmpty() && $sheet->parent_uuid) {
                $parentSheet = ServiceDeliveryControlSheet::where('uuid', $sheet->parent_uuid)->first();
                if ($parentSheet) {
                    $signatures = Signature::query()
                        ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheet')
                        ->where('entity_id', $parentSheet->id)
                        ->orderBy('id', 'asc')
                        ->get();
                }
            }

            $base64Images = $this->getBase64Parallel([
                'logo' => $logoModel,
                'firma_recibido' => $signatures->get(0), // La primera guardada es el funcionario que recibe
                'firma_conductor' => $signatures->get(1),  // La segunda guardada es el conductor
            ]);

            // Formatear el periodo
            $date = Carbon::parse($sheet->service_date);
            $periodo = $date->translatedFormat('F Y');

            // Para la planilla diaria, mostrar el registro en la primera fila, y luego rellenar el resto
            $dias = [];

            $restHours = $this->calculateRestHours($sheet->start_time, $sheet->end_time, $sheet->total_hours);

            $dias[] = [
                'numero' => (int) $date->format('d'),
                'ruta' => $sheet->daily_route,
                'hora_inicio' => $sheet->start_time ? $sheet->start_time->format('H:i') : '',
                'descanso_inicio' => $restHours['inicio'],
                'descanso_fin' => $restHours['fin'],
                'hora_fin' => $sheet->end_time ? $sheet->end_time->format('H:i') : '',
                'total_hours' => $sheet->total_hours ? $sheet->total_hours->format('H:i') : '',
                'km_inicial' => $sheet->starting_kilometer,
                'km_final' => $sheet->ending_kilometer,
                'km_total' => $sheet->starting_kilometer !== null && $sheet->ending_kilometer !== null
                    ? ($sheet->ending_kilometer - $sheet->starting_kilometer)
                    : '',
                'firma_funcionario' => $base64Images['firma_recibido'],
                'conductor' => $sheet->driver_name,
            ];

            // No agregamos filas vacías para que la tabla solo muestre los registros reales

            $data = [
                'logo' => $base64Images['logo'],
                'empresa_transportadora' => $empresaTransportadora,
                'periodo' => $periodo,
                'es_historial' => false,
                'placa' => $placa,
                'area' => $area,
                'clase' => $clase,
                'nit' => $nit,
                'empresa' => $empresa,
                'dias' => $dias,
                'observaciones' => $sheet->number_of_tolls ? "Peajes: {$sheet->number_of_tolls} | Valor: $ {$sheet->total_toll_value}" : '',
                'firma_conductor' => $base64Images['firma_conductor'],
                'firma_recibido' => $base64Images['firma_recibido'],
            ];

            $pdf = Pdf::loadView('pdf.service-control-sheet', $data);
            $pdf->setPaper('letter', 'landscape');

            $fileName = 'Planilla_Control_Diaria_'.($placa ?? 'Vehiculo').'_'.$date->format('Ymd').'.pdf';

            return [
                'pdf' => $pdf,
                'file_name' => $fileName,
            ];
        } catch (\Exception $e) {
            Logger::error('PdfService@generateDailyServiceControlSheetPdf error: '.$e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Genera el PDF consolidado de la hoja de control de prestación de servicios para todo un mes (Mensual / Historial).
     *
     * @param  string  $companyUuid  UUID de la empresa.
     * @param  string  $vehicleUuid  UUID del vehículo.
     * @param  int  $year  Año a consultar.
     * @param  int  $month  Mes a consultar (1-12).
     * @return array<string, mixed> Array con la instancia del PDF y el nombre del archivo.
     *
     * @throws ModelNotFoundException Si el vehículo no existe.
     * @throws \Exception Si ocurre un error en la generación del PDF.
     */
    public function generateMonthlyServiceControlSheetPdf(string $companyUuid, string $vehicleUuid, int $year, int $month): array
    {
        try {
            $vehicle = Vehicle::where('uuid', $vehicleUuid)->firstOrFail();
            $company = $vehicle->company;
            $logoModel = $company ? ($company->getFirstMedia('logos') ?? $company->logo ?? null) : null;
            $empresaTransportadora = $company?->business_name ?? 'TRANSPORTES SIN BARRERAS S.A.S.';

            // Buscar todos los registros de planillas para este vehículo en el mes y año seleccionados
            $sheets = ServiceDeliveryControlSheet::query()
                ->where('company_uuid', $companyUuid)
                ->where(function ($query) use ($vehicle) {
                    $query->whereHas('internalControl', function ($q) use ($vehicle) {
                        $q->where('vehicle_uuid', $vehicle->uuid);
                    })->orWhereHas('subcontractedControl', function ($q) use ($vehicle) {
                        $q->where('vehicle_license_plate', $vehicle->vehicle_license_plate);
                    });
                })
                ->whereYear('service_date', $year)
                ->whereMonth('service_date', $month)
                ->orderBy('service_date', 'asc')
                ->get();

            // Optimización premium: Cargar todas las firmas asociadas a las planillas encontradas de una sola vez
            $sheetsIds = $sheets->pluck('id')->toArray();
            $allSignatures = Signature::query()
                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheet')
                ->whereIn('entity_id', $sheetsIds)
                ->orderBy('id', 'asc')
                ->get()
                ->groupBy('entity_id');

            // Determinar si han pasado los 30 días calendario del mes para guardarlo en el historial permanente
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            $esHistorial = $endOfMonth->copy()->addDays(30)->isPast();

            // Formatear periodo
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $periodo = $startDate->translatedFormat('F Y');
            $daysInMonth = $startDate->daysInMonth;

            // Datos generales basados en el último servicio o del vehículo
            $lastSheet = $sheets->last();
            $placa = $vehicle->vehicle_license_plate;
            $area = $lastSheet ? $lastSheet->official_name_and_surname : 'N/A';
            $clase = $vehicle->vehicleClass?->name ?? 'N/A';

            $empresa = 'N/A';
            $nit = 'N/A';
            if ($lastSheet && $lastSheet->type_of_control_sheet === 'DIRECTO_CON_LA_EMPRESA' && $lastSheet->internalControl?->fuec?->contractor) {
                $empresa = $lastSheet->internalControl->fuec->contractor->company_name;
                $nit = $lastSheet->internalControl->fuec->contractor->document_number;
            } else {
                $empresa = $company?->business_name ?? 'N/A';
                $nit = $company?->document_number ?? 'N/A';
            }

            // Construir el listado diario del mes (secuencial, de 1 en 1 sin saltos de filas)
            $dias = [];
            $signatureModelsToLoad = [];

            foreach ($sheets as $sheet) {
                $sheetDate = Carbon::parse($sheet->service_date);
                $dayNum = $sheetDate->format('d');
                $uniqueKey = "{$dayNum}_{$sheet->id}";

                $sheetSignatures = $allSignatures->get($sheet->id, collect());
                $firmaFuncModel = $sheetSignatures->first(); // Funcionario que recibe

                // Si el día no tiene firmas propias pero pertenece a un servicio multi-día,
                // buscar las firmas del registro padre
                if ($firmaFuncModel === null && $sheet->parent_uuid) {
                    $parentId = $sheets->firstWhere('uuid', $sheet->parent_uuid)?->id;
                    if ($parentId && $allSignatures->has($parentId)) {
                        $firmaFuncModel = $allSignatures->get($parentId)->first();
                    }

                    // Si el padre no está en el mes consultado, buscar sus firmas directamente
                    if ($firmaFuncModel === null) {
                        $parentSheet = ServiceDeliveryControlSheet::where('uuid', $sheet->parent_uuid)->first();
                        if ($parentSheet) {
                            $parentSignature = Signature::query()
                                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheet')
                                ->where('entity_id', $parentSheet->id)
                                ->orderBy('id', 'asc')
                                ->first();

                            if ($parentSignature) {
                                $signatureModelsToLoad["firma_func_{$uniqueKey}"] = $parentSignature;
                                $firmaFuncModel = $parentSignature;
                            }
                        }
                    }
                }

                if ($firmaFuncModel) {
                    $signatureModelsToLoad["firma_func_{$uniqueKey}"] = $firmaFuncModel;
                }

                $restHours = $this->calculateRestHours($sheet->start_time, $sheet->end_time, $sheet->total_hours);

                $dias[] = [
                    'numero' => $dayNum,
                    'ruta' => $sheet->daily_route,
                    'hora_inicio' => $sheet->start_time ? $sheet->start_time->format('H:i') : '',
                    'descanso_inicio' => $restHours['inicio'],
                    'descanso_fin' => $restHours['fin'],
                    'hora_fin' => $sheet->end_time ? $sheet->end_time->format('H:i') : '',
                    'total_hours' => $sheet->total_hours ? $sheet->total_hours->format('H:i') : '',
                    'km_inicial' => $sheet->starting_kilometer,
                    'km_final' => $sheet->ending_kilometer,
                    'km_total' => ($sheet->starting_kilometer !== null && $sheet->ending_kilometer !== null)
                        ? ($sheet->ending_kilometer - $sheet->starting_kilometer)
                        : '',
                    'firma_funcionario' => null,
                    'unique_key' => $uniqueKey,
                    'conductor' => $sheet->driver_name,
                ];
            }

            // No rellenamos filas vacías adicionales para que solo se vean los registros reales

            // Obtener las firmas para el pie de página mensual (del último servicio firmado del mes)
            $lastSheetWithSignatures = $sheets->reverse()->first(function ($s) use ($allSignatures) {
                return $allSignatures->has($s->id);
            });

            if ($lastSheetWithSignatures) {
                $lastSignatures = $allSignatures->get($lastSheetWithSignatures->id, collect());
                if ($lastSignatures->isNotEmpty()) {
                    $signatureModelsToLoad['firma_recibido_mensual'] = $lastSignatures->first();
                    $signatureModelsToLoad['firma_conductor_mensual'] = $lastSignatures->get(1);
                }
            }

            // Cargar todas las imágenes en paralelo
            $filesToLoad = array_merge(['logo' => $logoModel], $signatureModelsToLoad);
            $base64Images = $this->getBase64Parallel($filesToLoad);

            // Mapear las firmas a cada día correspondiente
            foreach ($dias as &$dia) {
                if (! empty($dia['unique_key'])) {
                    $uKey = $dia['unique_key'];
                    $dia['firma_funcionario'] = $base64Images["firma_func_{$uKey}"] ?? null;
                }
            }
            unset($dia);

            // Construir observaciones de peajes consolidadas del mes
            $totalPeajes = $sheets->sum('number_of_tolls');
            $valorPeajes = $sheets->sum('total_toll_value');
            $obs = $totalPeajes > 0 ? "Total Peajes del Mes: {$totalPeajes} | Valor Consolidado: $ {$valorPeajes}" : '';

            $data = [
                'logo' => $base64Images['logo'],
                'empresa_transportadora' => $empresaTransportadora,
                'periodo' => $periodo,
                'es_historial' => $esHistorial,
                'placa' => $placa,
                'area' => $area,
                'clase' => $clase,
                'nit' => $nit,
                'empresa' => $empresa,
                'dias' => $dias,
                'observaciones' => $obs,
                'firma_conductor' => $base64Images['firma_conductor_mensual'] ?? null,
                'firma_recibido' => $base64Images['firma_recibido_mensual'] ?? null,
            ];

            $pdf = Pdf::loadView('pdf.service-control-sheet', $data);
            $pdf->setPaper('letter', 'landscape');

            $fileName = ($esHistorial ? 'Historial_Planilla_Control_Mensual_' : 'Planilla_Control_Mensual_').
                ($placa ?? 'Vehiculo').'_'.$startDate->format('Y_m').'.pdf';

            return [
                'pdf' => $pdf,
                'file_name' => $fileName,
            ];
        } catch (\Exception $e) {
            Logger::error('PdfService@generateMonthlyServiceControlSheetPdf error: '.$e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Calcula las horas de descanso (inicio y fin) basado en la diferencia entre
     * el tiempo transcurrido y las horas reportadas (estándar Colombia).
     */
    private function calculateRestHours(?Carbon $start, ?Carbon $end, ?Carbon $totalHours): array
    {
        if (! $start || ! $end) {
            return ['inicio' => '', 'fin' => ''];
        }

        $totalElapsedMinutes = $start->diffInMinutes($end);
        if ($end < $start) {
            $totalElapsedMinutes = $start->diffInMinutes($end->copy()->addDay());
        }

        $breakMinutes = 0;

        if ($totalHours) {
            $totalWorkedMinutes = ($totalHours->hour * 60) + $totalHours->minute;
            $breakMinutes = $totalElapsedMinutes - $totalWorkedMinutes;
        }

        // Si no hay diferencia reportada, pero la jornada es mayor a 6 horas,
        // en Colombia por ley debe haber al menos 1 hora de descanso.
        if ($breakMinutes <= 0 && $totalElapsedMinutes > 360) {
            $breakMinutes = 60; // Forzar 1 hora de descanso
        }

        if ($breakMinutes > 0) {
            // Descanso estándar colombiano suele ser al mediodía (12:00 PM)
            $breakStart = Carbon::createFromTime(12, 0);

            // Si el turno empezó después de las 12:00 o en la noche, el descanso se pone a la mitad de la jornada
            if ($start->hour >= 12 || $start->hour < 5 || $end->hour <= 12) {
                $halfElapsed = (int) ($totalElapsedMinutes / 2);
                $halfBreak = (int) ($breakMinutes / 2);
                $breakStart = $start->copy()->addMinutes($halfElapsed - $halfBreak);
            }

            $breakEnd = $breakStart->copy()->addMinutes($breakMinutes);

            return [
                'inicio' => $breakStart->format('H:i'),
                'fin' => $breakEnd->format('H:i'),
            ];
        }

        return ['inicio' => '', 'fin' => ''];
    }

    /**
     * Redimensiona una imagen binaria en memoria si es grande para optimizar el PDF.
     */
    private function optimizeBinaryImage(string $content, int $maxSize = 400): string
    {
        try {
            if (extension_loaded('gd') && strlen($content) > 50 * 1024) {
                $src = @imagecreatefromstring($content);
                if ($src) {
                    $width = imagesx($src);
                    $height = imagesy($src);

                    if ($width > $maxSize || $height > $maxSize) {
                        $ratio = $width / $height;
                        if ($width > $height) {
                            $newWidth = $maxSize;
                            $newHeight = (int) ($maxSize / $ratio);
                        } else {
                            $newHeight = $maxSize;
                            $newWidth = (int) ($maxSize * $ratio);
                        }

                        $dst = imagecreatetruecolor($newWidth, $newHeight);
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                        imagefill($dst, 0, 0, $transparent);

                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                        ob_start();
                        imagepng($dst);
                        $optimizedContent = ob_get_clean();

                        imagedestroy($src);
                        imagedestroy($dst);

                        return $optimizedContent;
                    }
                    imagedestroy($src);
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('Error optimizando imagen binaria para PDF: '.$e->getMessage());
        }

        return $content;
    }

    /**
     * Redimensiona una imagen local y la codifica en base64 para optimizar la generación del PDF.
     */
    private function optimizeAndEncodeImage(string $path, int $maxSize = 400): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        $optimized = $this->optimizeBinaryImage($content, $maxSize);

        return base64_encode($optimized);
    }
}
