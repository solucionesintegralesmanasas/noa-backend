<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Models\ContractSignature;
use App\Models\Company;
use App\Models\FleetServiceContract;
use App\Models\Owner;
use App\Models\Procedure;
use App\Models\ServiceProvisionContract;
use App\Models\SystemConfiguration;
use App\Models\TerritorialDirector;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Genera los documentos del expediente de radicación a PDF.
 *
 * Cada paso de la línea de tiempo expone uno o varios documentos. Todos se arman
 * con datos de la plataforma (empresa, vehículo, propietario, pólizas, contratos
 * y firmas); ningún texto fijo del documento queda incrustado en el código, de
 * modo que lo que se imprime es siempre lo que está registrado en el sistema.
 */
class RadicacionDocumentoService
{
    /** Documentos disponibles por cada paso de la línea de tiempo. */
    public const DOCUMENTOS_POR_PASO = [
        'CAPACIDAD_TRANSPORTADORA' => [
            ['clave' => 'CARTA_CAPACIDAD', 'etiqueta' => 'Carta de solicitud de capacidad transportadora'],
        ],
        'CARTA_DE_ACEPTACION' => [
            ['clave' => 'CARTA_ACEPTACION', 'etiqueta' => 'Carta de aceptación'],
        ],
        'INCLUSION_DE_POLIZAS' => [
            ['clave' => 'CARTA_INCLUSION_POLIZAS', 'etiqueta' => 'Carta de inclusión de pólizas'],
        ],
        'TARJETA_DE_OPERACION' => [
            ['clave' => 'CONTRATO_VINCULACION', 'etiqueta' => 'Contrato de vinculación por administración de flota', 'origen' => 'ADMIN_FLOTA'],
            ['clave' => 'PAGARE', 'etiqueta' => 'Pagaré y carta de instrucciones', 'origen' => 'ADMIN_FLOTA'],
            ['clave' => 'CONTRATO_PRESTACION', 'etiqueta' => 'Contrato de prestación de servicios', 'origen' => 'PRESTACION'],
        ],
        'RENOVACION_TARJETA' => [],
        'DESVINCULACION_MUTUO' => [],
        'DESVINCULACION_UNILATERAL' => [],
    ];

    /** Clave del documento -> paso de la línea de tiempo al que pertenece. */
    private const PASO_POR_CLAVE = [
        'CARTA_CAPACIDAD' => 'CAPACIDAD_TRANSPORTADORA',
        'CARTA_ACEPTACION' => 'CARTA_DE_ACEPTACION',
        'CARTA_INCLUSION_POLIZAS' => 'INCLUSION_DE_POLIZAS',
        'CONTRATO_VINCULACION' => 'TARJETA_DE_OPERACION',
        'PAGARE' => 'TARJETA_DE_OPERACION',
        'CONTRATO_PRESTACION' => 'TARJETA_DE_OPERACION',
    ];

    /** Clave del documento -> vista Blade que lo compone. */
    private const VISTA_POR_CLAVE = [
        'CARTA_CAPACIDAD' => 'pdf.radicacion.carta-capacidad',
        'CARTA_ACEPTACION' => 'pdf.radicacion.carta-aceptacion',
        'CARTA_INCLUSION_POLIZAS' => 'pdf.radicacion.carta-polizas',
        'CONTRATO_VINCULACION' => 'pdf.radicacion.contrato-vinculacion',
        'PAGARE' => 'pdf.radicacion.pagare',
        'CONTRATO_PRESTACION' => 'pdf.radicacion.contrato-prestacion',
    ];

    /**
     * Documento PDF que corresponde a un contrato, por su origen.
     */
    public static function documentoPara(string $origen): string
    {
        return $origen === 'ADMIN_FLOTA' ? 'CONTRATO_VINCULACION' : 'CONTRATO_PRESTACION';
    }

