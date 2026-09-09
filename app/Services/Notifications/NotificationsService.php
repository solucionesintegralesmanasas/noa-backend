<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\AffiliateAdminCharge;
use App\Models\BusinessCollaborationAgreement;
use App\Models\DriverLicense;
use App\Models\Notification;
use App\Models\OperationCard;
use App\Models\SocialSecurityContribution;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\BaseService;
use App\Utils\Logger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de negocio para la gestión integral y persistencia de alertas y notificaciones.
 * Hereda de BaseService para proveer transaccionalidad, logging y filtros estandarizados.
 *
 * @author   Darwin Montes
 *
 * @version  1.1.0
 *
 * @since    1.0.0
 */
class NotificationsService extends BaseService
{
    /** Días de anticipación para alertar vencimiento próximo (documentos, tarjetas, licencias) */
    private const EXPIRY_ALERT_DAYS = 5;

    /** Días de anticipación para alertar la primera RTM */
    private const RTM_ALERT_DAYS = 5;

    /** Días de anticipación para alertar convenios */
    private const AGREEMENT_ALERT_DAYS = 5;

    /** Documentos obligatorios para cada vehículo */
    private const MANDATORY_DOCUMENTS = ['SOAT', 'RTM', 'RCC', 'RCE'];

    /**
     * @var array<string>
     */
    protected array $searchableFields = ['title', 'message'];

    /**
     * Retorna la instancia de modelo para BaseService.
     */
    protected function getModelInstance(): Model
    {
        return new Notification;
    }

