<?php

declare(strict_types=1);

namespace App\Services\ContractExtraction;

use App\Models\Contractor;
use App\Models\Fuec;
use App\Models\ItWasConsecutive;
use App\Services\Administrations\EnablingResolutionService;
use App\Services\BaseService;
use App\Services\Fleet\VehicleDocumentService;
use App\Utils\Logger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de negocio altamente detallado para la gestión integral de FUEC.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de FUEC en el dominio del negocio.
 * Se encarga de aplicar las políticas corporativas asociadas y mantener la integridad referencial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class FuecService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = [
        'number_fuec',
        'request_number',
        'contract_number_display',
        'origin_route',
        'destination_route',
    ];

    public function __construct(
        private readonly ContractorService $contractorService,
        private readonly FuecPassengerService $fuecPassengerService,
        private readonly EnablingResolutionService $enablingResolutionService,

    ) {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new Fuec;
    }

    /**
     * Método query.
     */
    public function query(): Builder
    {
        return parent::query()->with(['passengers', 'contractor']);
    }

    /**
     * Método getAllFuecsWithPagination.
     */
    public function getAllFuecsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = parent::query();

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->where(function ($q) use ($thirdPartyUuid) {
                $q->whereHas('vehicle', function ($vq) use ($thirdPartyUuid) {
                    $vq->where('third_party_uuid', $thirdPartyUuid);
                })
                    ->orWhere('main_conductor_uuid', $thirdPartyUuid)
                    ->orWhere('secondary_conductor_uuid', $thirdPartyUuid)
                    ->orWhere('tertiary_conductor_uuid', $thirdPartyUuid);
            });
        }

        if (! empty($search) && ! empty($this->searchableFields)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }
            });
        }

        $columns = [
            'uuid',
            'number_fuec',
            'request_number',
            'contract_number_display',
            'issue_date',
            'origin_route',
            'destination_route',
            'status',
        ];

        $paginator = $query->paginate($perPage, $columns, 'page', $page);

        // Optimización de payload in-place
        $paginator->getCollection()->transform(fn ($fuec) => [
            'uuid' => $fuec->uuid,
            'number_fuec' => $fuec->number_fuec,
            'request_number' => $fuec->request_number,
            'contract_number_display' => $fuec->contract_number_display,
            'issue_date' => $fuec->issue_date,
            'origin_route' => $fuec->origin_route,
            'destination_route' => $fuec->destination_route,
            'status' => $fuec->status,
        ]);

        return $paginator;
    }

    /**
     * Método getAllFuecs.
     */
    public function getAllFuecs(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query();

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->where(function ($q) use ($thirdPartyUuid) {
                $q->whereHas('vehicle', function ($vq) use ($thirdPartyUuid) {
                    $vq->where('third_party_uuid', $thirdPartyUuid);
                })
                    ->orWhere('main_conductor_uuid', $thirdPartyUuid)
                    ->orWhere('secondary_conductor_uuid', $thirdPartyUuid)
                    ->orWhere('tertiary_conductor_uuid', $thirdPartyUuid);
            });
        }

        return $query->get();
    }

    /**
     * Método getFuecByUuid.
     */
    public function getFuecByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método validatePublicFuec.
     */
    public function validatePublicFuec(string $code): ?Model
    {
        $record = $this->query()
            ->where('verification_code', $code)
            ->orWhere('uuid', $code)
            ->first();

        if ($record) {
            $record->load([
                'company.media',
                'contractor',
                'vehicle.brand',
                'vehicle.vehicleClass',
                'vehicle.operationCards',
                'vehicle.businessCollaborationAgreements',
                'objectsContract',
                'mainConductor.driverLicenses',
                'secondaryConductor.driverLicenses',
                'tertiaryConductor.driverLicenses',
                'passengers.typeOfDocument',
            ]);
        }

        return $record;
    }

    /**
     * Método createFuec.
     */
    public function createFuec(array $data): Model
    {
        // Obtener la configuración de sistema de la empresa actual
        $config = DB::table('system_configuration')
            ->where('company_uuid', $data['company_uuid'])
            ->first();

        // 1. Validar que exista una inspección vehicular preoperacional para el vehículo en la fecha actual (hoy) si está configurado
        $requireInspections = $config ? (bool) $config->fuec_require_daily_inspections : false;
        if ($requireInspections) {
            $today = Carbon::today();
            $inspectionExists = DB::table('vehicle_inspections')
                ->where('vehicle_uuid', $data['vehicle_uuid'])
                ->whereDate('inspection_date', $today)
                ->exists();

            if (! $inspectionExists) {
                throw new \InvalidArgumentException('No se puede crear el FUEC: el vehículo seleccionado no cuenta con una inspección preoperacional registrada para el día de hoy.');
            }
        }

        // 2. Validar seguridad social de los conductores si está configurado
        $requireSocialSecurity = $config ? (bool) $config->fuec_require_social_security : false;
        if ($requireSocialSecurity) {
            $driverUuids = [];
            if (! empty($data['main_conductor_uuid'])) {
                $driverUuids[] = $data['main_conductor_uuid'];
            }
            if (! empty($data['secondary_conductor_uuid'])) {
                $driverUuids[] = $data['secondary_conductor_uuid'];
            }
            if (! empty($data['tertiary_conductor_uuid'])) {
                $driverUuids[] = $data['tertiary_conductor_uuid'];
            }

            $tripPeriod = Carbon::parse($data['effective_date'] ?? $data['issue_date'])->startOfMonth();

            foreach ($driverUuids as $driverUuid) {
                $hasSocialSecurity = DB::table('social_security_contributions')
                    ->where('third_party_uuid', $driverUuid)
                    ->whereDate('billing_period', $tripPeriod)
                    ->whereIn('status', ['PAGADO', 'APROBADO'])
                    ->exists();

                if (! $hasSocialSecurity) {
                    $driverName = DB::table('third_parties')->where('uuid', $driverUuid)->value('name') ?? 'Conductor';
                    throw new \InvalidArgumentException("No se puede crear el FUEC: El conductor {$driverName} no cuenta con aportes de seguridad social pagados o aprobados para el periodo del viaje ({$tripPeriod->format('m/Y')}).");
                }
            }
        }

        // 3. Validar pólizas RCC y RCE (corporativas vs individuales del vehículo)
        $issueDate = Carbon::parse($data['issue_date']);
        $useCorporatePolicies = $config ? (bool) $config->fuec_use_corporate_policies : false;
        if ($useCorporatePolicies) {
            if (empty($config->corporate_rcc_insurer) || empty($config->corporate_rce_expiration)) {
                throw new \InvalidArgumentException('No se puede crear el FUEC: La configuración de las pólizas corporativas (RCC/RCE) está incompleta.');
            }
            $corporateExpiry = Carbon::parse($config->corporate_rce_expiration);
            if ($corporateExpiry->lt($issueDate)) {
                throw new \InvalidArgumentException("No se puede crear el FUEC: Las pólizas corporativas RCC/RCE están vencidas (Vencimiento: {$corporateExpiry->format('d/m/Y')}).");
            }
        } else {
            $policies = DB::table('vehicle_documents')
                ->where('vehicle_uuid', $data['vehicle_uuid'])
                ->whereIn('document_type', ['RCC', 'RCE'])
                ->get();

            $policiesFound = $policies->pluck('document_type')->map(fn ($t) => strtoupper((string) $t))->toArray();

            if (! in_array('RCC', $policiesFound)) {
                throw new \InvalidArgumentException('No se puede crear el FUEC: El vehículo no cuenta con una póliza de Responsabilidad Civil Contractual (RCC) registrada.');
            }
            if (! in_array('RCE', $policiesFound)) {
                throw new \InvalidArgumentException('No se puede crear el FUEC: El vehículo no cuenta con una póliza de Responsabilidad Civil Extracontractual (RCE) registrada.');
            }

            foreach ($policies as $policy) {
                $policyExpiry = $policy->expiry_date ? Carbon::parse($policy->expiry_date) : null;
                if (! $policyExpiry || $policyExpiry->lt($issueDate)) {
                    $formattedExpiry = $policyExpiry ? $policyExpiry->format('d/m/Y') : 'no definida';
                    throw new \InvalidArgumentException("No se puede crear el FUEC: La póliza individual '{$policy->document_type}' está vencida (Fecha de vencimiento: {$formattedExpiry}).");
                }
            }
        }

        return $this->transaction(function () use ($data) {
            $contractorData = $data['contractor'];
            // Asegurar que el contractor posea la misma empresa
            $contractorData['company_uuid'] = $data['company_uuid'];

            $existingContractor = null;
            if (isset($contractorData['uuid'])) {
                $existingContractor = $this->contractorService->getContractorByUuid($contractorData['uuid']);
            }

            if ($existingContractor) {
                $contractor = $this->contractorService->updateContractor($contractorData['uuid'], $contractorData);
            } else {
                // Generar e incrementar el consecutivo de contrato en base de datos de forma atómica
                $nextContractNumber = $this->generateContractConsecutive($data['company_uuid']);
                $contractorData['contract_number'] = str_pad((string) ($nextContractNumber % 10000), 4, '0', STR_PAD_LEFT);

                $contractor = $this->contractorService->createContractor($contractorData);
            }

            // Generar dinámicamente el número FUEC oficial de 23 dígitos bajo bloqueo de base de datos
            $fuecNumberData = $this->assignVehicleToContract($contractor->uuid);
            $fullFuecNumber = $fuecNumberData['fuec_number'];
            $extractConsecutive = substr($fullFuecNumber, -4);

            $randomSuffix = rand(1000, 9999);
            $currentYear = date('Y');
            $verificationCode = (! empty($data['verification_code'])) ? $data['verification_code'] : "CODE-{$currentYear}-{$contractor->contract_number}-{$randomSuffix}";

            $record = Fuec::create([
                'issue_date' => $data['issue_date'],
                'request_number' => $fullFuecNumber,
                'number_fuec' => $extractConsecutive,
                'contract_number_display' => $contractor->contract_number,
                'company_uuid' => $data['company_uuid'],
                'contractor_uuid' => $contractor->uuid,
                'vehicle_uuid' => $data['vehicle_uuid'],
                'effective_date' => $data['effective_date'],
                'expiration_date' => $data['expiration_date'],
                'origin_route' => $data['origin_route'],
                'destination_route' => $data['destination_route'],
                'object_contract_uuid' => $data['object_contract_uuid'],
                'main_conductor_uuid' => $data['main_conductor_uuid'],
                'secondary_conductor_uuid' => $data['secondary_conductor_uuid'] ?? null,
                'tertiary_conductor_uuid' => $data['tertiary_conductor_uuid'] ?? null,
                'verification_code' => $verificationCode,
                'status' => $data['status'] ?? 'ACTIVO',
            ]);

            if (isset($data['passengers']) && is_array($data['passengers'])) {
                foreach ($data['passengers'] as $passenger) {
                    $passengerData = [
                        'fuec_uuid' => $record->uuid,
                        'type_of_document_uuid' => $passenger['type_of_document_uuid'],
                        'document_number' => $passenger['document_number'],
                        'first_and_last_name' => $passenger['first_and_last_name'],
                    ];
                    if (isset($passenger['uuid'])) {
                        $passengerData['uuid'] = $passenger['uuid'];
                    }
                    $this->fuecPassengerService->createFuecPassenger($passengerData);
                }
            }

            return $record->fresh();
        });
    }

    /**
     * Método updateFuec.
     */
    public function updateFuec(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var Fuec $record */
            $record = Fuec::query()->where('uuid', $uuid)->firstOrFail();

            // Sincronizar contratista si se proporciona
            if (isset($data['contractor']) && is_array($data['contractor'])) {
                $contractorData = $data['contractor'];
                $contractorData['company_uuid'] = $data['company_uuid'] ?? $record->company_uuid;

                $existingContractor = null;
                if (isset($contractorData['uuid'])) {
                    $existingContractor = $this->contractorService->getContractorByUuid($contractorData['uuid']);
                }

                if ($existingContractor) {
                    $contractor = $this->contractorService->updateContractor($contractorData['uuid'], $contractorData);
                } else {
                    // Generar e incrementar el consecutivo de contrato en base de datos de forma atómica
                    $nextContractNumber = $this->generateContractConsecutive($contractorData['company_uuid']);
                    $contractorData['contract_number'] = str_pad((string) ($nextContractNumber % 10000), 4, '0', STR_PAD_LEFT);

                    $contractor = $this->contractorService->createContractor($contractorData);
                }
                $data['contractor_uuid'] = $contractor->uuid;
            }

            $record->update([
                'issue_date' => $data['issue_date'] ?? $record->issue_date,
                'request_number' => $data['request_number'] ?? $record->request_number,
                'number_fuec' => $data['number_fuec'] ?? $record->number_fuec,
                'contract_number_display' => $data['contract_number_display'] ?? $record->contract_number_display,
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'contractor_uuid' => $data['contractor_uuid'] ?? $record->contractor_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'effective_date' => $data['effective_date'] ?? $record->effective_date,
                'expiration_date' => $data['expiration_date'] ?? $record->expiration_date,
                'origin_route' => $data['origin_route'] ?? $record->origin_route,
                'destination_route' => $data['destination_route'] ?? $record->destination_route,
                'object_contract_uuid' => $data['object_contract_uuid'] ?? $record->object_contract_uuid,
                'main_conductor_uuid' => $data['main_conductor_uuid'] ?? $record->main_conductor_uuid,
                'secondary_conductor_uuid' => $data['secondary_conductor_uuid'] ?? $record->secondary_conductor_uuid,
                'tertiary_conductor_uuid' => $data['tertiary_conductor_uuid'] ?? $record->tertiary_conductor_uuid,
                'verification_code' => $data['verification_code'] ?? $record->verification_code,
                'status' => $data['status'] ?? $record->status,
            ]);

            if (isset($data['passengers']) && is_array($data['passengers'])) {
                $record->passengers()->delete();
                foreach ($data['passengers'] as $passenger) {
                    $passengerData = [
                        'fuec_uuid' => $record->uuid,
                        'type_of_document_uuid' => $passenger['type_of_document_uuid'],
                        'document_number' => $passenger['document_number'],
                        'first_and_last_name' => $passenger['first_and_last_name'],
                    ];
                    if (isset($passenger['uuid'])) {
                        $passengerData['uuid'] = $passenger['uuid'];
                    }
                    $this->fuecPassengerService->createFuecPassenger($passengerData);
                }
            }

            return $record->fresh();
        });
    }

    /**
     * Método deleteFuec.
     */
    public function deleteFuec(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('FuecService@deleteFuec: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método generateContractConsecutive.
     */
    public function generateContractConsecutive(string $companyUuid, ?int $year = null): int
    {
        $year = $year ?? (int) date('Y');

        return DB::transaction(function () use ($companyUuid, $year) {
            // Bloqueamos la fila de la empresa en la base de datos para evitar la inserción duplicada
            // de secuencias anuales de tipo 'contract' (cuyo 'contract_id' es NULL)
            $companyExists = DB::table('companies')
                ->where('uuid', $companyUuid)
                ->lockForUpdate()
                ->exists();

            if (! $companyExists) {
                throw new \RuntimeException("La empresa con UUID {$companyUuid} no existe.");
            }

            $sequence = ItWasConsecutive::query()
                ->where('type', 'contract')
                ->where('year', $year)
                ->where('company_uuid', $companyUuid)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $nextNumber = $sequence->current_consecutive + 1;
                $sequence->update(['current_consecutive' => $nextNumber]);

                return $nextNumber;
            }

            ItWasConsecutive::create([
                'type' => 'contract',
                'company_uuid' => $companyUuid,
                'year' => $year,
                'contract_id' => null,
                'current_consecutive' => 1,
            ]);

            return 1;
        });
    }

    /**
     * Método getPreviewContractConsecutive.
     */
    public function getPreviewContractConsecutive(string $companyUuid, ?int $year = null): int
    {
        $year = $year ?? (int) date('Y');

        $sequence = ItWasConsecutive::query()
            ->where('type', 'contract')
            ->where('year', $year)
            ->where('company_uuid', $companyUuid)
            ->first();

        if ($sequence) {
            return $sequence->current_consecutive + 1;
        }

        return 1;
    }

    /**
     * Método generateFuecTail.
     */
    public function generateFuecTail(int $contractId, int $assignedContractNumber, string $companyUuid, ?int $year = null): string
    {
        $year = $year ?? (int) date('Y');

        $extractConsecutive = DB::transaction(function () use ($contractId, $companyUuid, $year) {
            // Bloqueamos la fila del contrato en la tabla 'contractors' para evitar condiciones de carrera
            // en la generación simultánea de extractos para un mismo contrato
            $contractExists = DB::table('contractors')
                ->where('id', $contractId)
                ->lockForUpdate()
                ->exists();

            if (! $contractExists) {
                throw new \RuntimeException("El contrato/contratista con ID {$contractId} no existe.");
            }

            $sequence = ItWasConsecutive::query()
                ->where('type', 'extract')
                ->where('year', $year)
                ->where('contract_id', $contractId)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $nextNumber = $sequence->current_consecutive + 1;
                $sequence->update(['current_consecutive' => $nextNumber]);

                return $nextNumber;
            }

            ItWasConsecutive::create([
                'type' => 'extract',
                'company_uuid' => $companyUuid,
                'year' => $year,
                'contract_id' => $contractId,
                'current_consecutive' => 1,
            ]);

            return 1;
        });

        // Asegurar que el contrato y extracto tengan exactamente 4 dígitos usando el módulo y relleno a la izquierda.
        // Resolución 6652/2019: Se toman los últimos 4 dígitos si el valor excede 9999.
        $formattedContract = str_pad((string) ($assignedContractNumber % 10000), 4, '0', STR_PAD_LEFT);
        $formattedExtract = str_pad((string) ($extractConsecutive % 10000), 4, '0', STR_PAD_LEFT);

        return "{$year}{$formattedContract}{$formattedExtract}";
    }

    /**
     * Método assignVehicleToContract.
     */
    public function assignVehicleToContract(string $contractUuid): array
    {
        $contract = $this->contractorService->getContractorByUuid($contractUuid);

        if (! $contract) {
            throw (new ModelNotFoundException)->setModel(Contractor::class, [$contractUuid]);
        }

        $company = $contract->company;

        if (! $company) {
            throw new \RuntimeException('El contratista/contrato no tiene una empresa asociada.');
        }

        // El número del contrato debe haber sido asignado previamente
        if (! $contract->contract_number) {
            throw new \RuntimeException('El contrato aún no tiene un número consecutivo asignado.');
        }

        // Recuperar la resolución de habilitación activa para la empresa
        $enablingResolution = $this->enablingResolutionService->query()
            ->where('company_uuid', $company->uuid)
            ->where('status', true)
            ->first();

        if (! $enablingResolution) {
            throw new \RuntimeException('No se encontró una resolución de habilitación activa para la empresa.');
        }

        // Generar los 12 dígitos finales (año + contrato + extracto) con control de concurrencia
        $fuecTailString = $this->generateFuecTail(
            contractId: $contract->id,
            assignedContractNumber: (int) $contract->contract_number,
            companyUuid: $company->uuid,
        );

        // Código territorial como se ingresó en base de datos y número de resolución/habilitación (columna number_fuec)
        $territorialCode = (string) $enablingResolution->territorial_code;
        $resNum = ! empty($enablingResolution->number_fuec) ? $enablingResolution->number_fuec : $enablingResolution->resolution_number;
        $resolutionNumber = str_pad((string) ((int) $resNum % 10000), 4, '0', STR_PAD_LEFT);

        // Obtener los dos últimos dígitos del año de resolución de forma segura
        // 223259524202600010001
        $resolutionDate = $enablingResolution->resolution_date;
        if (! ($resolutionDate instanceof Carbon)) {
            $resolutionDate = Carbon::parse($resolutionDate);
        }
        $resolutionYear = $resolutionDate->format('y');

        // Construcción del FUEC completo de 23 dígitos: 5 + 4 + 2 + 12
        $fullFuecNumber = "{$territorialCode}{$resolutionNumber}{$resolutionYear}{$fuecTailString}";

        return [
            'fuec_number' => $fullFuecNumber,
            'data' => [
                'year' => substr($fuecTailString, 0, 4),
                'formattedContract' => substr($fuecTailString, 4, 4),
                'formattedExtract' => substr($fuecTailString, 8, 4),
            ],
        ];
    }

    /**
     * Método getPreviewFuecNumber.
     */
    public function getPreviewFuecNumber(string $companyUuid, ?string $contractorUuid = null, ?string $contractNumber = null): string
    {
        $year = (int) date('Y');

        $company = DB::table('companies')->where('uuid', $companyUuid)->first();
        if (! $company) {
            return '';
        }

        // Buscar resolución de habilitación activa
        $enablingResolution = DB::table('enabling_resolutions')
            ->where('company_uuid', $companyUuid)
            ->where('status', true)
            ->first();

        if (! $enablingResolution) {
            return '';
        }

        // Código territorial como se ingresó en base de datos y número de resolución/habilitación (columna number_fuec)
        $territorialCode = (string) $enablingResolution->territorial_code;
        $resNum = ! empty($enablingResolution->number_fuec) ? $enablingResolution->number_fuec : $enablingResolution->resolution_number;
        $resolutionNumber = str_pad((string) ((int) $resNum % 10000), 4, '0', STR_PAD_LEFT);

        $resolutionDate = $enablingResolution->resolution_date;
        if (! ($resolutionDate instanceof Carbon)) {
            $resolutionDate = Carbon::parse($resolutionDate);
        }
        $resolutionYear = $resolutionDate->format('y');

        // Determinar número de contrato y consecutivo de extracto
        $assignedContractNumber = 1;
        $extractConsecutive = 1;

        if ($contractorUuid) {
            $contract = DB::table('contractors')->where('uuid', $contractorUuid)->first();
            if ($contract) {
                $assignedContractNumber = (int) $contract->contract_number;

                $sequence = ItWasConsecutive::query()
                    ->where('type', 'extract')
                    ->where('year', $year)
                    ->where('contract_id', $contract->id)
                    ->first();

                $extractConsecutive = $sequence ? ($sequence->current_consecutive + 1) : 1;
            }
        } elseif ($contractNumber) {
            $assignedContractNumber = (int) $contractNumber;
        } else {
            // Obtener vista previa de número de contrato
            $assignedContractNumber = $this->getPreviewContractConsecutive($companyUuid, $year);
        }

        $formattedContract = str_pad((string) ($assignedContractNumber % 10000), 4, '0', STR_PAD_LEFT);
        $formattedExtract = str_pad((string) ($extractConsecutive % 10000), 4, '0', STR_PAD_LEFT);

        return "{$territorialCode}{$resolutionNumber}{$resolutionYear}{$year}{$formattedContract}{$formattedExtract}";
    }

    /**
     * Método getPdfData.
     */
    public function getPdfData(string $verificationCode): ?Fuec
    {
        /** @var Fuec|null $fuec */
        $fuec = $this->query()->with([
            'company:id,uuid,business_name,document_number,verification_digit,address,phone,email,web_page',
            'contractor:uuid,company_name,document_number,responsible_name,responsible_document,responsible_phone,responsible_address',
            'vehicle:uuid,vehicle_class_uuid,brand_uuid,vehicle_license_plate,internal_number,model',
            'vehicle.brand:uuid,description',
            'vehicle.vehicleClass:uuid,description',
            'vehicle.operationCards:operating_card_number,vehicle_uuid',
            'vehicle.businessCollaborationAgreements:uuid,vehicle_uuid,contracting_entity_name',
            'objectsContract:uuid,name,description',
            'mainConductor:uuid,first_name,last_name,document_number',
            'mainConductor.driverLicenses:number,category,expiration_date,third_party_uuid',
            'secondaryConductor:uuid,first_name,last_name,document_number',
            'secondaryConductor.driverLicenses:number,category,expiration_date,third_party_uuid',
            'tertiaryConductor:uuid,first_name,last_name,document_number',
            'tertiaryConductor.driverLicenses:number,category,expiration_date,third_party_uuid',
            'passengers.typeOfDocument:uuid,prefix',
        ])
            ->select(
                'issue_date',
                'uuid',
                'vehicle_uuid',
                'company_uuid',
                'contractor_uuid',
                'object_contract_uuid',
                'main_conductor_uuid',
                'secondary_conductor_uuid',
                'tertiary_conductor_uuid',
                'request_number',
                'number_fuec',
                'contract_number_display as contract_number',
                'origin_route',
                'destination_route',
                'effective_date',
                'expiration_date',
                'verification_code',
            )
            ->where('verification_code', $verificationCode)
            ->first();

        if ($fuec) {
            // Cargar pasajeros formateados
            $formattedPassengers = $fuec->passengers->map(function ($passenger, $index) {
                return [
                    'counter' => $index + 1,
                    'uuid' => $passenger->uuid,
                    'document_number' => $passenger->document_number,
                    'first_and_last_name' => $passenger->first_and_last_name,
                    'prefix' => $passenger->typeOfDocument->prefix ?? '',
                ];
            });
            $fuec->setAttribute('passengers', $formattedPassengers);

            // Consultamos los tipos de documentos del vehículo
            $vehicleDocumentService = app(VehicleDocumentService::class);
            $fuec->vehicle_documents = $vehicleDocumentService->getAllVehicleDocuments(null, $fuec->vehicle_uuid);
        }

        return $fuec;
    }
}