    /**
     * Documentos que puede producir cada paso, con el estado de su contrato.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function documentosPorPaso(Procedure $padre, Collection $hijos): array
    {
        $ruta = app(RadicacionService::class)->rutaPara($padre->link_type ?? 'CAMBIO_DE_EMPRESA');
        $salida = [];
        foreach ($ruta as $paso) {
            $hijo = $hijos->firstWhere('procedure_type', $paso);
            $disponibles = [];
            foreach (self::DOCUMENTOS_POR_PASO[$paso] ?? [] as $doc) {
                $contrato = isset($doc['origen']) ? $this->contratoDe($hijo, $doc['origen']) : null;
                $disponibles[] = [
                    'clave' => $doc['clave'],
                    'etiqueta' => $doc['etiqueta'],
                    // Los contratos solo se pueden generar si ya existen en la plataforma.
                    'disponible' => ! isset($doc['origen']) || $contrato !== null,
                    'motivo_bloqueo' => isset($doc['origen']) && $contrato === null
                        ? 'Primero registre el contrato en este paso.'
                        : null,
                ];
            }
            $salida[$paso] = $disponibles;
        }

        return $salida;
    }

    /**
     * Localiza el contrato associated a un paso de la línea de tiempo.
     */
    private function contratoDe(?Procedure $hijo, string $origen): ?Model
    {
        if (! $hijo) {
            return null;
        }

        return $origen === 'ADMIN_FLOTA'
            ? FleetServiceContract::where('procedure_uuid', $hijo->uuid)->orderByDesc('issue_date')->first()
            : ServiceProvisionContract::where('procedure_uuid', $hijo->uuid)->orderByDesc('issue_date')->first();
    }

    /**
     * Compone el PDF de un documento del expediente.
     */
    public function generar(string $uuidExpediente, string $clave, bool $descargar = false): Response
    {
        $paso = self::PASO_POR_CLAVE[$clave] ?? null;
        $vista = self::VISTA_POR_CLAVE[$clave] ?? null;
        if ($paso === null || $vista === null) {
            abort(404, 'El documento solicitado no existe en el expediente de radicación.');
        }

        $datos = $this->datos($uuidExpediente, $paso, $clave);

        $pdf = Pdf::loadView($vista, $datos);
        $pdf->setPaper('letter', 'portrait');
        $nombre = $this->nombreArchivo($datos, $clave);

        return $descargar
            ? $pdf->download($nombre)
            : $pdf->stream($nombre);
    }