    /**
     * Método syncNotifications.
     */
    public function syncNotifications(?string $companyUuid = null, ?string $thirdPartyUuid = null): void
    {
        $this->transaction(function () use ($companyUuid, $thirdPartyUuid) {
            $vehicleDocs = $this->notificationsForVehicleDocuments($companyUuid, $thirdPartyUuid);
            $operationCards = $this->notificationsForOperationCards($companyUuid, $thirdPartyUuid);
            $driverLicenses = $this->notificationsForDriverLicenses($companyUuid, $thirdPartyUuid);
            $firstRtm = $this->notificationsForFirstRTM($companyUuid, $thirdPartyUuid);
            $agreements = $this->notificationsForAgreements($companyUuid, $thirdPartyUuid);
            $affiliateCharges = $this->notificationsForAffiliateCharges($companyUuid, $thirdPartyUuid);
            $pendingInspections = $this->notificationsForPendingInspections($companyUuid, $thirdPartyUuid);
            $preventativeMaintenance = $this->notificationsForPreventativeMaintenance($companyUuid, $thirdPartyUuid);
            $socialSecurity = $this->notificationsForSocialSecurity($companyUuid, $thirdPartyUuid);

            $activeAlerts = [];

            // 1. Documentos de Vehículo
            foreach (array_merge($vehicleDocs['expired'] ?? [], $vehicleDocs['expiring_soon'] ?? [], $vehicleDocs['missing'] ?? []) as $alert) {
                $vUuid = $alert['vehicle_uuid'] ?? null;
                $vehicle = $vUuid ? Vehicle::query()->where('uuid', '=', $vUuid, 'and')->first() : null;
                $cUuid = $companyUuid ?? $vehicle?->company_uuid ?? null;
                if (! $cUuid) {
                    continue;
                }

                // Si es un documento faltante, generar un UUID determinístico para evitar sobreescribir otros faltantes del mismo vehículo
                $entityUuid = $alert['document_uuid'] ?? null;
                if (! $entityUuid && $vUuid) {
                    $docType = $alert['document_type'] ?? 'MISSING';
                    $hash = md5($vUuid.'_'.$docType);
                    $entityUuid = sprintf('%08s-%04s-%04s-%04s-%12s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
                }

                $activeAlerts[] = [
                    'company_uuid' => $cUuid,
                    'type' => 'VEHICLE_DOCUMENT',
                    'title' => 'Documento '.($alert['document_type'] ?? '').' '.($alert['status'] ?? 'FALTANTE'),
                    'message' => $alert['message'],
                    'entity_uuid' => $entityUuid,
                    'entity_type' => isset($alert['document_uuid']) ? VehicleDocument::class : Vehicle::class,
                    'days_left' => $alert['days_left'] ?? null,
                    'expiry_date' => isset($alert['expiry_date']) ? Carbon::parse($alert['expiry_date']) : null,
                    'extra_data' => [
                        'vehicle_license_plate' => $alert['vehicle_license_plate'] ?? null,
                        'document_type' => $alert['document_type'] ?? null,
                    ],
                ];
            }

            // 2. Tarjetas de Operación
            foreach (array_merge($operationCards['expired'] ?? [], $operationCards['expiring_soon'] ?? [], $operationCards['missing'] ?? []) as $alert) {
                $vUuid = $alert['vehicle_uuid'] ?? null;
                $vehicle = $vUuid ? Vehicle::query()->where('uuid', '=', $vUuid, 'and')->first() : null;
                $cUuid = $companyUuid ?? $vehicle?->company_uuid ?? null;
                if (! $cUuid) {
                    continue;
                }

                $activeAlerts[] = [
                    'company_uuid' => $cUuid,
                    'type' => 'OPERATION_CARD',
                    'title' => 'Tarjeta de Operación '.($alert['operating_card_number'] ?? 'FALTANTE'),
                    'message' => $alert['message'],
                    'entity_uuid' => $alert['operation_card_uuid'] ?? $alert['vehicle_uuid'] ?? null,
                    'entity_type' => isset($alert['operation_card_uuid']) ? OperationCard::class : Vehicle::class,
                    'days_left' => $alert['days_left'] ?? null,
                    'expiry_date' => isset($alert['expiration_date']) ? Carbon::parse($alert['expiration_date']) : null,
                    'extra_data' => [
                        'vehicle_license_plate' => $alert['vehicle_license_plate'] ?? null,
                        'operating_card_number' => $alert['operating_card_number'] ?? null,
                    ],
                ];
            }

            // 3. Licencias de Conducción
            foreach (array_merge($driverLicenses['expired'] ?? [], $driverLicenses['expiring_soon'] ?? []) as $alert) {
                $licUuid = $alert['license_uuid'] ?? null;
                $license = $licUuid ? DriverLicense::query()->where('uuid', '=', $licUuid, 'and')->first() : null;
                $cUuid = $companyUuid ?? $license?->company_uuid ?? null;
                if (! $cUuid) {
                    continue;
                }

                $activeAlerts[] = [
                    'company_uuid' => $cUuid,
                    'type' => 'DRIVER_LICENSE',
                    'title' => 'Licencia de Conducción #'.($alert['license_number'] ?? ''),
                    'message' => $alert['message'],
                    'entity_uuid' => $licUuid,
                    'entity_type' => DriverLicense::class,
                    'days_left' => $alert['days_left'] ?? null,
                    'expiry_date' => isset($alert['expiration_date']) ? Carbon::parse($alert['expiration_date']) : null,
                    'extra_data' => [
                        'driver_name' => $alert['driver_name'] ?? null,
                        'license_number' => $alert['license_number'] ?? null,
                    ],
                ];
            }

            // 4. Primera RTM
            foreach (array_merge($firstRtm['expired'] ?? [], $firstRtm['expiring_soon'] ?? []) as $alert) {
                $vUuid = $alert['vehicle_uuid'] ?? null;
                $vehicle = $vUuid ? Vehicle::query()->where('uuid', '=', $vUuid, 'and')->first() : null;
                $cUuid = $companyUuid ?? $vehicle?->company_uuid ?? null;
                if (! $cUuid) {
                    continue;
                }

                $activeAlerts[] = [
                    'company_uuid' => $cUuid,
                    'type' => 'FIRST_RTM',
                    'title' => 'Primera RTM - '.($alert['vehicle_license_plate'] ?? ''),
                    'message' => $alert['message'],
                    'entity_uuid' => $vUuid,
                    'entity_type' => Vehicle::class,
                    'days_left' => $alert['days_left'] ?? null,
                    'expiry_date' => isset($alert['first_rtm_due_date']) ? Carbon::parse($alert['first_rtm_due_date']) : null,
                    'extra_data' => [
                        'vehicle_license_plate' => $alert['vehicle_license_plate'] ?? null,
                    ],
                ];
            }

            // 5. Convenios
            foreach (array_merge($agreements['expired'] ?? [], $agreements['expiring_soon'] ?? []) as $alert) {
                $agUuid = $alert['agreement_uuid'] ?? null;
                $agreement = $agUuid ? BusinessCollaborationAgreement::query()->where('uuid', '=', $agUuid, 'and')->first() : null;
                $cUuid = $companyUuid ?? $agreement?->company_uuid ?? null;
                if (! $cUuid) {
                    $vUuid = $alert['vehicle_uuid'] ?? null;
                    $vehicle = $vUuid ? Vehicle::query()->where('uuid', '=', $vUuid, 'and')->first() : null;
                    $cUuid = $vehicle?->company_uuid;
                }
                if (! $cUuid) {
                    continue;
                }

                $activeAlerts[] = [
                    'company_uuid' => $cUuid,
                    'type' => 'AGREEMENT',
                    'title' => 'Convenio Colaboración #'.($alert['resolution_number'] ?? ''),
                    'message' => $alert['message'],
                    'entity_uuid' => $agUuid,
                    'entity_type' => BusinessCollaborationAgreement::class,
                    'days_left' => $alert['days_left'] ?? null,
                    'expiry_date' => isset($alert['expiration_date']) ? Carbon::parse($alert['expiration_date']) : null,
                    'extra_data' => [
                        'entity_name' => $alert['entity_name'] ?? null,
                        'license_plate' => $alert['license_plate'] ?? null,
                    ],
                ];
            }

            // 6. Cobros de Administración
            foreach (array_merge($affiliateCharges['expired'] ?? [], $affiliateCharges['expiring_soon'] ?? []) as $alert) {
                $chUuid = $alert['charge_uuid'] ?? null;
                $charge = $chUuid ? AffiliateAdminCharge::query()->where('uuid', '=', $chUuid, 'and')->first() : null;
                $cUuid = $companyUuid ?? $charge?->company_uuid ?? null;
                if (! $cUuid) {
                    continue;
                }

                $activeAlerts[] = [
                    'company_uuid' => $cUuid,
                    'type' => 'AFFILIATE_CHARGE',
                    'title' => 'Cobro de Administración - '.($alert['vehicle_license_plate'] ?? ''),
                    'message' => $alert['message'],
                    'entity_uuid' => $chUuid,
                    'entity_type' => AffiliateAdminCharge::class,
                    'days_left' => $alert['days_left'] ?? null,
                    'expiry_date' => isset($alert['due_date']) ? Carbon::parse($alert['due_date']) : null,
                    'extra_data' => [
                        'vehicle_license_plate' => $alert['vehicle_license_plate'] ?? null,
                        'amount' => $alert['amount'] ?? null,
                    ],
                ];
            }

            // 7. Inspecciones Pendientes (Obligatoria diaria)
            foreach ($pendingInspections as $alert) {
                $vUuid = $alert['vehicle_uuid'] ?? null;
                $cUuid = $companyUuid ?? Vehicle::query()->where('uuid', '=', $vUuid)->value('company_uuid') ?? null;
                if (! $cUuid) {
                    continue;
                }

                $activeAlerts[] = [
                    'company_uuid' => $cUuid,
                    'type' => 'VEHICLE_INSPECTION_PENDING',
                    'title' => 'Inspección Pendiente: '.($alert['vehicle_license_plate'] ?? ''),
                    'message' => $alert['message'],
                    'entity_uuid' => $vUuid,
                    'entity_type' => Vehicle::class,
                    'days_left' => 0,
                    'expiry_date' => Carbon::today(),
                    'extra_data' => [
                        'vehicle_license_plate' => $alert['vehicle_license_plate'] ?? null,
                    ],
                ];
            }

            // 8. Mantenimiento Preventivo (Kilometraje)
            foreach ($preventativeMaintenance as $alert) {
                $vUuid = $alert['vehicle_uuid'] ?? null;
                $cUuid = $companyUuid ?? Vehicle::query()->where('uuid', '=', $vUuid)->value('company_uuid') ?? null;
                if (! $cUuid) {
                    continue;
                }

                $hash = md5($vUuid.'_'.$alert['milestone'].'_'.($alert['interval'] ?? ''));
                $deterministicUuid = sprintf(
                    '%08s-%04s-%04s-%04s-%12s',
                    substr($hash, 0, 8),
                    substr($hash, 8, 4),
                    substr($hash, 12, 4),
                    substr($hash, 16, 4),
                    substr($hash, 20, 12)
                );

                $activeAlerts[] = [
                    'company_uuid' => $cUuid,
                    'type' => 'VEHICLE_MAINTENANCE_ALERT',
                    'title' => $alert['title'],
                    'message' => $alert['message'],
                    'entity_uuid' => $deterministicUuid,
                    'entity_type' => Vehicle::class,
                    'days_left' => $alert['days_left'],
                    'expiry_date' => Carbon::today(),
                    'extra_data' => [
                        'vehicle_license_plate' => $alert['vehicle_license_plate'] ?? null,
                        'milestone' => $alert['milestone'] ?? null,
                        'status' => $alert['status'] ?? null,
                        'km_left' => $alert['km_left'] ?? null,
                    ],
                ];
            }

            // 9. Seguridad Social (Aportes en Mora)
            foreach ($socialSecurity['mora'] as $alert) {
                $tpUuid = $alert['third_party_uuid'] ?? null;
                $thirdParty = $tpUuid ? ThirdParty::query()->where('uuid', '=', $tpUuid)->first() : null;
                $cUuid = $companyUuid ?? $thirdParty?->company_uuid ?? null;
                if (! $cUuid) {
                    continue;
                }

                $hash = md5('SS_'.$tpUuid.'_'.$alert['billing_period']);
                $deterministicUuid = sprintf(
                    '%08s-%04s-%04s-%04s-%12s',
                    substr($hash, 0, 8),
                    substr($hash, 8, 4),
                    substr($hash, 12, 4),
                    substr($hash, 16, 4),
                    substr($hash, 20, 12)
                );

                $activeAlerts[] = [
                    'company_uuid' => $cUuid,
                    'type' => 'SOCIAL_SECURITY_MORA',
                    'title' => $alert['title'],
                    'message' => $alert['message'],
                    'entity_uuid' => $deterministicUuid,
                    'entity_type' => SocialSecurityContribution::class,
                    'days_left' => $alert['days_left'] ?? null,
                    'expiry_date' => isset($alert['billing_period']) ? Carbon::parse($alert['billing_period']) : null,
                    'extra_data' => [
                        'third_party_name' => $alert['third_party_name'] ?? null,
                        'billing_period' => $alert['billing_period'] ?? null,
                        'status' => $alert['status'] ?? null,
                    ],
                ];
            }

            $activeNotificationUuids = [];

            foreach ($activeAlerts as $alertData) {
                $existing = Notification::query()
                    ->where('company_uuid', '=', $alertData['company_uuid'], 'and')
                    ->where('type', '=', $alertData['type'], 'and')
                    ->where('entity_uuid', '=', $alertData['entity_uuid'], 'and')
                    ->first();

                if ($existing) {
                    $existing->update([
                        'title' => $alertData['title'],
                        'message' => $alertData['message'],
                        'days_left' => $alertData['days_left'],
                        'expiry_date' => $alertData['expiry_date'],
                        'extra_data' => $alertData['extra_data'],
                    ]);
                    $activeNotificationUuids[] = $existing->uuid;
                } else {
                    $newNotif = Notification::create([
                        'company_uuid' => $alertData['company_uuid'],
                        'type' => $alertData['type'],
                        'title' => $alertData['title'],
                        'message' => $alertData['message'],
                        'status' => 'PENDIENTE',
                        'entity_uuid' => $alertData['entity_uuid'],
                        'entity_type' => $alertData['entity_type'],
                        'days_left' => $alertData['days_left'],
                        'expiry_date' => $alertData['expiry_date'],
                        'extra_data' => $alertData['extra_data'],
                    ]);
                    $activeNotificationUuids[] = $newNotif->uuid;
                }
            }

            // Eliminar de base de datos alertas resueltas
            $cleanupQuery = Notification::query();
            if ($companyUuid) {
                $cleanupQuery->where('company_uuid', $companyUuid);
            }
            if (! empty($activeNotificationUuids)) {
                $cleanupQuery->whereNotIn('uuid', $activeNotificationUuids, 'and');
            }
            $cleanupQuery->delete();
        });
    }

    /**
     * Método getNotificationsWithPagination.
     */
    public function getNotificationsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $status = null,
        ?string $type = null
    ): LengthAwarePaginator {
        $query = $this->query();

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $query->latest();

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Método getLatestNotifications.
     */
    public function getLatestNotifications(?string $companyUuid = null, int $limit = 10): Collection
    {
        $query = $this->query();

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        return $query->latest()->limit($limit)->get();
    }

    /**
     * Método markAsRead.
     */
    public function markAsRead(string $uuid): bool
    {
        return $this->transaction(function () use ($uuid) {
            $record = $this->findByUuid($uuid);
            if ($record) {
                return $record->update(['status' => 'LEIDA']);
            }

            return false;
        });
    }

    /**
     * Método markAllAsRead.
     */
    public function markAllAsRead(?string $companyUuid = null): int
    {
        return $this->transaction(function () use ($companyUuid) {
            $query = $this->query()->where('status', 'PENDIENTE');

            if (! $companyUuid) {
                $companyUuid = request()->attributes->get('current_company_uuid');
            }
            if ($companyUuid) {
                $this->applyCompanyFilter($query, $companyUuid);
            }

            return $query->update(['status' => 'LEIDA']);
        });
    }

    /**
     * Elimina notificaciones vinculadas a un entity_uuid específico.
     */
    public function deleteByEntity(string $entityUuid, string $type): void
    {
        $this->transaction(function () use ($entityUuid, $type) {
            $this->query()
                ->where('entity_uuid', $entityUuid)
                ->where('type', $type)
                ->delete();
        });
    }

    /**
     * Aplica el filtro de empresa y aísla las notificaciones por afiliado/conductor basándose en las entidades.
     */
    protected function applyCompanyFilter(Builder $query, string $companyUuid): void
    {
        parent::applyCompanyFilter($query, $companyUuid);

        $user = Auth::user();
        if ($user && ($user->hasRole('AFILIADO') || $user->hasRole('CONDUCTOR'))) {
            $thirdPartyUuid = request()->attributes->get('current_third_party_uuid');
            if ($thirdPartyUuid) {
                $query->where(function ($q) use ($thirdPartyUuid) {
                    // 1. Vehículos
                    $q->orWhere(function ($sub) use ($thirdPartyUuid) {
                        $sub->where('entity_type', '=', Vehicle::class, 'and')
                            ->whereIn('entity_uuid', Vehicle::where('third_party_uuid', '=', $thirdPartyUuid, 'and')->select('uuid'), 'and', false);
                    })
                    // 2. Documentos de Vehículo
                        ->orWhere(function ($sub) use ($thirdPartyUuid) {
                            $sub->where('entity_type', '=', VehicleDocument::class, 'and')
                                ->whereIn('entity_uuid', VehicleDocument::whereHas('vehicle', function ($vq) use ($thirdPartyUuid) {
                                    $vq->where('third_party_uuid', '=', $thirdPartyUuid, 'and');
                                })->select('uuid'), 'and', false);
                        })
                    // 3. Tarjetas de Operación
                        ->orWhere(function ($sub) use ($thirdPartyUuid) {
                            $sub->where('entity_type', '=', OperationCard::class, 'and')
                                ->whereIn('entity_uuid', OperationCard::whereHas('vehicle', function ($vq) use ($thirdPartyUuid) {
                                    $vq->where('third_party_uuid', '=', $thirdPartyUuid, 'and');
                                })->select('uuid'), 'and', false);
                        })
                    // 4. Licencias de Conducción
                        ->orWhere(function ($sub) use ($thirdPartyUuid) {
                            $sub->where('entity_type', '=', DriverLicense::class, 'and')
                                ->whereIn('entity_uuid', DriverLicense::where('third_party_uuid', '=', $thirdPartyUuid, 'and')->select('uuid'), 'and', false);
                        })
                    // 5. Convenios
                        ->orWhere(function ($sub) use ($thirdPartyUuid) {
                            $sub->where('entity_type', '=', BusinessCollaborationAgreement::class, 'and')
                                ->whereIn('entity_uuid', BusinessCollaborationAgreement::whereHas('vehicle', function ($vq) use ($thirdPartyUuid) {
                                    $vq->where('third_party_uuid', '=', $thirdPartyUuid, 'and');
                                })->select('uuid'), 'and', false);
                        })
                    // 6. Cobros
                        ->orWhere(function ($sub) use ($thirdPartyUuid) {
                            $sub->where('entity_type', '=', AffiliateAdminCharge::class, 'and')
                                ->whereIn('entity_uuid', AffiliateAdminCharge::whereHas('vehicle', function ($vq) use ($thirdPartyUuid) {
                                    $vq->where('third_party_uuid', '=', $thirdPartyUuid, 'and');
                                })->select('uuid'), 'and', false);
                        });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }
    }

    // =========================================================================
    // DOCUMENTOS DE VEHÍCULO
    // =========================================================================

    /**
     * Método notificationsForVehicleDocuments.
     */
    public function notificationsForVehicleDocuments(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $today = Carbon::today();
            $alertDate = $today->copy()->addDays(self::EXPIRY_ALERT_DAYS);

            $query = Vehicle::query()
                ->with([
                    'thirdParty',
                    'vehicleDocuments',
                ]);

            if ($companyUuid) {
                $query->where('vehicles.company_uuid', $companyUuid);
            }

            $query->when($thirdPartyUuid, function ($q) use ($thirdPartyUuid) {
                $q->where('vehicles.third_party_uuid', $thirdPartyUuid);
            });

            $expired = [];
            $expiringSoon = [];
            $missing = [];

            $query->chunk(100, function ($vehicles) use (&$expired, &$expiringSoon, &$missing, $today, $alertDate) {
                /** @var Vehicle $vehicle */
                foreach ($vehicles as $vehicle) {
                    $registeredTypes = $vehicle->vehicleDocuments->pluck('document_type')->map(fn ($t) => strtoupper((string) $t))->toArray();

                    foreach (self::MANDATORY_DOCUMENTS as $mandatoryType) {
                        if (! in_array($mandatoryType, $registeredTypes)) {
                            if ($mandatoryType === 'RTM') {
                                $regDate = $vehicle->registration_date ? Carbon::parse($vehicle->registration_date) : null;
                                if ($regDate && $regDate->copy()->addYears(2)->gt($today)) {
                                    continue;
                                }
                            }

                            $missing[] = [
                                'vehicle_uuid' => $vehicle->uuid,
                                'vehicle_license_plate' => $vehicle->vehicle_license_plate,
                                'third_party_uuid' => $vehicle->third_party_uuid,
                                'document_type' => $mandatoryType,
                                'status' => 'FALTANTE',
                                'date' => $today->toDateTimeString(),
                                'message' => "Falta registrar el documento '{$mandatoryType}' para el vehículo {$vehicle->vehicle_license_plate}.",
                            ];
                        }
                    }

                    // Solo procesar el documento más reciente por tipo
                    $latestDocs = $vehicle->vehicleDocuments
                        ->groupBy('document_type')
                        ->map(fn ($docs) => $docs->sortByDesc('expiry_date')->first());

                    /** @var VehicleDocument $document */
                    foreach ($latestDocs as $document) {
                        $expiryDate = $document->expiry_date;

                        if (! $expiryDate) {
                            continue;
                        }

                        $vehicleInfo = [
                            'vehicle_uuid' => $vehicle->uuid,
                            'vehicle_license_plate' => $vehicle->vehicle_license_plate,
                            'third_party_uuid' => $vehicle->third_party_uuid,
                        ];

                        if ($expiryDate->lt($today)) {
                            $currentStatus = strtoupper($document->status ?? '');

                            $newStatus = null;
                            if ($currentStatus === 'VIGENTE') {
                                $newStatus = 'NO VIGENTE';
                            } elseif ($currentStatus === 'SI') {
                                $newStatus = 'NO';
                            }

                            if ($newStatus && $currentStatus !== $newStatus) {
                                try {
                                    $document->status = $newStatus;
                                    $document->save();
                                } catch (\Exception $e) {
                                    Logger::warning('No se pudo actualizar el estado del documento '.$document->uuid.': '.$e->getMessage());
                                }
                            }

                            $expired[] = array_merge($vehicleInfo, [
                                'document_uuid' => $document->uuid,
                                'document_type' => $document->document_type,
                                'expiry_date' => $expiryDate->toDateString(),
                                'date' => $expiryDate->toDateTimeString(),
                                'status' => $newStatus ?? $document->status,
                                'message' => "El documento '{$document->document_type}' del vehículo {$vehicle->vehicle_license_plate} está VENCIDO desde el ".$expiryDate->format('d/m/Y').'. Favor registrar el nuevo documento.',
                            ]);
                        } elseif ($expiryDate->lte($alertDate)) {
                            $daysLeft = (int) $today->diffInDays($expiryDate, false);

                            $expiringSoon[] = array_merge($vehicleInfo, [
                                'document_uuid' => $document->uuid,
                                'document_type' => $document->document_type,
                                'expiry_date' => $expiryDate->toDateString(),
                                'date' => $expiryDate->toDateTimeString(),
                                'days_left' => $daysLeft,
                                'status' => $document->status,
                                'message' => "El documento '{$document->document_type}' del vehículo "
                                    ."{$vehicle->vehicle_license_plate} vence en {$daysLeft} día(s) "
                                    .'('.$expiryDate->format('d/m/Y').').',
                            ]);
                        }
                    }
                }
            });

            return [
                'expired' => $expired,
                'expiring_soon' => $expiringSoon,
                'missing' => $missing,
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@notificationsForVehicleDocuments: '.$e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // TARJETAS DE OPERACIÓN
    // =========================================================================

    /**
     * Método notificationsForOperationCards.
     */
    public function notificationsForOperationCards(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $today = Carbon::today();
            $alertDate = $today->copy()->addDays(self::EXPIRY_ALERT_DAYS);

            $vehiclesQuery = Vehicle::query()->with(['thirdParty', 'operationCards']);

            if ($companyUuid) {
                $vehiclesQuery->where('vehicles.company_uuid', $companyUuid);
            }

            $vehiclesQuery->when($thirdPartyUuid, function ($q) use ($thirdPartyUuid) {
                $q->where('vehicles.third_party_uuid', $thirdPartyUuid);
            });

            $expired = [];
            $expiringSoon = [];
            $missing = [];

            $vehiclesQuery->chunk(100, function ($vehicles) use (&$expired, &$expiringSoon, &$missing, $today, $alertDate) {
                foreach ($vehicles as $vehicle) {
                    if ($vehicle->operationCards->isEmpty()) {
                        $missing[] = [
                            'vehicle_uuid' => $vehicle->uuid,
                            'vehicle_license_plate' => $vehicle->vehicle_license_plate,
                            'third_party_uuid' => $vehicle->third_party_uuid,
                            'message' => "Falta registrar la Tarjeta de Operación para el vehículo {$vehicle->vehicle_license_plate}.",
                        ];

                        continue;
                    }

                    // Solo procesar la tarjeta de operación más reciente
                    $latestCard = $vehicle->operationCards->sortByDesc('expiration_date')->first();

                    if ($latestCard) {
                        $expirationDate = $latestCard->expiration_date;
                        if ($expirationDate) {
                            $vehicleInfo = [
                                'operation_card_uuid' => $latestCard->uuid,
                                'operating_card_number' => $latestCard->operating_card_number,
                                'vehicle_uuid' => $latestCard->vehicle_uuid,
                                'vehicle_license_plate' => $vehicle->vehicle_license_plate,
                                'third_party_uuid' => $vehicle->third_party_uuid,
                            ];

                            if ($expirationDate->lt($today)) {
                                $expired[] = array_merge($vehicleInfo, [
                                    'expiration_date' => $expirationDate->toDateString(),
                                    'status' => 0,
                                    'message' => "La tarjeta de operación #{$latestCard->operating_card_number} del vehículo {$vehicle->vehicle_license_plate} está VENCIDA. Favor registrar la nueva tarjeta.",
                                ]);
                            } elseif ($expirationDate->lte($alertDate)) {
                                $daysLeft = (int) $today->diffInDays($expirationDate, false);
                                $expiringSoon[] = array_merge($vehicleInfo, [
                                    'expiration_date' => $expirationDate->toDateString(),
                                    'days_left' => $daysLeft,
                                    'status' => $latestCard->status,
                                    'message' => "La tarjeta de operación #{$latestCard->operating_card_number} del vehículo {$vehicle->vehicle_license_plate} vence en {$daysLeft} día(s) (".$expirationDate->format('d/m/Y').').',
                                ]);
                            }
                        }
                    }
                }
            });

            return [
                'expired' => $expired,
                'expiring_soon' => $expiringSoon,
                'missing' => $missing,
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@notificationsForOperationCards: '.$e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // LICENCIAS DE CONDUCCIÓN
    // =========================================================================

    /**
     * Método notificationsForDriverLicenses.
     */
    public function notificationsForDriverLicenses(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $today = Carbon::today();
            $alertDate = $today->copy()->addDays(self::EXPIRY_ALERT_DAYS);

            $query = DriverLicense::query()
                ->with(['thirdParty:uuid,company_uuid,first_name,last_name,document_number']);

            if ($companyUuid) {
                $query->where('driver_licenses.company_uuid', $companyUuid);
            }

            $query->when($thirdPartyUuid, function ($q) use ($thirdPartyUuid) {
                $q->where('driver_licenses.third_party_uuid', $thirdPartyUuid);
            });

            $licenses = $query->get();

            // Solo procesar la licencia más reciente por conductor
            $latestLicenses = $licenses->groupBy('third_party_uuid')
                ->map(fn ($group) => $group->sortByDesc('expiration_date')->first());

            $expired = [];
            $expiringSoon = [];

            /** @var DriverLicense $license */
            foreach ($latestLicenses as $license) {
                $expirationDate = $license->expiration_date;

                if (! $expirationDate) {
                    continue;
                }

                $driverName = trim(
                    ($license->thirdParty->first_name ?? '').' '.
                        ($license->thirdParty->last_name ?? '')
                );

                $licenseInfo = [
                    'license_uuid' => $license->uuid,
                    'license_number' => $license->number,
                    'category' => $license->category,
                    'third_party_uuid' => $license->third_party_uuid,
                    'driver_name' => $driverName ?: null,
                ];

                if ($expirationDate->lt($today)) {
                    if ($license->status !== 'VENCIDA') {
                        $license->status = 'VENCIDA';
                        $license->save();
                    }

                    $expired[] = array_merge($licenseInfo, [
                        'expiration_date' => $expirationDate->toDateString(),
                        'status' => 'VENCIDA',
                        'message' => "La licencia #{$license->number} del conductor {$driverName} está VENCIDA. Favor registrar la nueva licencia.",
                    ]);
                } elseif ($expirationDate->lte($alertDate)) {
                    $daysLeft = (int) $today->diffInDays($expirationDate, false);

                    $expiringSoon[] = array_merge($licenseInfo, [
                        'expiration_date' => $expirationDate->toDateString(),
                        'days_left' => $daysLeft,
                        'status' => $license->status,
                        'message' => "La licencia #{$license->number} del conductor "
                            ."{$driverName} vence en {$daysLeft} día(s) "
                            .'('.$expirationDate->format('d/m/Y').').',
                    ]);
                }
            }

            return [
                'expired' => $expired,
                'expiring_soon' => $expiringSoon,
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@notificationsForDriverLicenses: '.$e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // PRIMERA RTM — COLOMBIA
    // =========================================================================

    /**
     * Método notificationsForFirstRTM.
     */
    public function notificationsForFirstRTM(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $now = Carbon::now();
            $alertDate = $now->copy()->addDays(self::RTM_ALERT_DAYS);

            $query = Vehicle::query()
                ->with(['thirdParty:uuid,company_uuid,first_name,last_name,trade_name'])
                ->whereNotNull('registration_date')
                ->whereDoesntHave('vehicleDocuments', function ($q) {
                    $q->where('document_type', 'RTM');
                });

            if ($companyUuid) {
                $query->where('vehicles.company_uuid', $companyUuid);
            }

            $query->when($thirdPartyUuid, function ($q) use ($thirdPartyUuid) {
                $q->where('vehicles.third_party_uuid', $thirdPartyUuid);
            });

            $vehicles = $query->get();

            $expired = [];
            $expiringSoon = [];

            /** @var Vehicle $vehicle */
            foreach ($vehicles as $vehicle) {
                $registrationDate = Carbon::parse($vehicle->registration_date);
                $firstRtmDue = $registrationDate->copy()->addYears(2);

                if ($firstRtmDue->lt($now->copy()->subYear())) {
                    continue;
                }

                $vehicleInfo = [
                    'vehicle_uuid' => $vehicle->uuid,
                    'vehicle_license_plate' => $vehicle->vehicle_license_plate,
                    'third_party_uuid' => $vehicle->third_party_uuid,
                    'registration_date' => $registrationDate->toDateString(),
                    'first_rtm_due_date' => $firstRtmDue->toDateString(),
                ];

                if ($firstRtmDue->lt($now)) {
                    $daysOverdue = (int) $firstRtmDue->diffInDays($now);

                    $expired[] = array_merge($vehicleInfo, [
                        'days_overdue' => $daysOverdue,
                        'message' => "La primera RTM del vehículo {$vehicle->vehicle_license_plate} está VENCIDA. Favor registrar el documento RTM.",
                    ]);
                } elseif ($firstRtmDue->lte($alertDate)) {
                    $daysLeft = (int) $now->diffInDays($firstRtmDue, false);

                    $expiringSoon[] = array_merge($vehicleInfo, [
                        'days_left' => $daysLeft,
                        'message' => "El vehículo {$vehicle->vehicle_license_plate} debe realizar "
                            ."su primera RTM el {$firstRtmDue->format('d/m/Y')} "
                            ."(en {$daysLeft} día(s)). "
                            ."Fecha de matrícula: {$registrationDate->format('d/m/Y')}.",
                    ]);
                }
            }

            return [
                'expired' => $expired,
                'expiring_soon' => $expiringSoon,
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@notificationsForFirstRTM: '.$e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // CONVENIOS
    // =========================================================================

    /**
     * Método notificationsForAgreements.
     */
    public function notificationsForAgreements(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $today = Carbon::today();
            $alertDate = $today->copy()->addDays(self::AGREEMENT_ALERT_DAYS);

            $query = BusinessCollaborationAgreement::query()
                ->with(['vehicle:uuid,vehicle_license_plate,third_party_uuid', 'vehicle.thirdParty:uuid,company_uuid']);

            if ($companyUuid) {
                $query->where('business_collaboration_agreements.company_uuid', $companyUuid);
            }

            $query->when($thirdPartyUuid, function ($q) use ($thirdPartyUuid) {
                $q->whereHas('vehicle', function ($sub) use ($thirdPartyUuid) {
                    $sub->where('third_party_uuid', $thirdPartyUuid);
                });
            });

            $agreements = $query->get();

            // Solo procesar el convenio más reciente por vehículo
            $latestAgreements = $agreements->groupBy('vehicle_uuid')
                ->map(fn ($group) => $group->sortByDesc('expiry_date')->first());

            $expired = [];
            $expiringSoon = [];

            /** @var BusinessCollaborationAgreement $agreement */
            foreach ($latestAgreements as $agreement) {
                $expirationDate = $agreement->expiry_date;

                if (! $expirationDate) {
                    continue;
                }

                $agreementInfo = [
                    'agreement_uuid' => $agreement->uuid,
                    'resolution_number' => $agreement->resolution_number,
                    'vehicle_uuid' => $agreement->vehicle_uuid,
                    'license_plate' => $agreement->vehicle->vehicle_license_plate ?? null,
                    'entity_name' => $agreement->contracting_entity_name,
                ];

                if ($expirationDate->lt($today)) {
                    if ($agreement->status !== 0) {
                        $agreement->status = 0;
                        $agreement->save();
                    }

                    $expired[] = array_merge($agreementInfo, [
                        'expiration_date' => $expirationDate->toDateString(),
                        'message' => "El convenio con {$agreement->contracting_entity_name} para el vehículo {$agreementInfo['license_plate']} está VENCIDO. Favor registrar el nuevo convenio.",
                    ]);
                } elseif ($expirationDate->lte($alertDate)) {
                    $daysLeft = (int) $today->diffInDays($expirationDate, false);

                    $expiringSoon[] = array_merge($agreementInfo, [
                        'expiration_date' => $expirationDate->toDateString(),
                        'days_left' => $daysLeft,
                        'message' => "El convenio con {$agreement->contracting_entity_name} para el vehículo "
                            ."{$agreementInfo['license_plate']} vence en {$daysLeft} día(s) "
                            .'('.$expirationDate->format('d/m/Y').').',
                    ]);
                }
            }

            return [
                'expired' => $expired,
                'expiring_soon' => $expiringSoon,
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@notificationsForAgreements: '.$e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // COBROS DE AFILIACIÓN / ADMINISTRACIÓN
    // =========================================================================

    /**
     * Método notificationsForAffiliateCharges.
     */
    public function notificationsForAffiliateCharges(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $today = Carbon::today();
            $alertDate = $today->copy()->addDays(self::EXPIRY_ALERT_DAYS);

            $query = AffiliateAdminCharge::query()
                ->with(['vehicle.thirdParty'])
                ->where('status', '!=', 'PAGADO');

            if ($companyUuid) {
                $query->where('affiliate_admin_charges.company_uuid', $companyUuid);
            }

            if ($thirdPartyUuid) {
                $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                    $q->where('third_party_uuid', $thirdPartyUuid);
                });
            }

            $charges = $query->get();
            $expired = [];
            $expiringSoon = [];

            foreach ($charges as $charge) {
                if (! $charge->due_date) {
                    continue;
                }

                $dueDate = Carbon::parse($charge->due_date);
                $vehiclePlate = $charge->vehicle?->vehicle_license_plate ?? 'N/A';

                if ($dueDate->lt($today)) {
                    $expired[] = [
                        'charge_uuid' => $charge->uuid,
                        'vehicle_license_plate' => $vehiclePlate,
                        'amount' => $charge->amount,
                        'due_date' => $dueDate->toDateString(),
                        'date' => $dueDate->toDateTimeString(),
                        'status' => 'VENCIDO',
                        'message' => "El cobro de administración por \${$charge->amount} del vehículo {$vehiclePlate} está VENCIDO desde el ".$dueDate->format('d/m/Y').'.',
                    ];
                } elseif ($dueDate->lte($alertDate)) {
                    $daysLeft = (int) $today->diffInDays($dueDate, false);
                    $expiringSoon[] = [
                        'charge_uuid' => $charge->uuid,
                        'vehicle_license_plate' => $vehiclePlate,
                        'amount' => $charge->amount,
                        'due_date' => $dueDate->toDateString(),
                        'date' => $dueDate->toDateTimeString(),
                        'status' => 'PRÓXIMO',
                        'message' => "El cobro de administración por \${$charge->amount} del vehículo {$vehiclePlate} vence en {$daysLeft} día(s) (".$dueDate->format('d/m/Y').').',
                    ];
                }
            }

            return [
                'expired' => $expired,
                'expiring_soon' => $expiringSoon,
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@notificationsForAffiliateCharges: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método getAllNotifications.
     */
    public function getAllNotifications(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $vehicleDocs = $this->notificationsForVehicleDocuments($companyUuid, $thirdPartyUuid);
            $operationCards = $this->notificationsForOperationCards($companyUuid, $thirdPartyUuid);
            $driverLicenses = $this->notificationsForDriverLicenses($companyUuid, $thirdPartyUuid);
            $firstRtm = $this->notificationsForFirstRTM($companyUuid, $thirdPartyUuid);
            $agreements = $this->notificationsForAgreements($companyUuid, $thirdPartyUuid);
            $affiliateCharges = $this->notificationsForAffiliateCharges($companyUuid, $thirdPartyUuid);
            $preventativeMaintenance = $this->notificationsForPreventativeMaintenance($companyUuid, $thirdPartyUuid);
            $socialSecurity = $this->notificationsForSocialSecurity($companyUuid, $thirdPartyUuid);

            $pmExpiredCount = 0;
            $pmExpiringCount = 0;
            foreach ($preventativeMaintenance as $alert) {
                if ($alert['status'] === 'VENCIDO') {
                    $pmExpiredCount++;
                } else {
                    $pmExpiringCount++;
                }
            }

            $ssExpiredCount = count($socialSecurity['mora'] ?? []);

            $summary = [
                'total_expired' => count($vehicleDocs['expired'] ?? []) +
                    count($operationCards['expired'] ?? []) +
                    count($driverLicenses['expired'] ?? []) +
                    count($firstRtm['expired'] ?? []) +
                    count($agreements['expired'] ?? []) +
                    count($affiliateCharges['expired'] ?? []) +
                    $pmExpiredCount +
                    $ssExpiredCount,
                'total_expiring_soon' => count($vehicleDocs['expiring_soon'] ?? []) +
                    count($operationCards['expiring_soon'] ?? []) +
                    count($driverLicenses['expiring_soon'] ?? []) +
                    count($firstRtm['expiring_soon'] ?? []) +
                    count($agreements['expiring_soon'] ?? []) +
                    count($affiliateCharges['expiring_soon'] ?? []) +
                    $pmExpiringCount,
                'total_missing' => count($vehicleDocs['missing'] ?? []) +
                    count($operationCards['missing'] ?? []),
            ];

            return [
                'vehicle_documents' => $vehicleDocs,
                'operation_cards' => $operationCards,
                'driver_licenses' => $driverLicenses,
                'first_rtm' => $firstRtm,
                'agreements' => $agreements,
                'affiliate_charges' => $affiliateCharges,
                'preventative_maintenance' => $preventativeMaintenance,
                'social_security' => $socialSecurity,
                'summary' => $summary,
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@getAllNotifications: '.$e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // ACTUALIZACIÓN AUTOMÁTICA
    // =========================================================================

    /**
     * Método autoUpdateExpiredStatuses.
     */
    public function autoUpdateExpiredStatuses(): array
    {
        try {
            $now = Carbon::now();

            // 1. Vehicle Documents ('VIGENTE' -> 'NO VIGENTE')
            $docsVigente = VehicleDocument::query()
                ->where('expiry_date', '<', $now, 'and')
                ->where('status', '=', 'VIGENTE', 'and')
                ->update(['status' => 'NO VIGENTE']);

            // 2. Vehicle Documents ('SI' -> 'NO')
            $docsSi = VehicleDocument::query()
                ->where('expiry_date', '<', $now, 'and')
                ->where('status', '=', 'SI', 'and')
                ->update(['status' => 'NO']);

            // 3. Operation Cards (status -> 0)
            $cards = OperationCard::query()
                ->where('expiration_date', '<', $now, 'and')
                ->where('status', '!=', 0, 'and')
                ->update(['status' => 0]);

            // 4. Driver Licenses (status -> 'VENCIDA')
            $licenses = DriverLicense::query()
                ->where('expiration_date', '<', $now, 'and')
                ->where('status', '!=', 'VENCIDA', 'and')
                ->update(['status' => 'VENCIDA']);

            // 5. Agreements (status -> 0)
            $agreementsCount = BusinessCollaborationAgreement::query()
                ->where('expiry_date', '<', $now, 'and')
                ->where('status', '!=', 0, 'and')
                ->update(['status' => 0]);

            // 6. Seguridad Social
            $currentMonth = $now->copy()->startOfMonth();
            $ssUpdated = 0;

            // 6a. Corregir estados de registros pagados
            $ssUpdated += SocialSecurityContribution::where('billing_period', '<', $currentMonth)
                ->whereNotNull('payment_date')
                ->where('status', '!=', 'PAGADO Y FINALIZADO')
                ->update(['status' => 'PAGADO Y FINALIZADO']);

            $ssUpdated += SocialSecurityContribution::where('billing_period', $currentMonth)
                ->whereNotNull('payment_date')
                ->where('status', '!=', 'PAGADO Y EN CURSO')
                ->update(['status' => 'PAGADO Y EN CURSO']);

            // 6b. EN MORA: períodos anteriores sin pago cuando no hay mes actual pagado
            $thirdPartyUuids = SocialSecurityContribution::query()
                ->select('third_party_uuid')
                ->distinct()
                ->pluck('third_party_uuid');

            foreach ($thirdPartyUuids as $tpuuid) {
                $hasCurrentPaid = SocialSecurityContribution::where('third_party_uuid', $tpuuid)
                    ->where('billing_period', $currentMonth)
                    ->whereNotNull('payment_date')
                    ->exists();

                if (!$hasCurrentPaid) {
                    $affected = SocialSecurityContribution::where('third_party_uuid', $tpuuid)
                        ->where('billing_period', '<', $currentMonth)
                        ->whereNull('payment_date')
                        ->where('status', '!=', 'EN MORA')
                        ->update(['status' => 'EN MORA']);
                    $ssUpdated += $affected;
                }
            }

            return [
                'message' => 'Actualización automática completada',
                'details' => [
                    'updated_vehicle_documents' => $docsVigente + $docsSi,
                    'updated_operation_cards' => $cards,
                    'updated_driver_licenses' => $licenses,
                    'updated_agreements' => $agreementsCount,
                    'updated_social_security' => $ssUpdated,
                ],
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@autoUpdateExpiredStatuses: '.$e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // MÉTODO LEGACY (compatibilidad hacia atrás)
    // =========================================================================

    /**
     * Método isDriverLicenseExpired.
     */
    public function isDriverLicenseExpired(string $uuid): array
    {
        try {
            $license = DriverLicense::query()->where('third_party_uuid', '=', $uuid, 'and')->first();

            if (! $license) {
                return [
                    'is_expired' => true,
                    'message' => 'El conductor no tiene licencia registrada',
                ];
            }

            $now = Carbon::now();
            $expiryDate = $license->expiration_date;
            $isExpired = $expiryDate->lt($now);
            $daysLeft = $isExpired ? 0 : (int) $now->diffInDays($expiryDate, false);
            $expiringSoon = ! $isExpired && $expiryDate->lte($now->copy()->addDays(self::EXPIRY_ALERT_DAYS));

            return [
                'is_expired' => $isExpired,
                'expiring_soon' => $expiringSoon,
                'days_left' => $daysLeft,
                'expiry_date' => $expiryDate->toDateString(),
                'message' => $isExpired
                    ? 'La licencia está VENCIDA desde '.$expiryDate->format('d/m/Y').'.'
                    : ($expiringSoon
                        ? "La licencia vence en {$daysLeft} día(s) (".$expiryDate->format('d/m/Y').').'
                        : 'La licencia está vigente.'),
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@isDriverLicenseExpired: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método notificationsForPendingInspections.
     */
    public function notificationsForPendingInspections(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $today = Carbon::today();
            $vehiclesQuery = Vehicle::query();

            if ($companyUuid) {
                $vehiclesQuery->where('vehicles.company_uuid', $companyUuid);
            }

            $vehiclesQuery->when($thirdPartyUuid, function ($q) use ($thirdPartyUuid) {
                $q->where('vehicles.third_party_uuid', $thirdPartyUuid);
            });

            $vehicles = $vehiclesQuery->get();
            $vehicleUuids = $vehicles->pluck('uuid')->toArray();

            if (empty($vehicleUuids)) {
                return [];
            }

            // Consultar todas las inspecciones de hoy para estos vehículos
            $inspectionsToday = DB::table('vehicle_inspections')
                ->whereIn('vehicle_uuid', $vehicleUuids)
                ->whereDate('inspection_date', $today)
                ->pluck('vehicle_uuid')
                ->toArray();

            $pending = [];

            foreach ($vehicles as $vehicle) {
                if (! in_array($vehicle->uuid, $inspectionsToday)) {
                    $pending[] = [
                        'vehicle_uuid' => $vehicle->uuid,
                        'vehicle_license_plate' => $vehicle->vehicle_license_plate,
                        'message' => "El vehículo {$vehicle->vehicle_license_plate} no cuenta con una inspección preoperacional registrada para el día de hoy. Por favor registrarla de forma obligatoria.",
                    ];
                }
            }

            return $pending;
        } catch (\Exception $e) {
            Logger::error('NotificationsService@notificationsForPendingInspections: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método notificationsForPreventativeMaintenance.
     */
    public function notificationsForPreventativeMaintenance(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $vehiclesQuery = Vehicle::query();

            if ($companyUuid) {
                $vehiclesQuery->where('company_uuid', $companyUuid);
            }

            if ($thirdPartyUuid) {
                $vehiclesQuery->where('third_party_uuid', $thirdPartyUuid);
            }

            $alerts = [];

            // Definición del plan de mantenimiento preventivo
            $maintenanceCatalog = [
                10000 => [
                    'title' => 'Mantenimiento 5,000 km - 10,000 km',
                    'tasks' => 'Cambio de aceite de motor y filtro de aceite, Rotación de llantas',
                    'parts' => 'Inspección de niveles de líquidos (frenos, refrigerante)',
                ],
                30000 => [
                    'title' => 'Mantenimiento 15,000 km - 30,000 km',
                    'tasks' => 'Cambio de filtro de aire del motor y filtro de cabina, Cambio de filtro de combustible',
                    'parts' => 'Limpieza y ajuste de frenos',
                ],
                60000 => [
                    'title' => 'Mantenimiento 30,000 km - 60,000 km',
                    'tasks' => 'Cambio de líquido de frenos y refrigerante, Inspección de bujías (cambio si son convencionales)',
                    'parts' => 'Cambio de fluido de transmisión manual',
                ],
                100000 => [
                    'title' => 'Mantenimiento 60,000 km - 100,000 km',
                    'tasks' => 'Reemplazo de la correa (banda) de distribución, Inspección y posible cambio de amortiguadores y batería',
                    'parts' => 'Revisión de pastillas de freno y discos',
                ],
            ];

            $vehiclesQuery->chunk(100, function ($vehicles) use (&$alerts, $maintenanceCatalog) {
                foreach ($vehicles as $vehicle) {
                    // Kilometraje actual del vehículo (mayor kilometraje reportado en sus inspecciones)
                    $currentMileage = DB::table('vehicle_inspections')
                        ->where('vehicle_uuid', $vehicle->uuid)
                        ->max('mileage');

                    if (is_null($currentMileage) || $currentMileage <= 0) {
                        continue;
                    }

                    // Kilometraje del último mantenimiento preventivo realizado
                    $lastMaintenanceMileage = DB::table('maintenance')
                        ->where('vehicle_uuid', $vehicle->uuid)
                        ->where('maintenance_type', 'PREVENTIVA')
                        ->where('status', '!=', 'Anulado')
                        ->max('mileage') ?? 0;

                    foreach ($maintenanceCatalog as $interval => $details) {
                        // 1. Encontrar todos los hitos pasados en el rango (lastMaintenanceMileage, currentMileage]
                        $startK = (int) (floor($lastMaintenanceMileage / $interval) + 1);
                        $endK = (int) (floor($currentMileage / $interval));

                        for ($k = $startK; $k <= $endK; $k++) {
                            $milestone = $k * $interval;
                            $kmOverdue = $currentMileage - $milestone;
                            $alerts[] = [
                                'vehicle_uuid' => $vehicle->uuid,
                                'vehicle_license_plate' => $vehicle->vehicle_license_plate,
                                'milestone' => $milestone,
                                'interval' => $interval,
                                'status' => 'VENCIDO',
                                'title' => "Mantenimiento Preventivo {$milestone} km - VENCIDO",
                                'message' => "El vehículo {$vehicle->vehicle_license_plate} superó el hito de {$milestone} km por {$kmOverdue} km sin registrar este mantenimiento obligatorio. Tareas: {$details['tasks']}. Piezas a revisar/cambiar: {$details['parts']}.",
                                'days_left' => -$kmOverdue,
                                'km_left' => 0,
                            ];
                        }

                        // 2. Encontrar el siguiente hito (futuro)
                        $nextMilestone = (int) (ceil(($currentMileage + 1) / $interval) * $interval);
                        if ($nextMilestone > $lastMaintenanceMileage) {
                            $kmDifference = $nextMilestone - $currentMileage;
                            if ($kmDifference <= 500) {
                                $alerts[] = [
                                    'vehicle_uuid' => $vehicle->uuid,
                                    'vehicle_license_plate' => $vehicle->vehicle_license_plate,
                                    'milestone' => $nextMilestone,
                                    'interval' => $interval,
                                    'status' => 'PROXIMO',
                                    'title' => "Mantenimiento Preventivo {$nextMilestone} km - PRÓXIMO",
                                    'message' => "El vehículo {$vehicle->vehicle_license_plate} se encuentra a {$kmDifference} km de alcanzar el hito de {$nextMilestone} km. Tareas recomendadas: {$details['tasks']}. Piezas a revisar/cambiar: {$details['parts']}.",
                                    'days_left' => $kmDifference,
                                    'km_left' => $kmDifference,
                                ];
                            }
                        }
                    }
                }
            });

            return $alerts;
        } catch (\Exception $e) {
            Logger::error('NotificationsService@notificationsForPreventativeMaintenance: '.$e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // SEGURIDAD SOCIAL
    // =========================================================================

    /**
     * Detecta aportes de seguridad social en estado de mora.
     */
    public function notificationsForSocialSecurity(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        try {
            $currentMonth = Carbon::now()->startOfMonth();

            $query = SocialSecurityContribution::query()
                ->with(['thirdParty:uuid,company_uuid,first_name,last_name,trade_name']);

            if ($thirdPartyUuid) {
                $query->where('third_party_uuid', $thirdPartyUuid);
            }

            // Si hay companyUuid, filtrar por terceros de esa empresa
            if ($companyUuid) {
                $query->whereHas('thirdParty', function ($q) use ($companyUuid) {
                    $q->where('company_uuid', $companyUuid);
                });
            }

            $contributions = $query->where('status', 'EN MORA')->get();

            $mora = [];

            foreach ($contributions as $contribution) {
                $thirdParty = $contribution->thirdParty;
                if (! $thirdParty) {
                    continue;
                }

                $name = trim(
                    ($thirdParty->first_name ?? '').' '.
                    ($thirdParty->last_name ?? '')
                ) ?: ($thirdParty->trade_name ?? 'Desconocido');

                $period = $contribution->billing_period
                    ? Carbon::parse($contribution->billing_period)->format('m/Y')
                    : 'desconocido';

                $mora[] = [
                    'third_party_uuid' => $contribution->third_party_uuid,
                    'third_party_name' => $name,
                    'contribution_uuid' => $contribution->uuid,
                    'billing_period' => $contribution->billing_period?->toDateString(),
                    'days_left' => null,
                    'status' => 'EN MORA',
                    'title' => "Seguridad Social en Mora - {$name}",
                    'message' => "El aporte de seguridad social del período {$period} para {$name} está en estado de MORA. Período: {$period}.",
                ];
            }

            return [
                'mora' => $mora,
            ];
        } catch (\Exception $e) {
            Logger::error('NotificationsService@notificationsForSocialSecurity: '.$e->getMessage());
            throw $e;
        }
    }
}
