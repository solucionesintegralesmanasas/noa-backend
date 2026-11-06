<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Models\CapacityMovement;
use App\Models\ContractSignature;
use App\Models\FleetServiceContract;
use App\Models\Procedure;
use App\Models\RuntTxtBatch;
use App\Models\ServiceProvisionContract;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\BaseService;
use App\Services\Fleet\CapacityMovementService;
use App\Services\Fleet\OperationCardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RadicacionService extends BaseService
{
    public const RUTA_NUEVO = ['CAPACIDAD_TRANSPORTADORA', 'CARTA_DE_ACEPTACION', 'INCLUSION_DE_POLIZAS', 'TARJETA_DE_OPERACION'];
    public const RUTA_CAMBIO = ['INCLUSION_DE_POLIZAS', 'CARTA_DE_ACEPTACION', 'CAPACIDAD_TRANSPORTADORA', 'TARJETA_DE_OPERACION'];
    public const RUTA_RENOVACION = ['RENOVACION_TARJETA'];
    public const RUTA_DESVINCULACION_MUTUO = ['DESVINCULACION_MUTUO'];
    public const RUTA_DESVINCULACION_UNILATERAL = ['DESVINCULACION_UNILATERAL'];

    /** Segundos consecutivos que se prueban antes de fallar por falta de código libre. */
    public const INTENTOS_CODIGO = 120;

    /**
     * Vinculaciones que consumen un cupo de capacidad transportadora al aprobarse.
     * La renovación nunca mueve el contador y las desvinculaciones lo liberan.
     */
    public const VINCULACIONES_CON_CUPO = ['NUEVO_VEHICULO', 'CAMBIO_DE_EMPRESA'];

    protected function getModelInstance(): Model
    {
        return new Procedure;
    }

    public function rutaPara(string $linkType): array
    {
        return match ($linkType) {
            'NUEVO_VEHICULO' => self::RUTA_NUEVO,
            'RENOVACION' => self::RUTA_RENOVACION,
            'DESVINCULACION_MUTUO' => self::RUTA_DESVINCULACION_MUTUO,
            'DESVINCULACION_UNILATERAL' => self::RUTA_DESVINCULACION_UNILATERAL,
            default => self::RUTA_CAMBIO,
        };
    }

    /**
     * Código de trámite autogenerado con formato YYYYMMDDHHmmss. Si ese segundo ya
     * está usado en la empresa, avanza al siguiente hasta encontrar uno libre.
     *
     * @throws ValidationException Si no hay ningún segundo libre en el margen probado.
     */
    public function siguienteCodigo(?string $companyUuid = null): string
    {
        $momento = now();
        $codigo = $momento->format('YmdHis');
        $intentos = 0;

        while ($this->codigoExiste($codigo, $companyUuid)) {
            if (++$intentos > self::INTENTOS_CODIGO) {
                // Nunca se devuelve un código que ya existe: la unicidad se exige
                // en el formulario y aquí no hay margen para inventar otro formato.
                throw ValidationException::withMessages([
                    'procedure_code' => 'No fue posible generar un código de trámite libre para la empresa. Intente de nuevo.',
                ]);
            }
            $momento = $momento->copy()->addSecond();
            $codigo = $momento->format('YmdHis');
        }

        return $codigo;
    }

    /** ¿Ya existe un expediente (padre) con ese código en la empresa? */
    private function codigoExiste(string $codigo, ?string $companyUuid): bool
    {
        $query = Procedure::withoutGlobalScopes()
            ->where('procedure_code', $codigo)
            ->where(function ($w) {
                $w->whereNull('parent_procedure_uuid')->orWhere('parent_procedure_uuid', '');
            });

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query->exists();
    }

    /**
     * Consulta informativa de cupo para un expediente que se va a radicar.
     * No muta nada: el descuento real ocurre al cerrar CAPACIDAD_TRANSPORTADORA.
     *
     * @return array{
     *     requiere_cupo: bool,
     *     enforce: bool,
     *     vehicle_type: string|null,
     *     capacidad_configurada: bool,
     *     cupo_disponible: bool,
     *     authorized_capacity: int,
     *     current_capacity: int,
     *     conveyor_capacity_uuid: string|null
     * }
     */
    public function disponibilidadDeCupo(?string $companyUuid, ?string $vehicleUuid = null, ?string $linkType = null): array
    {
        $requiere = in_array($linkType, self::VINCULACIONES_CON_CUPO, true);
        // Sin scope de empresa a propósito: el vehículo de un CAMBIO_DE_EMPRESA puede
        // pertenecer a la empresa de origen y aun así hay que resolver su tipo.
        $vehiculo = $vehicleUuid
            ? Vehicle::withoutGlobalScopes()->where('uuid', $vehicleUuid)->first()
            : null;
        $tipo = $this->tipoVehiculoParaCapacidad($vehiculo);
        $cupo = $companyUuid
            ? app(CapacityMovementService::class)->cupoDisponible($companyUuid, $tipo)
            : null;

        return [
            'requiere_cupo' => $requiere,
            'enforce' => (bool) config('capacity.enforce', true),
            'vehicle_type' => $tipo,
            'capacidad_configurada' => $cupo !== null,
            'cupo_disponible' => (bool) ($cupo['available'] ?? false),
            'authorized_capacity' => (int) ($cupo['authorized_capacity'] ?? 0),
            'current_capacity' => (int) ($cupo['current_capacity'] ?? 0),
            'conveyor_capacity_uuid' => $cupo['conveyor_capacity_uuid'] ?? null,
        ];
    }

    /**
     * Pre-chequeo estricto al crear un expediente que consumirá cupo. Con el motor
     * en modo estricto (`capacity.enforce`) rechaza con 422 si la empresa no tiene
     * capacidad configurada o si no le quedan cupos. En modo auditoría solo informa.
     *
     * @throws ValidationException
     */
    public function validarCupoAlCrear(array $data): void
    {
        if (! in_array($data['link_type'] ?? null, self::VINCULACIONES_CON_CUPO, true)) {
            return;
        }
        if (! (bool) config('capacity.enforce', true)) {
            return;
        }

        $companyUuid = $data['company_uuid'] ?? null;
        if (empty($companyUuid)) {
            throw ValidationException::withMessages([
                'company_uuid' => 'No se pudo determinar la empresa del expediente.',
            ]);
        }

        $disponibilidad = $this->disponibilidadDeCupo($companyUuid, $data['vehicle_uuid'] ?? null, $data['link_type']);

        if (! $disponibilidad['capacidad_configurada']) {
            throw ValidationException::withMessages([
                'vehicle_uuid' => 'La empresa no tiene capacidad transportadora configurada para el tipo de vehículo.',
            ]);
        }
        if (! $disponibilidad['cupo_disponible']) {
            throw ValidationException::withMessages([
                'vehicle_uuid' => 'La empresa no tiene cupos disponibles de capacidad transportadora.',
            ]);
        }
    }

    public function crearExpediente(array $data): array
    {
        // Cupo primero: un expediente que nace sin cupo no debería llegar a la
        // línea de tiempo (el descuento real ocurre al cerrar la capacidad).
        $this->validarCupoAlCrear($data);

        return $this->transaction(function () use ($data) {
            // Código de trámite: si no viene, se genera automáticamente (YYYYMMDDHHmmss)
            // garantizando que no se repita dentro de la empresa.
            $codigo = $data['procedure_code'] ?? null;
            if (empty($codigo)) {
                $codigo = $this->siguienteCodigo($data['company_uuid'] ?? null);
            }

            $base = [
                'link_type' => $data['link_type'],
                'company_uuid' => $data['company_uuid'] ?? null,
                'origin_company_uuid' => $data['origin_company_uuid'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'] ?? null,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'procedure_code' => $codigo,
                'date_of_creation' => $data['date_of_creation'],
                'city_uuid' => $data['city_uuid'],
                'subject' => $data['subject'] ?? null,
                'paz_y_salvo' => $data['paz_y_salvo'] ?? null,
                'territorial_director_uuid' => $data['territorial_director_uuid'] ?? null,
                'territorial_director_name' => $data['territorial_director_name'] ?? null,
                'territorial_name' => $data['territorial_name'] ?? null,
                'status' => 'EN_PROCESO',
            ];
            $ruta = $this->rutaPara($data['link_type']);
            $padre = Procedure::create(array_merge($base, [
                'procedure_type' => $ruta[0],
                'current_step' => $ruta[0],
                'global_status' => 'EN_PROCESO',
            ]));
            $hijos = [];
            foreach ($ruta as $paso) {
                $hijos[] = Procedure::create(array_merge($base, [
                    'parent_procedure_uuid' => $padre->uuid,
                    'procedure_type' => $paso,
                    'procedure_code' => $codigo.'-'.substr($paso, 0, 3),
                    'status' => $paso === $ruta[0] ? 'EN_PROCESO' : 'RECIBIDO',
                ]));
            }

            return ['expediente' => $padre->fresh(), 'pasos' => $hijos, 'ruta' => $ruta];
        });
    }

    public function puedeAvanzar(Procedure $expediente, string $paso): bool
    {
        $ruta = $this->rutaPara($expediente->link_type ?? 'CAMBIO_DE_EMPRESA');
        $idx = array_search($paso, $ruta, true);
        if ($idx === false || $idx === 0) {
            return true;
        }
        $previo = $ruta[$idx - 1];
        $hijo = Procedure::where('parent_procedure_uuid', $expediente->uuid)
            ->where('procedure_type', $previo)->where('status', 'COMPLETADO')->exists();

        return $hijo;
    }

    public function validarRequisitos(Procedure $hijo): array
    {
        $padre = Procedure::where('uuid', $hijo->parent_procedure_uuid)->first();
        $vehicle = $hijo->vehicle_uuid ? Vehicle::where('uuid', $hijo->vehicle_uuid)->first() : null;
        if ($vehicle && method_exists($vehicle, 'esParticular') && $vehicle->esParticular()) {
            return ['ok' => false, 'mensaje' => 'Vehículo particular no requiere TO ni RCC/RCE.'];
        }
        switch ($hijo->procedure_type) {
            case 'RENOVACION_TARJETA':
                if ($hijo->simit_clear === false || $hijo->simit_clear === 0) {
                    return ['ok' => false, 'mensaje' => 'No se puede renovar con comparendos sin acuerdo de pago en SIMIT.'];
                }
                if (! $vehicle) {
                    return ['ok' => false, 'mensaje' => 'Sin vehículo asociado.'];
                }
                $soat = VehicleDocument::where('vehicle_uuid', $vehicle->uuid)->where('document_type', 'SOAT')
                    ->where('expiry_date', '>=', now()->toDateString())->exists();
                $rtm = VehicleDocument::where('vehicle_uuid', $vehicle->uuid)->where('document_type', 'RTM')
                    ->where('expiry_date', '>=', now()->toDateString())->exists();
                if (! $soat || ! $rtm) {
                    return ['ok' => false, 'mensaje' => 'SOAT y revisión técnico-mecánica deben estar vigentes en RUNT.'];
                }
                $card = \App\Models\OperationCard::where('vehicle_uuid', $vehicle->uuid)->orderByDesc('expiration_date')->first();
                if ($card && now()->diffInDays($card->expiration_date, false) > 60) {
                    return ['ok' => true, 'mensaje' => 'Aún faltan más de 2 meses; se recomienda iniciar 2 meses antes.'];
                }

                return ['ok' => true];
            case 'DESVINCULACION_MUTUO':
            case 'DESVINCULACION_UNILATERAL':
                return ['ok' => true, 'mensaje' => 'Adjunte acta, paz y salvo y tarjeta física para radicar ante el Ministerio.'];
            case 'CAPACIDAD_TRANSPORTADORA':
                $linkType = $padre?->link_type ?? '';
                if (! in_array($linkType, self::VINCULACIONES_CON_CUPO, true)) {
                    return ['ok' => true];
                }

                $companyUuid = $hijo->company_uuid ?? $padre?->company_uuid;
                if (empty($companyUuid)) {
                    return ['ok' => false, 'mensaje' => 'No se pudo determinar la empresa que recibe el vehículo.'];
                }

                // Idempotencia: si el movimiento de este paso ya se registró, no se
                // vuelve a exigir cupo (el motor tampoco lo movería dos veces).
                // Sin scope de empresa: la idempotencia es global por paso de trámite.
                $tipoMovimiento = $linkType === 'NUEVO_VEHICULO'
                    ? CapacityMovement::DESCUENTO_NUEVO
                    : CapacityMovement::DESCUENTO_INGRESO;
                $yaRegistrado = CapacityMovement::withoutGlobalScopes()
                    ->where('procedure_uuid', $hijo->uuid)
                    ->where('movement_type', $tipoMovimiento)
                    ->exists();
                if ($yaRegistrado || ! (bool) config('capacity.enforce', true)) {
                    return ['ok' => true];
                }

                $disponibilidad = $this->disponibilidadDeCupo(
                    $companyUuid,
                    $hijo->vehicle_uuid ?: $padre?->vehicle_uuid,
                    $linkType
                );
                if (! $disponibilidad['capacidad_configurada']) {
                    return ['ok' => false, 'mensaje' => 'La empresa no tiene capacidad transportadora configurada para el tipo de vehículo.'];
                }
                if (! $disponibilidad['cupo_disponible']) {
                    return ['ok' => false, 'mensaje' => 'La empresa no tiene cupos disponibles de capacidad transportadora.'];
                }

                return ['ok' => true];
            case 'INCLUSION_DE_POLIZAS':
                if (! $vehicle) {
                    return ['ok' => false, 'mensaje' => 'Sin vehículo asociado.'];
                }
                if (($padre?->link_type ?? '') === 'NUEVO_VEHICULO' && empty($vehicle->vehicle_license_plate)) {
                    return ['ok' => false, 'mensaje' => 'Falta matrícula (placa) antes de pólizas.'];
                }
                $companyUuid = $hijo->company_uuid ?? $padre?->company_uuid;
                $config = $companyUuid ? \App\Models\SystemConfiguration::where('company_uuid', $companyUuid)->first() : null;
                if ($config && (bool) $config->fuec_use_corporate_policies) {
                    $insurer = $config->rcc_insurer_company ?? $config->corporate_rcc_insurer;
                    $expiry = $config->corporate_rce_expiration ?? $config->corporate_rcc_expiration;
                    if (empty($insurer) || empty($expiry)) {
                        return ['ok' => false, 'mensaje' => 'Configure la aseguradora y el vencimiento de las pólizas corporativas RCC/RCE.'];
                    }
                    if (\Carbon\Carbon::parse($expiry)->lt(now()->toDateString())) {
                        return ['ok' => false, 'mensaje' => 'Las pólizas corporativas RCC/RCE están vencidas.'];
                    }

                    return ['ok' => true, 'mensaje' => 'Vehículo amparado por pólizas corporativas vigentes.'];
                }
                $rcc = VehicleDocument::where('vehicle_uuid', $vehicle->uuid)->where('document_type', 'RCC')
                    ->where('expiry_date', '>=', now()->toDateString())->exists();
                $rce = VehicleDocument::where('vehicle_uuid', $vehicle->uuid)->where('document_type', 'RCE')
                    ->where('expiry_date', '>=', now()->toDateString())->exists();
                if (! $rcc || ! $rce) {
                    return ['ok' => false, 'mensaje' => 'RCC y RCE vigentes son obligatorias.'];
                }

                return ['ok' => true];
            case 'TARJETA_DE_OPERACION':
                $admin = \App\Models\ContractSignature::where('contract_origin', 'ADMIN_FLOTA')
                    ->whereIn('contract_uuid', function ($q) use ($hijo) {
                        $q->select('uuid')->from('fleet_service_contracts')->where('procedure_uuid', $hijo->uuid);
                    })
                    ->where('status', 'FIRMADO')
                    ->exists();
                $prest = \App\Models\ContractSignature::where('contract_origin', 'PRESTACION')
                    ->whereIn('contract_uuid', function ($q) use ($hijo) {
                        $q->select('uuid')->from('service_provision_contracts')->where('procedure_uuid', $hijo->uuid);
                    })
                    ->where('status', 'FIRMADO')
                    ->exists();
                if (! $admin || ! $prest) {
                    return ['ok' => false, 'mensaje' => 'Se exigen ambos contratos firmados (admin flota + prestación).'];
                }

                return ['ok' => true];
            default:
                return ['ok' => true];
        }
    }

    /**
     * Genera el archivo TXT RUNT para radicación de Tarjeta de Operación.
     *
     * Formato de línea (23 campos separados por '|'):
     *   01  municipality_dane_code       Código DANE del municipio de la empresa
     *   02  company_name                 Razón social de la empresa
     *   03  company_address              Dirección de la empresa
     *   04  company_phone                Teléfono de la empresa
     *   05  company_email                Correo electrónico de la empresa
     *   06  legal_representative_fullname Nombre completo del representante legal
     *   07  legal_rep_document_issue_date Fecha expedición documento rep. legal (YYYYMMDD)
     *   08  contract_type                Tipo de contrato ('1' vinculación flotas, '2' prestación)
     *   09  contract_start_date          Fecha inicio contrato (YYYYMMDD)
     *   10  contract_end_date            Fecha fin contrato (YYYYMMDD)
     *   11  vehicle_plate                Placa del vehículo
     *   12  vehicle_class_code           Código clase vehículo (catalog RUNT)
     *   13  action_type                  Tipo de acción: C=Creación, M=Modificación, E=Eliminación
     *   14  owner_nit                    NIT/CC del propietario/tercero
     *   15  owner_name                   Nombre completo del propietario/tercero
     *   16  owner_address                Dirección del propietario/tercero
     *   17  owner_phone                  Teléfono del propietario/tercero
     *   18  owner_email                  Correo del propietario/tercero
     *   19  contract_issue_date          Fecha del contrato (YYYYMMDD)
     *   20  duration                     Duración del contrato en días
     *   21  filler                       Fijo '0050'
     *   22  status                       Fijo 'S' (Sí, radicado)
     *
     * También genera una línea cabecera (3 campos):
     *   01  municipality_dane_code
     *   02  current_date (YYYYMMDD)
     *   03  company_nit (sin dígito de verificación)
     *
     * @return RuntTxtBatch
     */
    public function generarTxt(Procedure $hijoTo, string $origin): RuntTxtBatch
    {
        // Cargar todas las relaciones necesarias
        $toHijo = Procedure::with([
            'vehicle:id,uuid,vehicle_license_plate,vehicle_class_uuid,third_party_uuid',
            'vehicle.vehicleClass:class_code_class,description',
            'vehicle.owners:uuid,vehicle_uuid,third_party_uuid,owner_name,document_number',
            'vehicle.thirdParty:id,uuid,company_name,first_name,last_name,document_number,phone,email,address,municipality_uuid',
            'company:id,uuid,business_name,address,phone,email,document_number,legal_representative_name,legal_representative_last_name,legal_representative_document_issue_date,municipality_uuid',
            'company.municipality:id,uuid,dane_code',
        ])->where('uuid', $hijoTo->uuid)->firstOrFail();

        $company = $toHijo->company;
        $vehicle = $toHijo->vehicle;
        $municipality = $company?->municipality;
        $vehicleClass = $vehicle?->vehicleClass;

        // Determinar datos del tercero/propiedad (propietario)
        // Prioridad: FleetServiceContract.third_party_uuid → Vehicle.third_party → Vehicle.owners[0].third_party
        $ownerThirdPartyUuid = null;
        $contract = null;

        if ($origin === 'ADMIN_FLOTA') {
            $contract = FleetServiceContract::where('procedure_uuid', $hijoTo->uuid)
                ->where(function ($q) use ($vehicle) {
                    if ($vehicle?->uuid) {
                        $q->whereNull('vehicle_uuid')->orWhere('vehicle_uuid', $vehicle->uuid);
                    }
                })
                ->first();
            $ownerThirdPartyUuid = $contract?->third_party_uuid
                ?? $vehicle?->third_party_uuid;
        } else {
            $contract = ServiceProvisionContract::where('procedure_uuid', $hijoTo->uuid)
                ->where(function ($q) use ($vehicle) {
                    if ($vehicle?->uuid) {
                        $q->whereNull('vehicle_uuid')->orWhere('vehicle_uuid', $vehicle->uuid);
                    }
                })
                ->first();
            // Para PRESTACION, buscar también en fleet_service_contracts del mismo procedimiento
            if (!$contract) {
                $contract = FleetServiceContract::where('procedure_uuid', $hijoTo->uuid)->first();
                $ownerThirdPartyUuid = $contract?->third_party_uuid
                    ?? $vehicle?->third_party_uuid;
            } else {
                $ownerThirdPartyUuid = $contract->vehicle?->third_party_uuid
                    ?? $vehicle?->third_party_uuid;
            }
        }

        // Cargar terceros/owners necesarios
        $ownerThirdParty = null;
        if ($ownerThirdPartyUuid) {
            $ownerThirdParty = ThirdParty::with('municipality')
                ->where('uuid', $ownerThirdPartyUuid)->first();
        }

        // Si no hay tercero asociado, buscar en owners
        if (!$ownerThirdParty && $vehicle) {
            $ownerRecord = $vehicle->owners()->first();
            if ($ownerRecord) {
                if ($ownerRecord->third_party_uuid) {
                    $ownerThirdParty = ThirdParty::where('uuid', $ownerRecord->third_party_uuid)->first();
                }
                if (!$ownerThirdParty) {
                    $ownerThirdParty = (object) [
                        'company_name' => null,
                        'first_name' => null,
                        'last_name' => null,
                        'document_number' => $ownerRecord->document_number ?? null,
                        'phone' => null,
                        'email' => null,
                        'address' => null,
                        'municipality' => null,
                    ];
                    // Construir nombre desde owner_name si existe
                    $fullName = trim((string) $ownerRecord->owner_name);
                    if ($fullName !== '') {
                        $ownerThirdParty = (object) array_merge(
                            (array) $ownerThirdParty,
                            ['company_name' => $fullName]
                        );
                    }
                }
            }
        }

        // Campo 01: Código DANE del municipio de la empresa
        $daneCode = str_pad((string) ($municipality?->dane_code ?? ''), 5, '0', STR_PAD_LEFT);
        // Si no tiene dane_code, usar '00000'
        if (empty($daneCode) || $daneCode === '00000') {
            $daneCode = '00000';
        }

        // Fecha actual formateada
        $currentDate = now()->format('Ymd');

        // NIT de la empresa sin dígito de verificación
        $companyNit = (string) $company?->document_number;

        // Línea cabecera
        $headerLine = implode('|', [$daneCode, $currentDate, $companyNit]);

        // --- Construir datos del owner/tercero ---
        $ownerName = '';
        if ($ownerThirdParty) {
            $ownerName = trim((string) ($ownerThirdParty->getFullNameAttribute()
                ?? $ownerThirdParty->company_name
                ?? ''));
        }
        $ownerNit = (string) ($ownerThirdParty?->document_number ?? '');
        $ownerAddress = (string) ($ownerThirdParty?->address ?? '');
        $ownerPhone = (string) ($ownerThirdParty?->phone ?? '');
        $ownerEmail = (string) ($ownerThirdParty?->email ?? '');

        // Representante legal completo
        $legalRepName = trim(
            ((string) ($company?->legal_representative_name ?? ''))
            .' '.
            ((string) ($company?->legal_representative_last_name ?? ''))
        );

        // Fecha emisión documento representante legal
        $legalRepDocIssueDate = '';
        if ($company?->legal_representative_document_issue_date) {
            $legalRepDocIssueDate = $company->legal_representative_document_issue_date
                instanceof \Illuminate\Support\Carbon
                ? $company->legal_representative_document_issue_date->format('Ymd')
                : \Carbon\Carbon::parse($company->legal_representative_document_issue_date)->format('Ymd');
        }

        // --- Determinar tipo de contrato y acción ---
        // Si el contrato encontrado tiene contract_type, usarlo; si no, usar cobertura del ServiceProvisionContract
        $contractType = '';
        $actionType = 'C';
        $contractStartDate = '';
        $contractEndDate = '';
        $contractIssueDate = '';
        $duration = '';

        if ($origin === 'ADMIN_FLOTA' && $contract instanceof FleetServiceContract) {
            $contractType = (string) ($contract->contract_type ?? '1');
            $actionType = (string) ($contract->type_of_action ?? 'C');
            $contractStartDate = $contract->start_date instanceof \Illuminate\Support\Carbon
                ? $contract->start_date->format('Ymd')
                : \Carbon\Carbon::parse($contract->start_date)->format('Ymd');
            $contractEndDate = $contract->end_date instanceof \Illuminate\Support\Carbon
                ? $contract->end_date->format('Ymd')
                : \Carbon\Carbon::parse($contract->end_date)->format('Ymd');
            $contractIssueDate = $contract->issue_date instanceof \Illuminate\Support\Carbon
                ? $contract->issue_date->format('Ymd')
                : \Carbon\Carbon::parse($contract->issue_date)->format('Ymd');
            $duration = (string) ($contract->duration ?? '');
        } elseif ($contract instanceof ServiceProvisionContract) {
            // Prestación: contract_type viene del servicio (cobertura indica tipo)
            $contractType = '2'; // Prestación de servicio
            $actionType = 'C';
            $contractStartDate = $contract->start_date instanceof \Illuminate\Support\Carbon
                ? $contract->start_date->format('Ymd')
                : \Carbon\Carbon::parse($contract->start_date)->format('Ymd');
            $contractEndDate = $contract->end_date instanceof \Illuminate\Support\Carbon
                ? $contract->end_date->format('Ymd')
                : \Carbon\Carbon::parse($contract->end_date)->format('Ymd');
            $contractIssueDate = $contract->issue_date instanceof \Illuminate\Support\Carbon
                ? $contract->issue_date->format('Ymd')
                : \Carbon\Carbon::parse($contract->issue_date)->format('Ymd');
            $duration = (string) ($contract->duration ?? '');
        } elseif ($contract instanceof FleetServiceContract) {
            // Caso fallback: contrato de flota encontrado pero sin type
            $contractType = (string) ($contract->contract_type ?? '1');
            $actionType = (string) ($contract->type_of_action ?? 'C');
            $contractStartDate = $contract->start_date instanceof \Illuminate\Support\Carbon
                ? $contract->start_date->format('Ymd')
                : \Carbon\Carbon::parse($contract->start_date)->format('Ymd');
            $contractEndDate = $contract->end_date instanceof \Illuminate\Support\Carbon
                ? $contract->end_date->format('Ymd')
                : \Carbon\Carbon::parse($contract->end_date)->format('Ymd');
            $contractIssueDate = $contract->issue_date instanceof \Illuminate\Support\Carbon
                ? $contract->issue_date->format('Ymd')
                : \Carbon\Carbon::parse($contract->issue_date)->format('Ymd');
            $duration = (string) ($contract->duration ?? '');
        }

        // --- Datos del vehículo ---
        $plate = (string) ($vehicle?->vehicle_license_plate ?? '');
        $vehicleClassCode = (string) ($vehicleClass?->class_code_class
            ?? $vehicleClass?->description
            ?? '');

        // --- Datos de la empresa ---
        $companyName = (string) ($company?->business_name ?? '');
        $companyAddress = (string) ($company?->address ?? '');
        $companyPhone = (string) ($company?->phone ?? '');
        $companyEmail = (string) ($company?->email ?? '');

        // Construir línea de datos (23 campos)
        $dataLine = implode('|', [
            $daneCode,                         // 01: código DANE municipio
            $companyName,                      // 02: razón social empresa
            $companyAddress,                   // 03: dirección empresa
            $companyPhone,                     // 04: teléfono empresa
            $companyEmail,                     // 05: correo empresa
            $legalRepName,                     // 06: nombre representante legal
            $legalRepDocIssueDate,             // 07: fecha expedición doc. rep. legal
            $contractType,                     // 08: tipo contrato (1=vinculación flotas, 2=prestación)
            $contractStartDate,                // 09: fecha inicio contrato
            $contractEndDate,                  // 10: fecha fin contrato
            $plate,                            // 11: placa vehículo
            $vehicleClassCode,                 // 12: código clase vehículo
            $actionType,                       // 13: tipo acción (C/M/E)
            $ownerNit,                         // 14: NIT/CC propietario
            $ownerName,                        // 15: nombre propietario
            $ownerAddress,                     // 16: dirección propietario
            $ownerPhone,                       // 17: teléfono propietario
            $ownerEmail,                       // 18: correo propietario
            $contractIssueDate,                // 19: fecha contrato
            $duration,                         // 20: duración días
            '0050',                            // 21: código fijo
            'S',                               // 22: estado (S = Sí radicado)
        ]);

        // Secuencia para este procedure + origin
        $seq = (RuntTxtBatch::where('procedure_uuid', $hijoTo->uuid)
            ->where('origin', $origin)
            ->max('sequence') ?? 0) + 1;

        $fullContent = $headerLine."\r\n".$dataLine."\r\n";

        return $this->transaction(fn () => RuntTxtBatch::create([
            'company_uuid' => $toHijo->company_uuid,
            'procedure_uuid' => $hijoTo->uuid,
            'origin' => $origin,
            'contract_uuid' => $contract?->uuid ?? null,
            'sequence' => $seq,
            'content' => $fullContent,
            'content_hash' => hash('sha256', $fullContent),
            'status' => 'GENERADO',
        ]));
    }

    public function listarExpedientes(int $perPage = 15, int $page = 1, string $search = '', ?string $companyUuid = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

        $query = Procedure::with(['vehicle:id,uuid,vehicle_license_plate,line'])
            ->where(function ($q) {
                $q->whereNull('parent_procedure_uuid')
                    ->orWhere('parent_procedure_uuid', '');
            })->orderByDesc('created_at');

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('procedure_code', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        // Los hijos se cargan en una sola consulta para toda la página (N+1 si se
        // pidieran expediente por expediente, ver SPEC-004 y SPEC-007).
        $uuids = $paginator->getCollection()->pluck('uuid')->all();
        $hijosPorPadre = $uuids === []
            ? collect()
            : Procedure::whereIn('parent_procedure_uuid', $uuids)
                ->get(['parent_procedure_uuid', 'procedure_type', 'status'])
                ->groupBy('parent_procedure_uuid');
        $paginator->getCollection()->transform(function ($exp) use ($hijosPorPadre) {
            $ruta = $this->rutaPara($exp->link_type ?? 'CAMBIO_DE_EMPRESA');
            $hijos = $hijosPorPadre->get($exp->uuid, collect());
            $linea = [];
            foreach ($ruta as $paso) {
                $h = $hijos->firstWhere('procedure_type', $paso);
                $linea[] = ['paso' => $paso, 'estado' => $h?->status ?? 'PENDIENTE'];
            }
            $exp->setAttribute('ruta', $ruta);
            $exp->setAttribute('linea_tiempo', $linea);
            $exp->setAttribute('avance', count(array_filter($linea, fn ($p) => $p['estado'] === 'COMPLETADO')).'/'.count($linea));

            return $exp;
        });

        return $paginator;
    }

    public function detalleExpediente(string $uuid): array
    {
        $query = Procedure::with('vehicle')->where('uuid', $uuid);
        $this->aislarPorAfiliado($query);
        $exp = $query->firstOrFail();
        $ruta = $this->rutaPara($exp->link_type ?? 'CAMBIO_DE_EMPRESA');
        $hijos = Procedure::where('parent_procedure_uuid', $exp->uuid)->orderBy('id')->get();
        $linea = [];
        foreach ($ruta as $paso) {
            $h = $hijos->firstWhere('procedure_type', $paso);
            $linea[] = ['paso' => $paso, 'estado' => $h?->status ?? 'PENDIENTE', 'uuid' => $h?->uuid];
        }
        $propietario = null;
        if ($exp->vehicle_uuid) {
            $owner = \App\Models\Owner::with('thirdParty')->where('vehicle_uuid', $exp->vehicle_uuid)->first();
            // El afiliado puede venir del registro de propietario, del tercero del
            // vehículo o del propio expediente, en ese orden.
            $vehiculo = \App\Models\Vehicle::with('thirdParty')->where('uuid', $exp->vehicle_uuid)->first();
            $tercero = $owner?->thirdParty
                ?? $vehiculo?->thirdParty
                ?? ($exp->third_party_uuid ? \App\Models\ThirdParty::where('uuid', $exp->third_party_uuid)->first() : null);

            if ($owner) {
                $propietario = [
                    'nombre' => $owner->owner_name ?: $tercero?->full_name,
                    'documento' => $owner->document_number ?: $tercero?->document_number,
                    'origen' => 'propietario',
                    'telefono' => $tercero?->phone,
                    'correo' => $tercero?->email,
                ];
            } elseif ($tercero) {
                $propietario = [
                    'nombre' => $tercero->full_name,
                    'documento' => $tercero->document_number,
                    'origen' => 'afiliado',
                    'telefono' => $tercero->phone,
                    'correo' => $tercero->email,
                ];
            }
        }
        $toHijo = $hijos->firstWhere('procedure_type', 'TARJETA_DE_OPERACION');
        $contratos = [];
        if ($toHijo) {
            $admin = FleetServiceContract::where('procedure_uuid', $toHijo->uuid)->get(['uuid', 'contract_number']);
            $prest = ServiceProvisionContract::where('procedure_uuid', $toHijo->uuid)->get(['uuid', 'contract_number', 'status']);
            foreach ($admin as $c) {
                $firmado = \App\Models\ContractSignature::where('contract_origin', 'ADMIN_FLOTA')
                    ->where('contract_uuid', $c->uuid)
                    ->where('status', 'FIRMADO')
                    ->exists();
                $contratos[] = ['origen' => 'ADMIN_FLOTA', 'uuid' => $c->uuid, 'numero' => $c->contract_number, 'firmado' => $firmado];
            }
            foreach ($prest as $c) {
                $firmado = \App\Models\ContractSignature::where('contract_origin', 'PRESTACION')
                    ->where('contract_uuid', $c->uuid)
                    ->where('status', 'FIRMADO')
                    ->exists();
                $contratos[] = ['origen' => 'PRESTACION', 'uuid' => $c->uuid, 'numero' => $c->contract_number, 'firmado' => $firmado];
            }
        }

        $documentos = app(RadicacionDocumentoService::class)->documentosPorPaso($exp, $hijos);

        return ['expediente' => $exp, 'ruta' => $ruta, 'linea_tiempo' => $linea, 'pasos' => $hijos, 'propietario' => $propietario, 'contratos' => $contratos, 'documentos' => $documentos];
    }

    /**
     * Campos que se capturan en un paso antes de avanzarlo (renovación,
     * desvinculación y referencia del pago). El estado del pago es informativo:
     * no bloquea el cierre del paso.
     */
    private const CAMPOS_EDITABLES_PASO = [
        'simit_clear',
        'soat_valid',
        'rtm_valid',
        'old_card_number',
        'radicado_number',
        'payment_status',
        'resolution_number',
        'paz_y_salvo',
        'subject',
    ];

    /**
     * Actualiza los datos del paso (o del expediente si se pasa el padre) y
     * devuelve el detalle completo. Un paso completado ya no admite cambios.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function actualizarPaso(string $uuid, array $data): array
    {
        return $this->transaction(function () use ($uuid, $data) {
            $query = Procedure::where('uuid', $uuid)->lockForUpdate();
            $this->aislarPorAfiliado($query);
            $registro = $query->firstOrFail();

            $esHijo = ! empty($registro->parent_procedure_uuid);
            $padre = $esHijo
                ? Procedure::where('uuid', $registro->parent_procedure_uuid)->firstOrFail()
                : $registro;

            if ($esHijo && $registro->status === 'COMPLETADO') {
                abort(422, 'Este paso ya fue completado y no admite cambios.');
            }
            if (! $esHijo && $padre->global_status === 'COMPLETADO') {
                abort(422, 'El expediente ya fue completado y no admite cambios.');
            }

            $cambios = array_intersect_key($data, array_flip(self::CAMPOS_EDITABLES_PASO));

            if ($cambios !== []) {
                $registro->update($cambios);

                // Datos de cabecera: el asunto y el paz y salvo describen al
                // expediente, no a un paso suelto; se replican en todas sus filas.
                $compartidos = array_intersect_key($cambios, array_flip(['subject', 'paz_y_salvo']));
                if ($compartidos !== [] && $esHijo) {
                    Procedure::where('uuid', $padre->uuid)->update($compartidos);
                    Procedure::where('parent_procedure_uuid', $padre->uuid)
                        ->where('uuid', '!=', $registro->uuid)
                        ->update($compartidos);
                }
                if ($compartidos !== [] && ! $esHijo) {
                    Procedure::where('parent_procedure_uuid', $padre->uuid)->update($compartidos);
                }
            }

            return $this->detalleExpediente($padre->uuid);
        });
    }

    public function avanzarPaso(string $hijoUuid): array
    {
        return $this->transaction(function () use ($hijoUuid) {
            // Bloqueo de fila: dos clics simultáneos no deben cerrar el mismo paso dos veces.
            $query = Procedure::where('uuid', $hijoUuid)->lockForUpdate();
            $this->aislarPorAfiliado($query);
            $hijo = $query->firstOrFail();
            if ($hijo->status === 'COMPLETADO') {
                abort(422, 'Este paso ya fue completado.');
            }
            if ($hijo->parent_procedure_uuid) {
                $expediente = Procedure::where('uuid', $hijo->parent_procedure_uuid)->firstOrFail();
                if (! $this->puedeAvanzar($expediente, $hijo->procedure_type)) {
                    abort(422, 'Debe completar el paso anterior antes de avanzar en este.');
                }
            }
            $val = $this->validarRequisitos($hijo);
            if (! $val['ok']) {
                abort(422, $val['mensaje'] ?? 'No cumple los requisitos del paso.');
            }
            $padreUuid = $hijo->parent_procedure_uuid ?: $hijo->uuid;
            $padre = Procedure::where('uuid', $padreUuid)->firstOrFail();
            $hijo->update(['status' => 'COMPLETADO']);

            // Efecto administrativo del paso: mueve el cupo de capacidad transportadora
            // (descuento en capacidad nueva/ingreso; liberación + cancelación de tarjeta
            // en desvinculación). Va dentro de la misma transacción: si el cupo no alcanza,
            // el paso no queda como completado.
            $this->aplicarMovimientosDeCapacidad($padre, $hijo);

            $ruta = $this->rutaPara($padre->link_type ?? 'CAMBIO_DE_EMPRESA');
            $idx = array_search($hijo->procedure_type, $ruta, true);
            $siguiente = ($idx !== false && isset($ruta[$idx + 1])) ? $ruta[$idx + 1] : null;
            if ($siguiente) {
                Procedure::where('parent_procedure_uuid', $padre->uuid)
                    ->where('procedure_type', $siguiente)->update(['status' => 'EN_PROCESO']);
                $padre->update(['current_step' => $siguiente, 'global_status' => 'EN_PROCESO', 'status' => 'EN_PROCESO']);
            } else {
                $padre->update(['current_step' => $hijo->procedure_type, 'global_status' => 'COMPLETADO', 'status' => 'COMPLETADO']);
            }

            return $this->detalleExpediente($padre->uuid);
        });
    }

    /**
     * Aplica el efecto legal de un paso sobre los cupos de capacidad transportadora:
     *
     *  - CAPACIDAD_TRANSPORTADORA de un vehículo NUEVO → descuenta 1 cupo.
     *  - CAPACIDAD_TRANSPORTADORA de un CAMBIO_DE_EMPRESA (ingreso) → descuenta 1 cupo
     *    en la empresa receptora y libera 1 cupo en la empresa de origen.
     *  - DESVINCULACION (mutuo/unilateral) → libera 1 cupo y cancela la tarjeta de operación.
     *
     * La renovación no mueve el contador (no pasa por aquí).
     */
    private function aplicarMovimientosDeCapacidad(Procedure $padre, Procedure $hijo): void
    {
        $companyUuid = $hijo->company_uuid ?: $padre->company_uuid;
        if (empty($companyUuid)) {
            return;
        }

        $vehicleUuid = $hijo->vehicle_uuid ?: $padre->vehicle_uuid;
        // Sin scope de empresa: el vehículo de un CAMBIO_DE_EMPRESA puede seguir
        // registrado en la empresa de origen (que es donde se libera el cupo).
        $vehicle = $vehicleUuid
            ? Vehicle::withoutGlobalScopes()->where('uuid', $vehicleUuid)->first()
            : null;
        $vehicleType = $this->tipoVehiculoParaCapacidad($vehicle);
        $engine = app(CapacityMovementService::class);

        switch ($hijo->procedure_type) {
            case 'CAPACIDAD_TRANSPORTADORA':
                $tipo = match ($padre->link_type) {
                    'NUEVO_VEHICULO' => CapacityMovement::DESCUENTO_NUEVO,
                    'CAMBIO_DE_EMPRESA' => CapacityMovement::DESCUENTO_INGRESO,
                    default => null,
                };
                if ($tipo === null) {
                    return;
                }
                $engine->registrar([
                    'company_uuid' => $companyUuid,
                    'vehicle_uuid' => $vehicleUuid,
                    'vehicle_type' => $vehicleType,
                    'procedure_uuid' => $hijo->uuid,
                    'movement_type' => $tipo,
                    'notes' => "Descuento de cupo al aprobar la capacidad transportadora ({$padre->link_type}).",
                ]);

                // Salida por cambio de empresa (Decreto 348/2015): el cupo deja de
                // pertenecer a la empresa de origen y debe liberarse allí. Idempotente
                // por (paso, LIBERACION_SALIDA); falla cerrada si el origen no tiene
                // capacidad configurada.
                $originUuid = $padre->origin_company_uuid;
                if ($padre->link_type === 'CAMBIO_DE_EMPRESA'
                    && ! empty($originUuid)
                    && $originUuid !== $companyUuid) {
                    $engine->registrar([
                        'company_uuid' => $originUuid,
                        'vehicle_uuid' => $vehicleUuid,
                        'vehicle_type' => $vehicleType,
                        'procedure_uuid' => $hijo->uuid,
                        'movement_type' => CapacityMovement::LIBERACION_SALIDA,
                        'notes' => 'Liberación de cupo en la empresa de origen por cambio de empresa.',
                    ]);
                }
                break;

            case 'DESVINCULACION_MUTUO':
            case 'DESVINCULACION_UNILATERAL':
                $engine->registrar([
                    'company_uuid' => $companyUuid,
                    'vehicle_uuid' => $vehicleUuid,
                    'vehicle_type' => $vehicleType,
                    'procedure_uuid' => $hijo->uuid,
                    'movement_type' => CapacityMovement::LIBERACION_DESVINCULACION,
                    'resolution_number' => $hijo->resolution_number ?: $padre->resolution_number,
                    'notes' => "Liberación de cupo por {$hijo->procedure_type}.",
                ]);
                // Consecuencia directa: la tarjeta de operación se cancela/inhabilita en el RUNT.
                app(OperationCardService::class)->cancelarPorSalida(
                    $vehicleUuid,
                    $companyUuid,
                    $hijo->procedure_type
                );
                break;
        }
    }

    /** Tipo de vehículo con el que se resuelve la capacidad transportadora. */
    private function tipoVehiculoParaCapacidad(?Vehicle $vehicle): ?string
    {
        if (! $vehicle) {
            return null;
        }

        $vehicle->loadMissing('vehicleClass');

        return $vehicle->vehicleClass?->description ?: $vehicle->body_type;
    }
}
