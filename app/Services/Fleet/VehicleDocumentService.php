<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\VehicleDocument;
use App\Models\Vehicle;
use App\Services\BaseService;
use App\Services\Notifications\EmailLogService;
use App\Services\Notifications\NotificationsService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de DocumentoVehiculo.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class VehicleDocumentService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['policy_number', 'issuing_entity'];

    protected function getModelInstance(): Model
    {
        return new VehicleDocument;
    }

    /**
     * Método getAllVehicleDocumentsWithPagination.
     */
    public function getAllVehicleDocumentsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $documentType = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()->with(['vehicle.thirdParty']);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        if ($documentType) {
            $types = explode(',', $documentType);
            if (count($types) > 1) {
                return $this->getGroupedVehicleDocumentPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid, $types);
            }
            $query->where('document_type', $documentType);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
                $q->orWhereHas('vehicle', function ($qVeh) use ($search) {
                    $qVeh->where('vehicle_license_plate', 'like', "%{$search}%");
                });
            });
        }

        $columns = [
            'uuid',
            'vehicle_uuid',
            'policy_number',
            'document_type',
            'issuing_entity',
            'expiry_date',
            'status',
        ];

        return $query->paginate($perPage, $columns, 'page', $page);
    }

    /**
 * Devuelve el listado de pólizas (RCE/RCC) agrupado por vehículo.
 * Cada fila del resultado representa un solo vehículo y contiene sus
 * pólizas en la propiedad "policies".
 *
 * @param string $search
 * @param array<string> $types Tipos de documento agrupados (ej. RCE, RCC)
 */
    private function getGroupedVehicleDocumentPagination(
        int $perPage,
        int $page,
        string $search,
        ?string $companyUuid,
        ?string $thirdPartyUuid,
        array $types
    ): LengthAwarePaginator {
        $vehicleQuery = Vehicle::query()
            ->whereExists(function ($q) use ($types, $companyUuid) {
                $q->selectRaw('1')->from('vehicle_documents')
                    ->whereColumn('vehicle_documents.vehicle_uuid', 'vehicles.uuid')
                    ->whereIn('vehicle_documents.document_type', $types);
                if ($companyUuid) {
                    $q->where('vehicle_documents.company_uuid', $companyUuid);
                }
            });

        if ($thirdPartyUuid) {
            $vehicleQuery->where('third_party_uuid', $thirdPartyUuid);
        }

        if (! empty($search)) {
            $vehicleQuery->where(function ($q) use ($search, $types) {
                $q->where('vehicle_license_plate', 'like', "%{$search}%")
                    ->orWhere('internal_number', 'like', "%{$search}%")
                    ->orWhereHas('vehicleDocuments', function ($qd) use ($search, $types) {
                        $qd->whereIn('document_type', $types)
                            ->where(function ($q2) use ($search) {
                                foreach ($this->searchableFields as $field) {
                                    $q2->orWhere($field, 'like', "%{$search}%");
                                }
                            });
                    });
            });
        }

        $vehicles = $vehicleQuery->paginate($perPage, ['uuid', 'vehicle_license_plate'], 'page', $page);

        $uuids = $vehicles->pluck('uuid');
        $documents = VehicleDocument::whereIn('vehicle_uuid', $uuids)
            ->whereIn('document_type', $types)
            ->orderBy('document_type')
            ->get()
            ->groupBy('vehicle_uuid');

        $grouped = $vehicles->map(function ($vehicle) use ($documents, $types) {
            $policies = $documents->get($vehicle->uuid, collect())->values();
            $first = $policies->first();

            $validExpiries = $policies->pluck('expiry_date')->filter()
                ->map(fn ($date) => $date instanceof \Carbon\Carbon ? $date->toDateString() : (string) $date);
            $expiry = $validExpiries->isEmpty() ? null : $validExpiries->sort()->first();

            $hasVigente = $policies->contains(fn ($doc) => $doc->status === 'VIGENTE');

            return [
                'uuid' => $vehicle->uuid,
                'vehicle_uuid' => $vehicle->uuid,
                'policy_number' => $first?->policy_number,
                'document_type' => implode(',', collect($types)->unique()->values()->all()),
                'issuing_entity' => $policies->pluck('issuing_entity')->filter()->unique()->implode(' / '),
                'expiry_date' => $expiry,
                'status' => $hasVigente ? 'VIGENTE' : ($first?->status ?? 'INACTIVA'),
                'vehicle' => [
                    'uuid' => $vehicle->uuid,
                    'vehicle_license_plate' => $vehicle->vehicle_license_plate,
                ],
                'policies' => $policies->map(function ($doc) {
                    return [
                        'uuid' => $doc->uuid,
                        'vehicle_uuid' => $doc->vehicle_uuid,
                        'policy_number' => $doc->policy_number,
                        'document_type' => $doc->document_type,
                        'issuing_entity' => $doc->issuing_entity,
                        'expiry_date' => $doc->expiry_date?->toDateString(),
                        'status' => $doc->status,
                    ];
                })->all(),
            ];
        });

        return $vehicles->setCollection($grouped);
    }

    /**
     * Método getAllVehicleDocuments.
     */
    public function getAllVehicleDocuments(?string $companyUuid = null, ?string $vehicleUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query()->with(['vehicle.thirdParty']);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        return $query->get([
            'uuid',
            'company_uuid',
            'vehicle_uuid',
            'policy_number',
            'document_type',
            'issuing_entity',
            'expiry_date',
            'status',
        ]);
    }

    /**
     * Método getVehicleDocumentByUuid.
     */
    public function getVehicleDocumentByUuid(string $uuid): ?Model
    {
        $document = $this->findByUuid($uuid);
        if ($document) {
            $document->load(['vehicle.thirdParty']);
        }

        return $document;
    }

    public function createVehicleDocument(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            // Marcar documentos anteriores del mismo tipo como INACTIVA
            VehicleDocument::where('vehicle_uuid', $data['vehicle_uuid'])
                ->where('document_type', $data['document_type'])
                ->update(['status' => 'INACTIVA']);

            $document = VehicleDocument::create([
                'company_uuid' => $data['company_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'],
                'document_type' => $data['document_type'],
                'policy_number' => $data['policy_number'],
                'issue_date' => $data['issue_date'],
                'effective_date' => $data['effective_date'] ?? null,
                'expiry_date' => $data['expiry_date'],
                'issuing_entity' => $data['issuing_entity'],
                'tariff_code' => $data['tariff_code'] ?? null,
                'taker' => $data['taker'] ?? null,
                'status' => $data['status'],
            ]);

            try {
                $document->load(['vehicle.thirdParty', 'company']);

                // 1. Enviar correo instantáneo si aplica
                app(EmailLogService::class)->processSingleDocument($document);

                // 2. Eliminar la notificación de "FALTANTE" en la campanita web
                $hash = md5($document->vehicle_uuid.'_'.$document->document_type);
                $entityUuid = sprintf('%08s-%04s-%04s-%04s-%12s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
                app(NotificationsService::class)->deleteByEntity($entityUuid, 'VEHICLE_DOCUMENT');

                // 3. Sincronizar notificaciones de la empresa de forma inmediata
                app(NotificationsService::class)->syncNotifications($document->company_uuid);

            } catch (\Exception $e) {
                Logger::error('Error en procesos post-registro de documento: '.$e->getMessage());
            }

            return $document;
        });
    }

    /**
     * Método updateVehicleDocument.
     */
    public function updateVehicleDocument(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'document_type' => $data['document_type'] ?? $record->document_type,
                'policy_number' => $data['policy_number'] ?? $record->policy_number,
                'issue_date' => $data['issue_date'] ?? $record->issue_date,
                'effective_date' => $data['effective_date'] ?? $record->effective_date,
                'expiry_date' => $data['expiry_date'] ?? $record->expiry_date,
                'issuing_entity' => $data['issuing_entity'] ?? $record->issuing_entity,
                'tariff_code' => $data['tariff_code'] ?? $record->tariff_code,
                'taker' => $data['taker'] ?? $record->taker,
                'status' => $data['status'] ?? $record->status,
            ]);

            try {
                $record->load(['vehicle.thirdParty', 'company']);

                // 1. Enviar correo instantáneo de la nueva fecha si aplica
                app(EmailLogService::class)->processSingleDocument($record);

                // 2. Limpiar alertas previas ("VENCIDO" / "POR VENCER") en la campanita web
                app(NotificationsService::class)->deleteByEntity($record->uuid, 'VEHICLE_DOCUMENT');

                // 3. Sincronizar notificaciones de la empresa de forma inmediata
                app(NotificationsService::class)->syncNotifications($record->company_uuid);

            } catch (\Exception $e) {
                Logger::error('Error en envío instantáneo de correos tras update: '.$e->getMessage());
            }

            return $record->fresh();
        });
    }

    /**
     * Método deleteVehicleDocument.
     */
    public function deleteVehicleDocument(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('VehicleDocumentService@deleteVehicleDocument: '.$e->getMessage());
            throw $e;
        }
    }
}
