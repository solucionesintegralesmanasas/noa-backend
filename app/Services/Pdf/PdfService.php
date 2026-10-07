<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\Signature;
use App\Models\SystemConfiguration;
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
        private readonly AffiliateAdminChargeService $affiliateAdminChargeService,
        private readonly ServiceControlSheetRows $filas,
        private readonly ServiceControlSheetDaily $diario
    ) {}

    /**
     * Normaliza la base de una URL web para QR/links de validación.
     * Evita el doble esquema (https://https://dominio...) cuando web_page ya lo incluye.
     */
    private function buildWebBaseUrl(?string $webPage = null): string
    {
        $base = ($webPage && trim($webPage) !== '') ? trim($webPage) : (string) config('app.url');

        if (! preg_match('~^https?://~i', $base)) {
            $base = 'https://'.$base;
        }

        return rtrim($base, '/');
    }

    /**
     * Método generateFromView.
     *
     *
     * @return Barryvdh\DomPDF\PDF
     */
    public function generateFromView(string $view, array $data = [], array $options = []): \Barryvdh\DomPDF\PDF
    {
        // Hoja membretada opcional: data URL completa lista para el <img> del layout.
        // Se activa con ['letterhead' => 'data:...;base64,...'] en $data o $options. Null = sin membretado.
        if (! array_key_exists('letterhead', $data)) {
            $data['letterhead'] = $options['letterhead'] ?? null;
        }

        $pdf = Pdf::loadView($view, $data);

        $paper = $options['paper'] ?? 'letter';
        $orientation = $options['orientation'] ?? 'portrait';
        $pdf->setPaper($paper, $orientation);
        $pdf->setOption([
            'dpi' => $options['dpi'] ?? 120,
            'isFontSubsettingEnabled' => $options['font_subsetting'] ?? true,
        ]);

        if (! empty($options['warnings'])) {
            $pdf->setWarnings($options['warnings']);
        } else {
            $pdf->setWarnings((bool) config('app.debug', false));
        }

        // Leyenda de generación vertical a la derecha (incluye número de página).
        if ($options['sello'] ?? true) {
            $this->sellarPaginado($pdf);
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
     * Construye un nombre de archivo legible y consistente para todos los PDF:
     * "Documento - Identificador - Fecha.pdf".
     *
     * Acepta cualquier cantidad de partes (documento, placa, número, fecha, etc.)
     * y las une separadas por " - ". No elimina acentos ni espacios (el wrapper
     * de DomPDF envía un nombre ASCII de respaldo). Solo limpia los caracteres
     * inválidos en sistemas de archivos.
     */
    public static function nombreArchivo(string ...$partes): string
    {
        $partes = array_map('trim', $partes);
        $partes = array_values(array_filter($partes, fn ($p) => $p !== ''));

        $nombre = implode(' - ', $partes);
        $nombre = preg_replace('/[\/\\\\:*?"<>|]+/u', '-', $nombre);
        $nombre = preg_replace('/\s+/u', ' ', $nombre);

        return trim($nombre, " -.").'.pdf';
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
        $data = $this->conMarca($data, $company, 'ficha_tecnica_tercero', $logoModel);

        // Definimos la vista que contendrá el diseño del PDF
        $view = 'pdf.third-parties.technical-sheet';
        $filename = self::nombreArchivo('Ficha Técnica Tercero', $driverData->document_number ?? $thirdPartyUuid);

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

        $view = 'pdf.contracts.business-agreement';
        $placa = $agreement->vehicle ? $agreement->vehicle->vehicle_license_plate : 'Sin Placa';
        $filename = self::nombreArchivo('Convenio de Colaboración Empresarial', $agreement->agreement_internal_id ?? $uuid, $placa);

        $data['data'] = $this->conMarca($data['data'], $agreement->company, 'convenio_colaboracion', $logoModel);
        // La plantilla lee $data['...'] (nivel anidado): se exponen también arriba para el membrete.
        $data['letterhead'] = $data['data']['letterhead'];
        $data['logo_fondo'] = $data['data']['logo_fondo'];
        $data['ocultar_marca'] = $data['data']['ocultar_marca'];

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

            $qrUrl = $this->buildWebBaseUrl($fuec->company->web_page).
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

            $pdf = Pdf::loadView('pdf.fuec', $this->conMarca([
                'fuec' => $fuec,
                'data' => $data,
            ], $fuec->company ?? null, 'fuec', $fuec->company->logo ?? null))
                ->setPaper($paperSize, $orientation)
                ->setOption('defaultFont', 'Arial')
                ->setOption('isRemoteEnabled', false)        // ✅ ya usamos base64
                ->setOption('isHtml5ParserEnabled', true)    // ✅ parser más rápido
                ->setOption('isFontSubsettingEnabled', true); // ✅ fuentes más livianas

            // Nombre del archivo: FUEC - placa - recorrido (origen - destino)
            $placa = trim((string) ($fuec->vehicle->vehicle_license_plate ?? 'SINPLACA'));
            $origen = trim((string) ($fuec->origin_route ?? ''));
            $destino = trim((string) ($fuec->destination_route ?? ''));
            $recorrido = trim($origen.($origen !== '' && $destino !== '' ? ' - ' : '').$destino, ' -');
            $nombre = self::nombreArchivo('FUEC', $placa, $recorrido);

            return [
                'pdf' => $pdf,
                'file_name' => $nombre,
            ];
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Membrete de la empresa listo para el layout (`data:<mime>;base64,...`).
     *
     * Primero la hoja cargada en la configuración (colección LETTERHEAD) y, si no
     * hay, el logo de la empresa. Null cuando no hay ninguno.
     */
    public function letterheadDataUrl(?object $company = null): ?string
    {
        try {
            $media = null;

            $companyUuid = is_object($company) ? ($company->uuid ?? null) : null;
            if ($companyUuid) {
                $config = SystemConfiguration::where('company_uuid', $companyUuid)->first();
                $media = $config?->getFirstMedia('LETTERHEAD');
            }

            if (! $media && is_object($company) && method_exists($company, 'getFirstMedia')) {
                $media = $company->getFirstMedia('logos');
            }

            if (! is_object($media) || ! method_exists($media, 'getPath')) {
                return null;
            }

            $path = $media->getPath();
            if (! $path || ! file_exists($path)) {
                return null;
            }

            $mime = $media->mime_type ?? mime_content_type($path) ?: 'image/png';

            // Solo imágenes: un PDF u otro archivo en el <img> rompe el render y el paginado.
            if (! str_starts_with((string) $mime, 'image/')) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
        } catch (\Throwable $e) {
            Logger::warning('No se pudo resolver el membrete: '.$e->getMessage());

            return null;
        }
    }

    // ========================================================================
    // PRESENTACIÓN POR DOCUMENTO PDF (membrete / logo de fondo / limpio)
    // Configurable por empresa en `system_configuration.pdf_branding`
    // ({ "<clave>": "membrete"|"fondo"|"limpio" }). Sin configurar = fondo.
    // ========================================================================

    public const PDF_MODO_MEMBRETE = 'membrete';

    public const PDF_MODO_FONDO = 'fondo';

    public const PDF_MODO_LIMPIO = 'limpio';

    public const PDF_MODO_DEFECTO = self::PDF_MODO_FONDO;

    public const PDF_CLAVES = [
        'fuec',
        'hoja_vida_vehicular',
        'ficha_tecnica_vehiculo',
        'acta_entrega',
        'mantenimiento',
        'planilla_diaria',
        'planilla_mensual',
        'planilla_filtrada',
        'convenio_colaboracion',
        'recibo_administracion',
        'reporte_vehiculos',
        'ficha_tecnica_tercero',
        'inspeccion_preoperacional',
    ];

    /**
     * Modo de presentación de un documento PDF para una empresa.
     */
    public function modoPdfPara(?string $companyUuid, string $clave): string
    {
        try {
            if ($companyUuid) {
                $config = SystemConfiguration::where('company_uuid', $companyUuid)->first();
                $modo = is_array($config?->pdf_branding ?? null)
                    ? ($config->pdf_branding[$clave] ?? null)
                    : null;
                if (in_array($modo, [self::PDF_MODO_MEMBRETE, self::PDF_MODO_FONDO, self::PDF_MODO_LIMPIO], true)) {
                    return $modo;
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('No se pudo leer pdf_branding: '.$e->getMessage());
        }

        return self::PDF_MODO_DEFECTO;
    }

    /**
     * Resuelve membrete y logo de fondo según el modo configurado.
     *
     * @param  mixed  $logoModel  Modelo de MediaLibrary, ruta o nulo.
     * @return array{modo: string, letterhead: string|null, logo_fondo: string|null}
     */
    public function marcaPdfPara(?object $company, string $clave, mixed $logoModel): array
    {
        $companyUuid = is_object($company) ? ($company->uuid ?? null) : null;
        $modo = $this->modoPdfPara($companyUuid, $clave);

        if ($modo === self::PDF_MODO_MEMBRETE) {
            return [
                'modo' => $modo,
                'letterhead' => $this->letterheadDataUrl($company),
                'logo_fondo' => null,
            ];
        }

        if ($modo === self::PDF_MODO_LIMPIO) {
            return ['modo' => $modo, 'letterhead' => null, 'logo_fondo' => null];
        }

        $fondo = null;
        if ($logoModel) {
            try {
                $base64 = $this->getBase64Parallel(['logo_fondo' => $logoModel], ['logo_fondo' => 1200]);
                $fondo = $base64['logo_fondo'] ?? null;
            } catch (\Throwable $e) {
                Logger::warning('No se pudo cargar el logo de fondo: '.$e->getMessage());
            }
        }

        return ['modo' => $modo, 'letterhead' => null, 'logo_fondo' => $fondo];
    }

    /**
     * Inyecta las claves de presentación (`letterhead`, `logo_fondo`,
     * `ocultar_marca`) en los datos de cualquier vista PDF.
     *
     * @param  array<string, mixed>  $data
     * @param  mixed  $logoModel  Modelo de MediaLibrary, ruta o nulo.
     * @return array<string, mixed>
     */
    public function conMarca(array $data, ?object $company, string $clave, mixed $logoModel): array
    {
        $marca = $this->marcaPdfPara($company, $clave, $logoModel);
        $data['letterhead'] = $marca['letterhead'];
        $data['logo_fondo'] = $marca['logo_fondo'];
        $data['ocultar_marca'] = $marca['modo'] === self::PDF_MODO_LIMPIO;

        return $data;
    }

    /**
     * Estampa la leyenda de generación vertical, centrada en el borde derecho,
     * con la fecha y el número real de página en toda la hoja.
     *
     * DomPDF no soporta counter(page)/counter(pages) en CSS, así que se dibuja en
     * el canvas tras el render (que además soporta rotación). Como el wrapper no
     * re-renderiza, el sello sobrevive al download()/stream()/output() posterior.
     * Coordenadas del canvas: se adaptan solas a vertical u horizontal.
     */
    public function sellarPaginado(\Barryvdh\DomPDF\PDF $pdf): \Barryvdh\DomPDF\PDF
    {
        try {
            $pdf->render();
            $dompdf = $pdf->getDomPDF();
            $canvas = $dompdf->getCanvas();
            $metricas = $dompdf->getFontMetrics();
            $fuente = $metricas->getFont('Arial', 'normal');
            $tamano = 7.5;
            $texto = 'Generado por NOA Transportes | Fecha: '.date('d/m/Y H:i').' | Página {PAGE_NUM} de {PAGE_COUNT}';
            $anchoTexto = $metricas->getTextWidth($texto, $fuente, $tamano);

            // Vertical: el texto arranca abajo del centro y sube por el borde derecho.
            $x = $canvas->get_width() - 14;
            $y = ($canvas->get_height() + $anchoTexto) / 2;

            $canvas->page_text($x, $y, $texto, $fuente, $tamano, [0.47, 0.47, 0.47], 0.0, 0.0, -90);
        } catch (\Throwable $e) {
            Logger::warning('No se pudo sellar el paginado: '.$e->getMessage());
        }

        return $pdf;
    }

    // ✅ Nuevo método paralelo
    /**
     * Método getBase64Parallel.
     *
     * @param array<string, int> $maxSizes Tamaño máximo por clave (default 400).
     *                                     Usar un valor mayor (p. ej. 1200) para imágenes
     *                                     que se muestran grandes, como marcas de agua.
     */
    public function getBase64Parallel(array $fileModels, array $maxSizes = []): array
    {
        $results = [];
        $pendingHttp = [];

        foreach ($fileModels as $key => $fileModel) {
            if (! $fileModel) {
                $results[$key] = null;

                continue;
            }

            $maxSize = $maxSizes[$key] ?? 400;

            if (is_string($fileModel)) {
                if (file_exists($fileModel)) {
                    $results[$key] = $this->optimizeAndEncodeImage($fileModel, $maxSize);
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
                        $results[$key] = $this->optimizeAndEncodeImage($path, $maxSize);

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
                        $results[$key] = $this->optimizeAndEncodeImage($path, $maxSize);

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

            $qrUrl = $this->buildWebBaseUrl($fullCompany->web_page ?? null).'/validar-vehiculo/'.$vehicleUuid;
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

            $pdf = Pdf::loadView('pdf.fleet.vehicle-history', $this->conMarca([
                'data' => $history,
                'images' => $images,
            ], $fullCompany, 'hoja_vida_vehicular', $logoModel));
            $pdf->setPaper('letter', 'portrait');
            $this->sellarPaginado($pdf);

            $fileName = self::nombreArchivo('Hoja de Vida Vehicular', $vehicle['vehicle_license_plate'] ?? $vehicleUuid);

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
            $qrUrl = $this->buildWebBaseUrl($webPage).'/validar-vehiculo/'.$vehicle->uuid;

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

            $pdf = Pdf::loadView('pdf.fleet.technical-sheet-vehicles', $this->conMarca([
                'title' => 'Ficha Técnica Vehículo',
                'vehicle' => $vehicle,
                'data' => $data,
            ], $company ?? null, 'ficha_tecnica_vehiculo', $logoModel));
            $pdf->setPaper('letter', 'portrait');
            $this->sellarPaginado($pdf);

            $fileName = self::nombreArchivo('Ficha Técnica Vehículo', $vehicle->vehicle_license_plate ?? $vehicleUuid);

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

            $pdf = Pdf::loadView('pdf.fleet.handover-record', $this->conMarca($data, $companyModel, 'acta_entrega', $logoModel));
            $pdf->setPaper('letter', 'portrait');
            $this->sellarPaginado($pdf);

            $fileName = self::nombreArchivo('Acta de Entrega', $dataVehicle['vehiculo']['placa'] ?: $vehicleUuid);

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

        $view = 'pdf.contracts.admin-charge';
        $filename = self::nombreArchivo('Constancia de Administración', $charge->charge_internal_id ?? $uuid);

        $data['data'] = $this->conMarca($data['data'], $charge->company, 'recibo_administracion', $logoModel);
        // La plantilla lee $data['...'] (nivel anidado): se exponen también arriba para el membrete.
        $data['letterhead'] = $data['data']['letterhead'];
        $data['logo_fondo'] = $data['data']['logo_fondo'];
        $data['ocultar_marca'] = $data['data']['ocultar_marca'];

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

            $marca = $this->marcaPdfPara($vehicle->company, 'mantenimiento', $logoModel);

            $data = [
                'title' => 'Hoja de Vida – Mantenimiento',
                'letterhead' => $marca['letterhead'],
                'vehicle' => $vehicle,
                'company' => $vehicle->company,
                'maintenances' => $vehicle->maintenance ?? collect(),
                'company_logo_base64' => $base64Images['logo'],
                'company_logo_fondo_base64' => $marca['logo_fondo'] ?? $base64Images['logo'],
            ];

            $pdf = Pdf::loadView('pdf.maintenance.maintenance', $data);
            $pdf->setPaper('letter', 'portrait');
            $this->sellarPaginado($pdf);

            $fileName = self::nombreArchivo('Hoja de Vida Mantenimiento', $vehicle->vehicle_license_plate ?? $vehicleUuid);

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

            // Agregar información del proyecto si está asociada
            $nombreProyecto = $sheet->project ? $sheet->project->project_name : null;

            // Reporte por día: solo la hoja pedida (cada día se imprime por separado).
            $hojasDias = collect([$sheet]);

            // Precargar firmas de todas las hojas del reporte en una sola ronda.
            $hojaIds = $hojasDias->pluck('id')->all();
            if (! in_array($sheet->id, $hojaIds, true)) {
                $hojaIds[] = $sheet->id; // padre como respaldo de firmas
            }
            $padreExterno = null;
            if ($sheet->parent_uuid) {
                $padreExterno = ServiceDeliveryControlSheet::where('uuid', $sheet->parent_uuid)->first();
                if ($padreExterno && ! in_array($padreExterno->id, $hojaIds, true)) {
                    $hojaIds[] = $padreExterno->id;
                }
            }

            $firmasHojaPorId = Signature::query()
                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheet')
                ->whereIn('entity_id', $hojaIds)
                ->orderBy('id', 'asc')
                ->get()
                ->groupBy('entity_id');

            // Firma del coordinador (link público): reemplaza al funcionario en RECIBO Y FIRMA.
            // Respaldo a la firma del funcionario para planillas firmadas antes de esta funcionalidad.
            $firmasCoordPorId = Signature::query()
                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheetCoordinator')
                ->whereIn('entity_id', $hojaIds)
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('entity_id');

            // Firmas del día: propias; si la hoja pedida es hija de un servicio
            // multi-día y no tiene firmas propias, las de su padre externo.
            $firmasDeHoja = function ($dia) use ($firmasHojaPorId, $padreExterno) {
                $grupo = $firmasHojaPorId->get($dia->id) ?? collect();
                if ($grupo->isEmpty() && $padreExterno) {
                    $grupo = $firmasHojaPorId->get($padreExterno->id) ?? collect();
                }

                return $grupo;
            };

            $coordDeHoja = function ($dia) use ($firmasCoordPorId, $padreExterno) {
                $grupo = $firmasCoordPorId->get($dia->id) ?? collect();
                if ($grupo->isEmpty() && $padreExterno) {
                    $grupo = $firmasCoordPorId->get($padreExterno->id) ?? collect();
                }

                return $grupo->first();
            };

            // En disponibilidad no hay funcionario: solo hay 1 firma (conductor).
            // Si se usa get(0) para recibido, la firma del conductor aparece en la casilla del coordinador.
            $archivosFirmas = ['logo' => $logoModel];
            foreach ($hojasDias as $hojaDia) {
                $grupoDia = $firmasDeHoja($hojaDia);
                $coordDia = $coordDeHoja($hojaDia);
                $esDispDia = ! $hojaDia->routes()->where('is_active', true)->exists();
                $archivosFirmas["recibido_{$hojaDia->id}"] = $esDispDia ? $coordDia : ($coordDia ?? $grupoDia->get(0));
                $archivosFirmas["func_{$hojaDia->id}"] = $esDispDia ? null : $grupoDia->get(0);
                $archivosFirmas["cond_{$hojaDia->id}"] = $esDispDia ? $grupoDia->get(0) : $grupoDia->get(1);
            }

            $base64Images = $this->getBase64Parallel($archivosFirmas);

            // Formatear el periodo
            $date = Carbon::parse($sheet->service_date);
            $periodo = $date->translatedFormat('F Y');

            // Firmas por recorrido de todos los días incluidos (cierre individual por recorrido).
            $todosRouteIds = [];
            foreach ($hojasDias as $hojaDia) {
                foreach ($hojaDia->routes as $rt) {
                    if ($rt->is_active) {
                        $todosRouteIds[] = $rt->id;
                    }
                }
            }
            $routeSignatures = collect();
            if (! empty($todosRouteIds)) {
                $routeSignatures = Signature::query()
                    ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheetRoute')
                    ->whereIn('entity_id', $todosRouteIds)
                    ->orderBy('id', 'asc')
                    ->get()
                    ->groupBy('entity_id');
            }

            // Filas unificadas con mensual/filtrado (misma forma, misma resolución
            // de firmas, mismo respaldo). La estrategia de E/S no cambia: una
            // lectura por recorrido, igual que antes.
            $resultadoFilas = $this->filas->armarDias($hojasDias, new ServiceControlSheetRowsOptions(
                proyecto: fn () => $nombreProyecto,
                rutasDe: fn ($hoja) => $hoja->routes->where('is_active', true)->sortBy('order_index')->values(),
                firmaHoja: fn ($hoja) => $base64Images["func_{$hoja->id}"] ?? null,
                firmasRuta: function ($hoja, $route) use ($routeSignatures) {
                    $sigGroup = $routeSignatures->get($route->id) ?? collect();
                    $base64 = $this->getBase64Parallel([
                        'firma_funcionario' => $sigGroup->get(0),
                        'firma_conductor' => $sigGroup->get(1),
                    ]);

                    return ['funcionario' => $base64['firma_funcionario'], 'conductor' => $base64['firma_conductor']];
                },
                numeroEntero: true,
                conRutasDetalle: true,
            ));
            $dias = $resultadoFilas['dias'];
            $rutasDetalle = $resultadoFilas['rutasDetalle'];
            $totalRecorridos = $resultadoFilas['totalRecorridos'];

            // No agregamos filas vacías para que la tabla solo muestre los registros reales

            // Observaciones: peajes + recorridos + vigencia del proyecto
            $obsPartes = [];
            $totalPeajes = (int) $hojasDias->sum('number_of_tolls');
            $valorPeajes = $hojasDias->sum('total_toll_value');
            if ($totalPeajes > 0) {
                $obsPartes[] = "Peajes: {$totalPeajes} | Valor: $ {$valorPeajes}";
            }
            if ($totalRecorridos > 0) {
                $obsPartes[] = "Recorridos del día: {$totalRecorridos}";
            } else {
                $motivos = $hojasDias->map(fn ($h) => $h->motivoDisponibilidad())->filter()->unique();
                $obsPartes[] = 'Estado: DISPONIBILIDAD (vehículo y conductor disponibles, sin recorridos asignados)'
                    .($motivos->isNotEmpty() ? ' | Motivo: '.$motivos->implode(' / ') : '');
            }
            if ($sheet->project && $sheet->project->start_date && $sheet->project->completion_date) {
                $obsPartes[] = 'Vigencia proyecto: '.Carbon::parse($sheet->project->start_date)->format('d/m/Y').' - '.Carbon::parse($sheet->project->completion_date)->format('d/m/Y');
            }

            $logoData = $this->resolveLogoWithMime($logoModel, $base64Images['logo'] ?? null);

            // Firma del conductor para el pie: se toma del último día con firma de
            // conductor; respaldo en cadena porque el cierre a veces solo guarda la
            // firma del funcionario a nivel de hoja.
            $firmaConductorPie = null;
            foreach ($hojasDias as $hojaDia) {
                $cand = $base64Images["cond_{$hojaDia->id}"] ?? null;
                if (! empty($cand)) {
                    $firmaConductorPie = $cand;
                }
            }
            if (empty($firmaConductorPie) && ! empty($rutasDetalle)) {
                foreach ($rutasDetalle as $rdFallback) {
                    if (! empty($rdFallback['firma_conductor'])) {
                        $firmaConductorPie = $rdFallback['firma_conductor'];
                        break;
                    }
                }
            }
            if (empty($firmaConductorPie)) {
                try {
                    $conductorModelo = $sheet->internalControl?->thirdParty ?? null;
                    $firmaConductorModelo = $conductorModelo?->getFirstMedia('signatures')
                        ?? $conductorModelo?->getFirstMedia('signature');
                    if ($firmaConductorModelo) {
                        $extra = $this->getBase64Parallel(['firma_cond_extra' => $firmaConductorModelo]);
                        $firmaConductorPie = $extra['firma_cond_extra'] ?? null;
                    }
                } catch (\Throwable $e) {
                    Logger::warning('No se pudo cargar firma del conductor (respaldo): '.$e->getMessage());
                }
            }

            // Firma de recibido (coordinador): la del último día con firma; respaldo
            // a la firma del funcionario de cualquier fila del reporte.
            $firmaRecibidoPie = null;
            foreach ($hojasDias->reverse()->values() as $hojaDia) {
                $cand = $base64Images["recibido_{$hojaDia->id}"] ?? null;
                if (! empty($cand)) {
                    $firmaRecibidoPie = $cand;
                    break;
                }
            }
            if (empty($firmaRecibidoPie)) {
                foreach ($dias as $d) {
                    if (! empty($d['firma_funcionario'])) {
                        $firmaRecibidoPie = $d['firma_funcionario'];
                        break;
                    }
                }
            }

            // Mapa y bandas del diario en su módulo: dependencias inyectadas,
            // sin app() y sin engordar este servicio.
            [$mapaRecorrido, $mapaFecha, $mapaEsCaptura] = $this->diario->generarMapa($sheet, $hojasDias, $placa);
            [$firmasPendientesPdf, $certificacionesPendientesPdf, $diasConExcepcion] = $this->diario->armarBandas($hojasDias);

            $data = [
                'logo' => $logoData['logo'],
                'logo_mime' => $logoData['mime'],
                'empresa_transportadora' => $empresaTransportadora,
                'periodo' => $periodo,
                'es_historial' => false,
                'placa' => $placa,
                'area' => $area,
                'clase' => $clase,
                'nit' => $nit,
                'empresa' => $empresa,
                'dias' => $dias,
                'observaciones' => implode(' | ', $obsPartes),
                'firma_conductor' => $firmaConductorPie,
                'firma_recibido' => $firmaRecibidoPie,
                'proyecto' => $nombreProyecto,
                'proyecto_vigencia' => ($sheet->project && $sheet->project->start_date && $sheet->project->completion_date)
                    ? Carbon::parse($sheet->project->start_date)->format('d/m/Y').' - '.Carbon::parse($sheet->project->completion_date)->format('d/m/Y')
                    : null,
                'rutas_detalle' => $rutasDetalle, // Se conserva por compatibilidad; la vista ya muestra firmas por fila
                'mapa_recorrido' => $mapaRecorrido['imagen_base64'],
                'mapa_recorrido_mime' => $mapaRecorrido['mime'],
                'mapa_sin_datos' => $mapaRecorrido['sin_datos'],
                'mapa_stats' => $mapaRecorrido['stats'],
                'mapa_fecha' => $mapaFecha,
                'mapa_es_captura' => $mapaEsCaptura,
                'evidencia_incompleta' => $firmasPendientesPdf !== [],
                'firmas_pendientes' => $firmasPendientesPdf,
                'certificaciones_pendientes' => $certificacionesPendientesPdf,
                'dias_con_excepcion' => $diasConExcepcion,
            ];

            $data = $this->conMarca($data, $company, 'planilla_diaria', $logoModel);

            $pdf = Pdf::loadView('pdf.maintenance.service-control-sheet', $data);
            $pdf->setPaper('letter', 'portrait');
            $this->sellarPaginado($pdf);

            $fileName = self::nombreArchivo('Planilla de Control Diaria', $placa ?? 'Vehículo', $date->format('d-m-Y'));

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
    public function generateMonthlyServiceControlSheetPdf(string $companyUuid, string $vehicleUuid, int $year, int $month, bool $soloFinalizados = true): array
    {
        try {
            $vehicle = Vehicle::where('uuid', $vehicleUuid)->firstOrFail();
            $company = $vehicle->company;
            $logoModel = $company ? ($company->getFirstMedia('logos') ?? $company->logo ?? null) : null;
            $empresaTransportadora = $company?->business_name ?? 'TRANSPORTES SIN BARRERAS S.A.S.';

            // Solo días finalizados (cerrados): is_active = false tras el cierre.
            // Si se requiere ver días abiertos, llamar con $soloFinalizados = false.
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
                ->when($soloFinalizados, fn ($q) => $q->where('is_active', false))
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

            // Firmas del coordinador (link público, una sola firma por hoja)
            $allCoordinatorSignatures = Signature::query()
                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheetCoordinator')
                ->whereIn('entity_id', $sheetsIds)
                ->orderBy('id', 'desc')
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

            // Precargar recorridos del mes para generar UNA FILA POR CADA RUTA
            $sheetUuids = $sheets->pluck('uuid')->all();
            $routesBySheet = collect();
            $routeSignaturesByRoute = collect();
            if (! empty($sheetUuids)) {
                $todasRutas = \App\Models\ServiceDeliveryControlSheetRoute::query()
                    ->whereIn('service_delivery_control_sheet_uuid', $sheetUuids)
                    ->where('is_active', true)
                    ->orderBy('order_index')
                    ->get();
                $routesBySheet = $todasRutas->groupBy('service_delivery_control_sheet_uuid');
                $todosRouteIds = $todasRutas->pluck('id')->all();
                if (! empty($todosRouteIds)) {
                    $routeSignaturesByRoute = Signature::query()
                        ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheetRoute')
                        ->whereIn('entity_id', $todosRouteIds)
                        ->orderBy('id', 'asc')
                        ->get()
                        ->groupBy('entity_id');
                }
            }

            // Construir el listado del mes: UNA FILA POR CADA RUTA (recorrido),
            // repitiendo fecha/horas/km globales en cada línea según formato OPE-F-006.
            $dias = [];
            $signatureModelsToLoad = [];

            // Fase 1: juntar los modelos de firma para UNA sola carga en lote.
            // Las filas se arman después, con el builder y el lote ya resuelto.
            $clavesHoja = [];
            $clavesRuta = [];

            foreach ($sheets as $sheet) {
                $sheetDate = Carbon::parse($sheet->service_date);
                $dayNum = $sheetDate->format('d');
                $uniqueKey = "{$dayNum}_{$sheet->id}";
                $clavesHoja[$sheet->id] = $uniqueKey;

                $sheetSignatures = $allSignatures->get($sheet->id, collect());
                $firmaFuncModel = $sheetSignatures->first(); // Funcionario que recibe
                $firmaCondModel = $sheetSignatures->get(1); // Conductor de la hoja

                // Si el día no tiene firmas propias pero pertenece a un servicio multi-día,
                // buscar las firmas del registro padre
                if ($firmaFuncModel === null && $sheet->parent_uuid) {
                    $parentId = $sheets->firstWhere('uuid', $sheet->parent_uuid)?->id;
                    if ($parentId && $allSignatures->has($parentId)) {
                        $firmaFuncModel = $allSignatures->get($parentId)->first();
                        $firmaCondModel = $allSignatures->get($parentId)->get(1);
                    }

                    // Si el padre no está en el mes consultado, buscar sus firmas directamente
                    if ($firmaFuncModel === null) {
                        $parentSheet = ServiceDeliveryControlSheet::where('uuid', $sheet->parent_uuid)->first();
                        if ($parentSheet) {
                            $parentSigs = Signature::query()
                                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheet')
                                ->where('entity_id', $parentSheet->id)
                                ->orderBy('id', 'asc')
                                ->get();

                            if ($parentSigs->isNotEmpty()) {
                                $firmaFuncModel = $parentSigs->first();
                                $firmaCondModel = $parentSigs->get(1);
                            }
                        }
                    }
                }

                if ($firmaFuncModel) {
                    $signatureModelsToLoad["firma_func_{$uniqueKey}"] = $firmaFuncModel;
                }
                if ($firmaCondModel) {
                    $signatureModelsToLoad["firma_cond_{$uniqueKey}"] = $firmaCondModel;
                }

                // Recorridos de esta hoja (desde la precarga, sin N+1)
                $routes = $routesBySheet->get($sheet->uuid, collect());
                foreach ($routes as $route) {
                    $claveRecorrido = "rec_{$uniqueKey}_{$route->id}";
                    $clavesRuta[$route->id] = $claveRecorrido;
                    $grupoFirmas = $routeSignaturesByRoute->get($route->id) ?? collect();
                    if ($grupoFirmas->get(0)) {
                        $signatureModelsToLoad["firma_func_{$claveRecorrido}"] = $grupoFirmas->get(0);
                    }
                    if ($grupoFirmas->get(1)) {
                        $signatureModelsToLoad["firma_conduit_{$claveRecorrido}"] = $grupoFirmas->get(1);
                    }
                }
            }

            // No rellenamos filas vacías adicionales para que solo se vean los registros reales

            // Obtener las firmas para el pie de página mensual.
            // RECIBO Y FIRMA = coordinador por link; respaldo al funcionario de la última hoja firmada.
            $lastSheetWithSignatures = $sheets->reverse()->first(function ($s) use ($allSignatures) {
                return $allSignatures->has($s->id);
            });

            if ($lastSheetWithSignatures) {
                $lastSignatures = $allSignatures->get($lastSheetWithSignatures->id, collect());
                if ($lastSignatures->isNotEmpty()) {
                    $signatureModelsToLoad['firma_recibido_mensual_respaldo'] = $lastSignatures->first();
                    $signatureModelsToLoad['firma_conductor_mensual'] = $lastSignatures->get(1);
                }
            }

            // Coordinador del mes: última hoja con firma de coordinador (link público).
            $lastSheetWithCoordinator = $sheets->reverse()->first(function ($s) use ($allCoordinatorSignatures) {
                return $allCoordinatorSignatures->has($s->id);
            });
            if ($lastSheetWithCoordinator) {
                $coordSigs = $allCoordinatorSignatures->get($lastSheetWithCoordinator->id, collect());
                if ($coordSigs->isNotEmpty()) {
                    $signatureModelsToLoad['firma_recibido_mensual'] = $coordSigs->first();
                }
            }

            // Cargar todas las imágenes en paralelo
            $filesToLoad = array_merge(['logo' => $logoModel], $signatureModelsToLoad);
            $base64Images = $this->getBase64Parallel($filesToLoad);

            // Resolver firmas por hoja y por recorrido desde el lote único.
            // La firma del conductor no se usa en el listado: solo va en el pie.
            $firmaHojaMensual = [];
            foreach ($clavesHoja as $sheetId => $uniqueKey) {
                $firmaHojaMensual[$sheetId] = $base64Images["firma_func_{$uniqueKey}"] ?? null;
            }
            $firmasRutaMensual = [];
            foreach ($clavesRuta as $routeId => $claveRecorrido) {
                $firmasRutaMensual[$routeId] = [
                    'funcionario' => $base64Images["firma_func_{$claveRecorrido}"] ?? null,
                    'conductor' => null,
                ];
            }

            // Filas unificadas con diario/filtrado (misma forma, mismo respaldo).
            $resultadoFilas = $this->filas->armarDias($sheets, new ServiceControlSheetRowsOptions(
                proyecto: fn ($s) => $s->project ? $s->project->project_name : null,
                rutasDe: fn ($s) => $routesBySheet->get($s->uuid, collect()),
                firmaHoja: fn ($s) => $firmaHojaMensual[$s->id] ?? null,
                firmasRuta: fn ($h, $r) => $firmasRutaMensual[$r->id] ?? ['funcionario' => null, 'conductor' => null],
                numeroEntero: false,
                conRutasDetalle: false,
            ));
            $dias = $resultadoFilas['dias'];

            // Construir observaciones consolidadas del mes: peajes + total recorridos
            $totalPeajes = $sheets->sum('number_of_tolls');
            $valorPeajes = $sheets->sum('total_toll_value');
            $obsPartes = [];
            if ($soloFinalizados) {
                $obsPartes[] = 'Solo días FINALIZADOS (cerrados)';
            }
            if ($totalPeajes > 0) {
                $obsPartes[] = "Total Peajes del Mes: {$totalPeajes} | Valor Consolidado: $ {$valorPeajes}";
            }
            $totalRecorridos = $routesBySheet->sum(fn ($grupo) => $grupo->count());
            if ($totalRecorridos > 0) {
                $obsPartes[] = "Total Recorridos del Mes: {$totalRecorridos}";
            }
            $obs = implode(' | ', $obsPartes);

            $proyectoMensual = $sheets->first()?->project;
            $vigenciaMensual = ($proyectoMensual && $proyectoMensual->start_date && $proyectoMensual->completion_date)
                ? Carbon::parse($proyectoMensual->start_date)->format('d/m/Y').' - '.Carbon::parse($proyectoMensual->completion_date)->format('d/m/Y')
                : null;

            $logoDataMensual = $this->resolveLogoWithMime($logoModel, $base64Images['logo'] ?? null);

            // Pie mensual: la firma del conductor a veces solo existe por recorrido o en
            // otra hoja del mes; se busca en cadena para que FIRMA DEL CONDUCTOR QUE ENTREGA no quede vacía.
            $firmaConductorMensual = $base64Images['firma_conductor_mensual'] ?? null;
            if (empty($firmaConductorMensual)) {
                foreach ($base64Images as $clave => $img) {
                    if (str_starts_with($clave, 'firma_cond_') && ! empty($img)) {
                        $firmaConductorMensual = $img;
                        break;
                    }
                }
            }
            if (empty($firmaConductorMensual)) {
                foreach ($base64Images as $clave => $img) {
                    if (str_starts_with($clave, 'firma_conduit_') && ! empty($img)) {
                        $firmaConductorMensual = $img;
                        break;
                    }
                }
            }
            $firmaRecibidoMensual = $base64Images['firma_recibido_mensual'] ?? null;
            if (empty($firmaRecibidoMensual)) {
                $firmaRecibidoMensual = $base64Images['firma_recibido_mensual_respaldo'] ?? null;
            }
            if (empty($firmaRecibidoMensual)) {
                foreach ($dias as $d) {
                    if (! empty($d['firma_funcionario'])) {
                        $firmaRecibidoMensual = $d['firma_funcionario'];
                        break;
                    }
                }
            }

            $data = [
                'logo' => $logoDataMensual['logo'],
                'logo_mime' => $logoDataMensual['mime'],
                'empresa_transportadora' => $empresaTransportadora,
                'periodo' => $periodo . ($soloFinalizados ? ' — SOLO DÍAS FINALIZADOS' : ''),
                'es_historial' => $esHistorial,
                'solo_finalizados' => $soloFinalizados,
                'placa' => $placa,
                'area' => $area,
                'clase' => $clase,
                'nit' => $nit,
                'empresa' => $empresa,
                'dias' => $dias,
                'observaciones' => $obs,
                'firma_conductor' => $firmaConductorMensual,
                'firma_recibido' => $firmaRecibidoMensual,
                'proyecto' => $proyectoMensual ? $proyectoMensual->project_name : null,
                'proyecto_vigencia' => $vigenciaMensual,
            ];

            $data = $this->conMarca($data, $company, 'planilla_mensual', $logoModel);

            $pdf = Pdf::loadView('pdf.maintenance.service-control-sheet', $data);
            $pdf->setPaper('letter', 'portrait');
            $this->sellarPaginado($pdf);

            $fileName = self::nombreArchivo(
                ($esHistorial ? 'Historial de Planilla de Control Mensual' : 'Planilla de Control Mensual'),
                $placa ?? 'Vehículo',
                $startDate->format('d-m-Y'),
            );

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
     * Genera el PDF de Control de Servicio por filtros (rango, vehículo, conductor, día, mensual).
     * Solo días cerrados (is_active=false). Una fila por recorrido en la vista OPE-F-006.
     *
     * Filtros: company_uuid, fecha_desde, fecha_hasta, fecha, vehicle_uuid, third_party_uuid, year, month.
     *
     * @return array{pdf: \Barryvdh\DomPDF\PDF, file_name: string, total_dias: int}
     */
    public function generateFilteredServiceControlSheetPdf(array $filtros): array
    {
        try {
            $companyUuid = $filtros['company_uuid'] ?? null;
            $tipo = $filtros['tipo'] ?? 'rango';

            $query = ServiceDeliveryControlSheet::query()
                ->where('is_active', false)
                ->with(['project', 'routes', 'internalControl.vehicle', 'subcontractedControl'])
                ->orderBy('service_date', 'asc');

            if ($companyUuid) {
                $query->where('company_uuid', $companyUuid);
            }
            if (! empty($filtros['fecha_desde'])) {
                $query->whereDate('service_date', '>=', $filtros['fecha_desde']);
            }
            if (! empty($filtros['fecha_hasta'])) {
                $query->whereDate('service_date', '<=', $filtros['fecha_hasta']);
            }
            if (! empty($filtros['fecha'])) {
                $query->whereDate('service_date', $filtros['fecha']);
            }
            if (! empty($filtros['year'])) {
                $query->whereYear('service_date', (int) $filtros['year']);
            }
            if (! empty($filtros['month'])) {
                $query->whereMonth('service_date', (int) $filtros['month']);
            }
            if (! empty($filtros['vehicle_uuid'])) {
                $vehicleUuid = $filtros['vehicle_uuid'];
                $placaFiltro = Vehicle::where('uuid', $vehicleUuid)->value('vehicle_license_plate');
                $query->where(function ($q) use ($vehicleUuid, $placaFiltro) {
                    $q->whereHas('internalControl', fn ($qq) => $qq->where('vehicle_uuid', $vehicleUuid));
                    if ($placaFiltro) {
                        $q->orWhereHas('subcontractedControl', fn ($qq) => $qq->where('vehicle_license_plate', $placaFiltro));
                    }
                });
            }
            if (! empty($filtros['third_party_uuid'])) {
                $driverUuid = $filtros['third_party_uuid'];
                $query->whereHas('internalControl', fn ($q) => $q->where('third_party_uuid', $driverUuid));
            }

            $sheets = $query->limit(500)->get();
            if ($sheets->isEmpty()) {
                throw new ModelNotFoundException('Sin fechas cerradas para los filtros indicados.');
            }

            // El tope de 500 nunca es silencioso: se cuenta el total con los mismos filtros.
            $totalPlanillas = (clone $query)->count();
            $truncado = $totalPlanillas > 500;

            $company = $sheets->first()->company;
            $logoModel = $company ? ($company->getFirstMedia('logos') ?? $company->logo ?? null) : null;
            $empresaTransportadora = $company?->business_name ?? 'TRANSPORTES SIN BARRERAS S.A.S.';
            $base64Logo = $this->getBase64Parallel(['logo' => $logoModel]);
            $logoData = $this->resolveLogoWithMime($logoModel, $base64Logo['logo'] ?? null);

            $sheetsIds = $sheets->pluck('id')->toArray();
            $sheetUuids = $sheets->pluck('uuid')->all();
            $allSignatures = Signature::query()
                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheet')
                ->whereIn('entity_id', $sheetsIds)->orderBy('id')->get()->groupBy('entity_id');
            $allCoordinator = Signature::query()
                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheetCoordinator')
                ->whereIn('entity_id', $sheetsIds)->orderBy('id', 'desc')->get()->groupBy('entity_id');
            $todasRutas = \App\Models\ServiceDeliveryControlSheetRoute::query()
                ->whereIn('service_delivery_control_sheet_uuid', $sheetUuids)
                ->where('is_active', true)->orderBy('order_index')->get();
            $routesBySheet = $todasRutas->groupBy('service_delivery_control_sheet_uuid');
            $routeSigs = collect();
            if ($todasRutas->isNotEmpty()) {
                $routeSigs = Signature::query()
                    ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheetRoute')
                    ->whereIn('entity_id', $todasRutas->pluck('id')->all())
                    ->orderBy('id')->get()->groupBy('entity_id');
            }
            $sigModels = [];
            foreach ($sheets as $sh) {
                $g = $allSignatures->get($sh->id, collect());
                if ($g->first()) {
                    $sigModels["ff_{$sh->id}"] = $g->first();
                }
                if ($g->get(1)) {
                    $sigModels["fc_{$sh->id}"] = $g->get(1);
                }
                $gc = $allCoordinator->get($sh->id, collect());
                if ($gc->first()) {
                    $sigModels["rc_{$sh->id}"] = $gc->first();
                }
            }
            $base64Sigs = $this->getBase64Parallel(array_merge(['logo2' => null], $sigModels));
            unset($base64Sigs['logo2']);

            // Filas unificadas con diario/mensual: misma forma, mismos respaldos.
            // Diferencia intencionada respecto al código anterior: las filas por
            // recorrido usan hora/km del recorrido (no solo globales), el detalle
            // incluye peajes y la firma resuelve igual que en el diario.
            $resultadoFilas = $this->filas->armarDias($sheets, new ServiceControlSheetRowsOptions(
                proyecto: fn ($s) => $s->project?->project_name,
                rutasDe: fn ($s) => $routesBySheet->get($s->uuid, collect()),
                firmaHoja: fn ($s) => $base64Sigs["ff_{$s->id}"] ?? null,
                firmasRuta: function ($hoja, $route) use ($routeSigs) {
                    $grupoFirmas = $routeSigs->get($route->id, collect());
                    $firmaRec = null;
                    if ($grupoFirmas->first()) {
                        $paralelo = $this->getBase64Parallel(['fr' => $grupoFirmas->first()]);
                        $firmaRec = $paralelo['fr'] ?? null;
                    }

                    return ['funcionario' => $firmaRec, 'conductor' => null];
                },
                numeroEntero: false,
                conRutasDetalle: false,
            ));
            $dias = $resultadoFilas['dias'];

            $etiquetas = [
                'rango' => 'RANGO '.($filtros['fecha_desde'] ?? '').' AL '.($filtros['fecha_hasta'] ?? ''),
                'vehiculo' => 'VEHÍCULO',
                'conductor' => 'CONDUCTOR',
                'dia' => 'DÍA '.($filtros['fecha'] ?? ''),
                'mensual' => 'MENSUAL '.($filtros['year'] ?? '').'-'.($filtros['month'] ?? ''),
            ];
            $firmaCond = collect($base64Sigs)->first(fn ($v, $k) => str_starts_with($k, 'fc_') && ! empty($v));
            $firmaRec = collect($base64Sigs)->first(fn ($v, $k) => str_starts_with($k, 'rc_') && ! empty($v))
                ?? collect(array_column($dias, 'firma_funcionario'))->first(fn ($v) => ! empty($v));

            // El encabezado muestra TODOS los proyectos del rango, no solo el de
            // la primera planilla: un rango puede cruzar varios proyectos.
            $proyectosDistintos = $sheets->map(fn ($s) => $s->project?->project_name)
                ->filter()->unique()->values();
            $proyectoCabecera = $proyectosDistintos->count() === 1
                ? $proyectosDistintos->first()
                : 'VARIOS ('.$proyectosDistintos->count().'): '.$proyectosDistintos->implode(' / ');

            $data = [
                'logo' => $logoData['logo'],
                'logo_mime' => $logoData['mime'],
                'empresa_transportadora' => $empresaTransportadora,
                'periodo' => ($etiquetas[$tipo] ?? 'REPORTE').' — SOLO DÍAS FINALIZADOS',
                'es_historial' => false,
                'solo_finalizados' => true,
                'placa' => $sheets->first()->vehicle_license_plate ?? 'VARIOS',
                'area' => $sheets->last()->official_name_and_surname ?? 'N/A',
                'clase' => 'N/A',
                'nit' => $company?->document_number ?? 'N/A',
                'empresa' => $empresaTransportadora,
                'dias' => $dias,
                'observaciones' => 'Reporte '.strtoupper($tipo).' | Días cerrados: '.$sheets->count().' | Filas: '.count($dias).($truncado ? ' | MOSTRANDO 500 DE '.$totalPlanillas : ''),
                'firma_conductor' => $firmaCond,
                'firma_recibido' => $firmaRec,
                'proyecto' => $proyectoCabecera,
                'proyecto_vigencia' => null,
            ];

            $data = $this->conMarca($data, $company, 'planilla_filtrada', $logoModel);

            $pdf = Pdf::loadView('pdf.maintenance.service-control-sheet', $data);
            $pdf->setPaper('letter', 'portrait');
            $this->sellarPaginado($pdf);

            return [
                'pdf' => $pdf,
                'file_name' => self::nombreArchivo('Reporte de Control '.ucfirst($tipo), now()->format('d-m-Y H:i')),
                'total_dias' => $sheets->count(),
                'total_planillas' => $totalPlanillas,
                'truncado' => $truncado,
            ];
        } catch (\Exception $e) {
            Logger::error('PdfService@generateFilteredServiceControlSheetPdf error: '.$e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Genera el PDF del reporte de vehículos (tabla simple, tope 1000 filas).
     *
     * @param  \Illuminate\Support\Collection<int, array>  $filas
     */
    public function generateVehicleReportPdf($filas, array $filtros = []): array
    {
        $etiquetas = [
            'affiliate' => 'AFILIADO',
            'operation_card' => 'TARJETA DE OPERACIÓN',
            'document' => 'DOCUMENTOS',
            'project' => 'PROYECTO',
            'maintenance' => 'MANTENIMIENTO',
            'driver' => 'CONDUCTOR',
        ];
        $tipo = $filtros['filter_type'] ?? '-';

        // El render tiene presupuesto de 0 consultas (ReporteVehiculosConsultasTest):
        // solo se usa la empresa si ya viene cargada en los filtros, nunca se consulta aquí.
        $reportCompany = ($filtros['company'] ?? null) instanceof object ? $filtros['company'] : null;
        $reportLogoModel = $reportCompany ? ($reportCompany->getFirstMedia('logos') ?? $reportCompany->logo ?? null) : null;

        $pdf = Pdf::loadView('pdf.fleet.vehicle-report', $this->conMarca([
            'filas' => $filas,
            'filtros' => $filtros,
            'filtroDescripcion' => $etiquetas[$tipo] ?? strtoupper((string) $tipo),
        ], $reportCompany, 'reporte_vehiculos', $reportLogoModel));
        $pdf->setPaper('letter', 'portrait');
        $this->sellarPaginado($pdf);

        return [
            'pdf' => $pdf,
            'file_name' => self::nombreArchivo('Reporte de Vehículos', now()->format('d-m-Y H:i')),
        ];
    }

    /**
     * Resuelve el logo de la empresa con respaldo en disco cuando MediaLibrary no tiene imagen.
     * Retorna el base64 ya codificado y el mime para usar en el data-uri del PDF.
     *
     * @param  mixed  $logoModel  Modelo de MediaLibrary, ruta o nulo.
     * @param  string|null  $logoBase64  Base64 ya cargado (puede ser nulo).
     * @return array{logo: string|null, mime: string}
     */
    private function resolveLogoWithMime(mixed $logoModel, ?string $logoBase64): array
    {
        $mime = 'image/png';

        if (! empty($logoBase64)) {
            $detected = $this->detectLogoMime($logoModel);
            if ($detected !== null) {
                $mime = $detected;
            }

            return ['logo' => $logoBase64, 'mime' => $mime];
        }

        $candidatos = [
            public_path('img/logo.png'),
            public_path('img/logo.jpg'),
            public_path('img/logo.jpeg'),
            public_path('images/logo.png'),
            public_path('images/logo.jpg'),
        ];

        foreach ($candidatos as $ruta) {
            if (is_string($ruta) && file_exists($ruta)) {
                $codificado = $this->optimizeAndEncodeImage($ruta);
                if (! empty($codificado)) {
                    $porExtension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
                    if ($porExtension === 'jpg' || $porExtension === 'jpeg') {
                        $mime = 'image/jpeg';
                    }

                    return ['logo' => $codificado, 'mime' => $mime];
                }
            }
        }

        // Respaldo: logo guardado en disco por empresa (storage/app/public/companies/*/LOGO/*)
        // aunque la tabla media esté vacía. Se toma la primera imagen válida encontrada.
        try {
            $base = storage_path('app/public/companies');
            if (is_dir($base)) {
                $archivos = [];
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $archivo) {
                    if (! $archivo->isFile()) {
                        continue;
                    }
                    $rutaLogo = $archivo->getPathname();
                    if (! str_contains(str_replace('\\', '/', $rutaLogo), '/LOGO/')) {
                        continue;
                    }
                    $ext = strtolower($archivo->getExtension());
                    if (in_array($ext, ['png', 'jpg', 'jpeg'], true) && $archivo->getSize() > 1024) {
                        $archivos[] = $rutaLogo;
                    }
                }
                sort($archivos);
                foreach ($archivos as $rutaLogo) {
                    $codificado = $this->optimizeAndEncodeImage($rutaLogo);
                    if (! empty($codificado)) {
                        $ext = strtolower(pathinfo($rutaLogo, PATHINFO_EXTENSION));
                        $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/png';

                        return ['logo' => $codificado, 'mime' => $mime];
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('No se pudo cargar logo en disco por empresa: '.$e->getMessage());
        }

        return ['logo' => null, 'mime' => $mime];
    }

    /**
     * Detecta el mime del logo cuando proviene de MediaLibrary o de una ruta local.
     */
    private function detectLogoMime(mixed $logoModel): ?string
    {
        try {
            if (is_object($logoModel) && method_exists($logoModel, 'getPath')) {
                $ruta = $logoModel->getPath();
                if ($ruta && file_exists($ruta) && function_exists('mime_content_type')) {
                    $detectado = @mime_content_type($ruta);
                    if (is_string($detectado) && str_starts_with($detectado, 'image/')) {
                        return $detectado;
                    }
                }
            }

            if (is_string($logoModel) && file_exists($logoModel) && function_exists('mime_content_type')) {
                $detectado = @mime_content_type($logoModel);
                if (is_string($detectado) && str_starts_with($detectado, 'image/')) {
                    return $detectado;
                }
            }

            if (is_object($logoModel) && isset($logoModel->mime_type) && is_string($logoModel->mime_type)) {
                return $logoModel->mime_type;
            }
        } catch (\Throwable $e) {
            Logger::warning('No se pudo detectar mime del logo: '.$e->getMessage());
        }

        return null;
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
    /**
     * Genera el contrato laboral en PDF.
     */
    public function generateEmploymentContractPdf(string $uuid): \Illuminate\Http\Response
    {
        $contract = \App\Models\EmploymentContract::with(['company.municipality', 'thirdParty'])->where('uuid', $uuid)->firstOrFail();

        $company = $contract->company;
        $thirdParty = $contract->thirdParty;

        // Normalizar los datos
        $data = [
            'worker_name' => $thirdParty->first_name . ' ' . $thirdParty->last_name,
            'worker_doc' => $thirdParty->document_number,
            'worker_address' => $thirdParty->address ?? 'N/A',
            'worker_city' => $thirdParty->expedition_place ?? 'Colombia',
            'employer_name' => $company->business_name,
            'employer_nit' => $company->document_number,
            'employer_rep' => $company->legal_representative_name . ' ' . $company->legal_representative_last_name,
            'employer_address' => $company->address ?? 'N/A',
            'employer_city' => $company->municipality ? $company->municipality->name : 'N/A',
            'position' => $contract->position ?? 'Empleado',
            'salary' => $contract->base_salary ?? 0,
            'salary_type' => $contract->salary_type ?? 'ORDINARIO',
            'start_date' => $contract->start_date ? $contract->start_date->format('Y-m-d') : now()->format('Y-m-d'),
            'end_date' => $contract->end_date ? $contract->end_date->format('Y-m-d') : null,
            'signing_city' => $company->municipality ? $company->municipality->name : 'N/A',
            'signing_date' => $contract->start_date ? $contract->start_date->format('Y-m-d') : now()->format('Y-m-d'),
            'place' => $company->municipality ? $company->municipality->name : 'N/A',
            'work_description' => 'Actividades inherentes al cargo de ' . ($contract->position ?? 'Empleado'),
            'institution' => 'N/A',
            'specialty' => 'N/A',
            'hours' => $contract->working_hours_per_week ?? 48,
            'schedule' => 'Lunes a Sábado',
            'payment_method' => 'transferencia bancaria',
            'pay_period' => 'mensuales',
            'transport' => $contract->transport_subsidy_applies ?? false,
            'probation_days' => 60,
            'duration' => $contract->start_date && $contract->end_date ? $contract->start_date->diffInMonths($contract->end_date) . ' meses' : 'N/A',
        ];

        // Usar la clase de plantillas que creamos
        $contractType = is_string($contract->contract_type) ? $contract->contract_type : $contract->contract_type->value;
        $doc = \App\Support\HumanResources\EmploymentContractTemplates::build($contractType, $data);

        // Obtenemos los logos de la empresa
        $logoModel = $company ? ($company->getFirstMedia('logos') ?? $company->logo ?? null) : null;

        $viewData = [
            'doc' => $doc,
        ];
        
        $viewData = $this->conMarca($viewData, $company, 'contrato_laboral', $logoModel);

        $view = 'pdf.hr.employment-contract';
        $filename = self::nombreArchivo('Contrato Laboral', $thirdParty->document_number ?? $uuid);

        return $this->streamFromView($view, $viewData, $filename, [
            'paper' => 'letter',
            'orientation' => 'portrait',
        ]);
    }
}