    /**
     * Reúne todos los datos de plataforma que consumen las plantillas.
     *
     * @return array<string, mixed>
     */
    public function datos(string $uuidExpediente, string $paso, string $clave): array
    {
        $padre = Procedure::with(['company.municipality', 'city', 'territorialDirector', 'vehicle.brand', 'vehicle.vehicleClass', 'vehicle.operationCards'])
            ->where('uuid', $uuidExpediente)->firstOrFail();

        $hijos = Procedure::where('parent_procedure_uuid', $padre->uuid)->orderBy('id')->get();
        $hijo = $hijos->firstWhere('procedure_type', $paso);

        $vehiculo = $padre->vehicle;
        $config = $padre->company_uuid
            ? SystemConfiguration::where('company_uuid', $padre->company_uuid)->first()
            : null;

        $toHijo = $hijos->firstWhere('procedure_type', 'TARJETA_DE_OPERACION');
        $vinculacion = $toHijo ? FleetServiceContract::where('procedure_uuid', $toHijo->uuid)->orderByDesc('issue_date')->first() : null;
        $prestacion = $toHijo ? ServiceProvisionContract::where('procedure_uuid', $toHijo->uuid)->orderByDesc('issue_date')->first() : null;

        $empresa = $this->datosEmpresa($padre->company);
        $propietario = $this->datosPropietario($vehiculo);
        $vinculacion = $this->datosVinculacion($vinculacion);
        $prestacion = $this->datosPrestacion($prestacion);

        // Firma del representante legal: prima la capturada para ese contrato y, si no
        // existe, la que la empresa tiene cargada en su ficha. Así el bloque de
        // "Representante Legal" nunca sale vacío si la empresa ya registró su firma.
        $empresa['firma_representante'] = $this->resolverFirma([
            $vinculacion['firma_empresa'] ?? null,
            $empresa['firma'] ?? null,
        ]);

        // La firma del propietario puede venir del enlace de firma del contrato o de
        // la que tenga registrada el vehículo.
        $firmaPropietario = $propietario['firma'] ?? null;
        if ($vinculacion !== []) {
            $vinculacion['firma_propietario'] = $this->resolverFirma([
                $vinculacion['firma_propietario'] ?? null,
                $firmaPropietario,
            ]);
        }
        if ($prestacion !== []) {
            $prestacion['firma_propietario'] = $this->resolverFirma([
                $prestacion['firma_propietario'] ?? null,
                $firmaPropietario,
            ]);
            // En la prestación, el contratante es la empresa: su firma es la del
            // representante legal, o la del cliente si ese la firmó por enlace.
            $prestacion['firma_empresa'] = $this->resolverFirma([
                $prestacion['firma_cliente'] ?? null,
                $empresa['firma_representante'],
            ]);
        }

        $datos = [
            'expediente' => $this->datosExpediente($padre, $hijo),
            'empresa' => $empresa,
            'destinatario' => $this->datosDestinatario($padre),
            'vehiculo' => $this->datosVehiculo($vehiculo),
            'propietario' => $propietario,
            'polizas' => $this->datosPolizas($config, $vehiculo),
            'vinculacion' => $vinculacion,
            'prestacion' => $prestacion,
            'imagenes' => $this->datosImagenes($config, $empresa['logo']['imagen'] ?? null),
        ];

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosExpediente(Procedure $padre, ?Procedure $hijo): array
    {
        return [
            'codigo' => $padre->procedure_code,
            'tipo_tramite' => $padre->link_type,
            'fecha_radicacion' => $padre->date_of_creation ? $this->fecha($padre->date_of_creation) : null,
            'asunto' => $padre->subject,
            'ciudad' => $padre->city?->name,
            'paso' => $hijo?->procedure_type,
            'estado_paso' => $hijo?->status,
            'estado_global' => $padre->global_status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosEmpresa(?Company $empresa): array
    {
        return [
            'razon_social' => $empresa?->business_name,
            'nombre_comercial' => $empresa?->trade_name,
            'nit' => $empresa?->document_number,
            'representante' => $this->nombreRepresentante($empresa?->legal_representative_name, $empresa?->legal_representative_last_name),
            'documento_representante' => $empresa?->legal_representative_document_number,
            'ciudad' => $empresa?->municipality?->name,
            'firma' => $this->imagenDeMedia($empresa?->signature, 480),
            'logo' => $this->imagenDeMedia($empresa?->logo, 420),
        ];
    }

    /**
     * Convierte un archivo de MediaLibrary en un data URL utilizable en el PDF.
     *
     * Lo que se guarda no siempre es PNG (la firma de la empresa, por ejemplo, es
     * JPEG y pesa cientos de KB), así que se reescala antes de incrustar: sin esto
     * el PDF puede pesar megabytes por una simple firma.
     *
     * @return array{imagen: ?string, texto: ?string}
     */
    private function imagenDeMedia(?object $media, int $maxAncho = 520): array
    {
        if (! $media || ! method_exists($media, 'getPath')) {
            return ['imagen' => null, 'texto' => null];
        }

        try {
            $ruta = $media->getPath();
        } catch (\Throwable) {
            return ['imagen' => null, 'texto' => null];
        }

        if (! $ruta || ! is_file($ruta)) {
            return ['imagen' => null, 'texto' => null];
        }

        $contenido = @file_get_contents($ruta);
        if ($contenido === false) {
            return ['imagen' => null, 'texto' => null];
        }

        [$contenido, $mime] = $this->reescalar($contenido, $mime = $media->mime_type ?? null, $maxAncho);

        if (! $mime) {
            return ['imagen' => null, 'texto' => null];
        }

        return ['imagen' => 'data:'.$mime.';base64,'.base64_encode($contenido), 'texto' => null];
    }

    /**
     * Reescala una imagen para que no pese de más en el PDF.
     *
     * @return array{0: string, 1: ?string} contenido y mime resultante
     */
    private function reescalar(string $contenido, ?string $mime, int $maxAncho): array
    {
        if (! str_starts_with((string) $mime, 'image/')) {
            $detectado = @mime_content_type('data://image;base64,'.base64_encode($contenido));
            $mime = is_string($detectado) && str_starts_with($detectado, 'image/') ? $detectado : null;
        }

        // Solo tiene sentido redimensionar si la imagen es grande y GD está disponible.
        if (! $mime || strlen($contenido) < 40 * 1024 || ! extension_loaded('gd')) {
            return [$contenido, $mime];
        }

        try {
            $origen = @imagecreatefromstring($contenido);
            if (! $origen) {
                return [$contenido, $mime];
            }

            $ancho = imagesx($origen);
            $alto = imagesy($origen);

            if ($ancho <= $maxAncho) {
                imagedestroy($origen);

                return [$contenido, $mime];
            }

            $nuevoAncho = $maxAncho;
            $nuevoAlto = max(1, (int) round($alto * ($maxAncho / $ancho)));

            $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
            imagealphablending($destino, false);
            imagesavealpha($destino, true);
            imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 255, 255, 255, 127));
            imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

            // Una foto de firma escaneada pesa muchísimo como PNG; solo se conserva
            // el PNG cuando la imagen tiene transparencia real (trazo del canvas).
            $salidaMime = $this->tieneTransparencia($origen) ? 'image/png' : 'image/jpeg';

            ob_start();
            if ($salidaMime === 'image/png') {
                // El segundo parámetro de imagepng() es el archivo de salida; la
                // compresión va en el tercero. Pasarlo en el segundo lanza TypeError.
                imagepng($destino, null, 6);
            } else {
                imagejpeg($destino, null, 82);
            }
            $optimizado = ob_get_clean();

            imagedestroy($origen);
            imagedestroy($destino);

            return [is_string($optimizado) && $optimizado !== '' ? $optimizado : $contenido, $salidaMime];
        } catch (\Throwable $e) {
            // No debe romper el PDF: se usa la imagen original sin reescalar.
            Log::warning('No se pudo reescalar una imagen para el PDF de radicación: '.$e->getMessage());

            return [$contenido, $mime];
        }
    }

    /** Detecta si la imagen tiene píxeles con canal alfa semitransparente. */
    private function tieneTransparencia(\GdImage $imagen): bool
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        // El cast debe rodear la división completa: (int) a / b divide después del
        // cast y devuelve float, que imagecolorat() no acepta.
        $paso = max(1, (int) round(max($ancho, $alto) / 40));

        for ($y = 0; $y < $alto; $y += $paso) {
            for ($x = 0; $x < $ancho; $x += $paso) {
                if (imagecolorat($imagen, $x, $y) >> 24 !== 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Elige la primera firma que realmente tenga contenido.
     *
     * @param  array<int, array<string, mixed>|null>  $candidatas
     * @return array{imagen: ?string, texto: ?string}
     */
    private function resolverFirma(array $candidatas): array
    {
        foreach ($candidatas as $firma) {
            if (! empty($firma['imagen']) || ! empty($firma['texto'])) {
                return ['imagen' => $firma['imagen'] ?? null, 'texto' => $firma['texto'] ?? null];
            }
        }

        return ['imagen' => null, 'texto' => null];
    }

    /**
     * A quién va dirigida la carta: el Ministerio, a través de la dirección
     * territorial del expediente.
     *
     * Nota: `territorial_directors.name` guarda el departamento y
     * `territorial_director` la denominación completa de la dependencia. La
     * plataforma no almacena el nombre personal del director, así que la carta
     * se dirige a la dependencia y no a una persona.
     *
     * @return array<string, mixed>
     */
    private function datosDestinatario(Procedure $padre): array
    {
        $dependencia = $padre->territorialDirector?->territorial_director;

        return [
            'entidad' => 'Ministerio de Transporte',
            'dependencia' => $dependencia,
            'departamento' => $padre->territorialDirector?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosVehiculo(?Vehicle $vehiculo): array
    {
        if (! $vehiculo) {
            return [];
        }

        return [
            'placa' => $vehiculo->vehicle_license_plate,
            'marca' => $vehiculo->brand?->description,
            'linea' => $vehiculo->line,
            'modelo' => $vehiculo->model,
            'servicio' => $vehiculo->type_of_service,
            'clase' => $vehiculo->vehicleClass?->description,
            'cilindraje' => $vehiculo->engine_displacement,
            'carroceria' => $vehiculo->body_type,
            'numero_motor' => $vehiculo->engine_number,
            'numero_chasis' => $vehiculo->chassis_number,
            'vin' => $vehiculo->vin_number,
            'pasajeros' => $vehiculo->passenger_capacity,
            'carga' => $vehiculo->load_capacity,
            'combustible' => $vehiculo->fuel_type,
            'es_particular' => $vehiculo->esParticular(),
            'tarjeta_operacion' => $vehiculo->operationCards?->sortByDesc('expiration_date')?->first()?->operating_card_number,
        ];
    }

    /**
     * Propietario o afiliado del vehículo, que es quien firma la vinculación.
     *
     * @return array<string, mixed>
     */
    private function datosPropietario(?Vehicle $vehiculo): array
    {
        if (! $vehiculo) {
            return [];
        }
        $owner = Owner::with('thirdParty')->where('vehicle_uuid', $vehiculo->uuid)->first();
        $tercero = $owner?->thirdParty ?? $vehiculo->thirdParty;

        return [
            'nombre' => $owner?->owner_name ?: $tercero?->full_name,
            'documento' => $owner?->document_number ?: $tercero?->document_number,
            'es_propietario' => $owner !== null,
            'firma' => $this->firmaDe('PROPIETARIO', $vehiculo->uuid),
        ];
    }

    /**
 * Pólizas de la empresa.
 *
 * La carta es sobre un vehículo concreto, así que se prefiere la póliza que
 * está registrada en ese vehículo (`vehicle_documents`). Las pólizas corporativas
 * de la configuración son el respaldo para cuando el vehículo no tenga RCC/RCE
 * propias, que es el caso en que se usan para toda la flota.
 *
 * @return array<string, mixed>
     */
    private function datosPolizas(?SystemConfiguration $config, ?Vehicle $vehiculo): array
    {
        $documento = fn (string $tipo) => $vehiculo?->vehicleDocuments?->firstWhere('document_type', $tipo);

        $corporativa = [
            'rcc' => ['aseguradora' => $config?->corporate_rcc_insurer ?? $config?->rcc_insurer_company, 'numero' => $config?->rcc_policy_number],
            'rce' => ['aseguradora' => $config?->rce_insurer_company, 'numero' => $config?->rce_policy_number],
        ];
        $delVehiculo = [
            'rcc' => ['aseguradora' => $documento('RCC')?->issuing_entity, 'numero' => $documento('RCC')?->policy_number],
            'rce' => ['aseguradora' => $documento('RCE')?->issuing_entity, 'numero' => $documento('RCE')?->policy_number],
        ];

        $resolver = function (string $ramo) use ($corporativa, $delVehiculo): array {
            $propia = $delVehiculo[$ramo];

            return ! empty($propia['aseguradora']) && ! empty($propia['numero'])
                ? $propia
                : $corporativa[$ramo];
        };

        $rcc = $resolver('rcc');
        $rce = $resolver('rce');
        $delVehiculoUsadas = $rcc['numero'] === $delVehiculo['rcc']['numero'] && ! empty($delVehiculo['rcc']['numero']);

        return [
            'del_vehiculo' => $delVehiculoUsadas,
            'rcc_numero' => $rcc['numero'],
            'rcc_aseguradora' => $rcc['aseguradora'],
            'rce_numero' => $rce['numero'],
            'rce_aseguradora' => $rce['aseguradora'],
            'vigencia_desde' => $this->fecha($vehiculo?->operationCards?->sortByDesc('expiration_date')?->first()?->expiration_date),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosVinculacion(?FleetServiceContract $contrato): array
    {
        if (! $contrato) {
            return [];
        }

        return [
            'numero' => $contrato->contract_number,
            'tipo' => $contrato->contract_type,
            'tipo_accion' => $contrato->type_of_action,
            'fecha_emision' => $this->fecha($contrato->issue_date),
            'fecha_inicio' => $this->fecha($contrato->start_date),
            'fecha_fin' => $this->fecha($contrato->end_date),
            'duracion_dias' => $contrato->duration,
            'valor_tasacion' => $contrato->valuation_amount,
            'firma_empresa' => $this->firmaDe('REP_LEGAL', $contrato->uuid, 'ADMIN_FLOTA'),
            'firma_propietario' => $this->firmaDe('PROPIETARIO', $contrato->uuid, 'ADMIN_FLOTA'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPrestacion(?ServiceProvisionContract $contrato): array
    {
        if (! $contrato) {
            return [];
        }

        return [
            'numero' => $contrato->contract_number,
            'cobertura' => $contrato->coverage,
            'objeto' => $contrato->object_description,
            'fecha_emision' => $this->fecha($contrato->issue_date),
            'fecha_inicio' => $this->fecha($contrato->start_date),
            'fecha_fin' => $this->fecha($contrato->end_date),
            'duracion_dias' => $contrato->duration,
            'valor_tasacion' => $contrato->valuation_amount,
            'firma_propietario' => $this->firmaDe('PROPIETARIO', $contrato->uuid, 'PRESTACION'),
            'firma_cliente' => $this->firmaDe('CLIENTE', $contrato->uuid, 'PRESTACION'),
        ];
    }

    /**
     * Trazo de firma ya capturado, listo para incrustar en la plantilla.
     *
     * Se devuelven `imagen` y `texto` por separado porque la firma puede venir
     * como trazo (base64, tomada en pantalla) o como nombre escrito (firma por
     * enlace); la plantilla decide cómo mostrarla.
     *
     * @return array{imagen: ?string, texto: ?string}
     */
    private function firmaDe(string $rol, string $contratoUuid, ?string $origen = null): array
    {
        $consulta = ContractSignature::where('contract_uuid', $contratoUuid)
            ->where('signer_role', $rol)
            ->where('status', 'FIRMADO');
        if ($origen) {
            $consulta->where('contract_origin', $origen);
        }
        $firma = $consulta->orderByDesc('signed_at')->first();

        return ContractSignatureService::firmaParaPdf($firma?->signature_data);
    }

    /**
     * Logotipos de la plataforma y del Ministerio, listos para el `src` de un `<img>`.
     *
     * Todos se devuelven como data URL completo (`data:<mime>;base64,...`) para que
     * las plantillas no tengan que suponer el tipo. El membrete de la empresa es
     * primero el de la configuración (LETTERHEAD) y, si no hay, su logo.
     *
     * @return array<string, ?string>
     */
    private function datosImagenes(?SystemConfiguration $config, ?string $logoEmpresa = null): array
    {
        // Se leen como MediaLibrary para pasar por el mismo reescalador: el
        // membrete es la imagen más pesada del encabezado.
        $deConfig = function (string $coleccion, int $maxAncho) use ($config): ?string {
            try {
                $media = $config?->getFirstMedia($coleccion);
            } catch (\Throwable) {
                return null;
            }

            return $this->imagenDeMedia($media, $maxAncho)['imagen'];
        };

        return [
            'ministerio' => $deConfig('MINISTRY_LOGO', 420)
                ?? $this->imagenDeMedia($this->archivoComoMedia(public_path('img/transporte.png')), 420)['imagen'],
            'superintendencia' => $deConfig('SUPER_LOGO', 300)
                ?? $this->imagenDeMedia($this->archivoComoMedia(public_path('img/super2.png')), 300)['imagen'],
            'empresa' => $deConfig('LETTERHEAD', 420) ?? $logoEmpresa,
        ];
    }

    /** Envuelve un archivo local como si fuera un Media, para reusar imagenDeMedia(). */
    private function archivoComoMedia(string $ruta): ?object
    {
        return is_file($ruta) ? new class($ruta)
        {
            public function __construct(private string $ruta) {}

            public function getPath(): string
            {
                return $this->ruta;
            }

            public function __get(string $nombre): mixed
            {
                return $nombre === 'mime_type' ? @mime_content_type($this->ruta) : null;
            }
        } : null;
    }

    private function nombreRepresentante(?string $nombres, ?string $apellidos = null): ?string
    {
        $nombre = trim(implode(' ', array_filter([$nombres, $apellidos])));

        return $nombre === '' ? null : $nombre;
    }

    private function fecha(mixed $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }
        try {
            return Carbon::parse($valor)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function nombreArchivo(array $datos, string $clave): string
    {
        $etiquetas = collect(self::DOCUMENTOS_POR_PASO)
            ->flatten(1)
            ->firstWhere('clave', $clave);
        $base = Str::slug($etiquetas['etiqueta'] ?? $clave, '_');
        $sufijos = [
            'CARTA_CAPACIDAD' => $datos['vehiculo']['placa'] ?? null,
            'CARTA_INCLUSION_POLIZAS' => $datos['vehiculo']['placa'] ?? null,
            'CARTA_ACEPTACION' => $datos['vehiculo']['placa'] ?? null,
            'CONTRATO_VINCULACION' => $datos['vinculacion']['numero'] ?? null,
            'PAGARE' => $datos['vinculacion']['numero'] ?? null,
            'CONTRATO_PRESTACION' => $datos['prestacion']['numero'] ?? null,
        ];
        $sufijo = $sufijos[$clave] ?? null;

        return $base.($sufijo ? '_'.$sufijo : '').'.pdf';
    }
}