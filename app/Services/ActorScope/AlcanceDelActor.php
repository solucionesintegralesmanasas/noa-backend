<?php

declare(strict_types=1);

namespace App\Services\ActorScope;

use App\Models\AffiliateAdminCharge;
use App\Models\BusinessCollaborationAgreement;
use App\Models\DriverLicense;
use App\Models\DriverLocation;
use App\Models\DriverLocationSession;
use App\Models\OperationCard;
use App\Models\OwnerDriver;
use App\Models\ProjectDriverVehicle;
use App\Models\ThirdParty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Affiliates\AffiliateDriverService;
use App\Services\Fleet\VehicleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-013: Servicio unificado de resolución y aplicación del alcance de visibilidad
 * para los diferentes actores del sistema (Empresa, Sede Operativa, Afiliado y Conductor).
 *
 * Registrado como servicio scoped en el contenedor de Laravel.
 * Resuelve y memoriza en memoria de la petición el contexto del usuario autenticado
 * en una única consulta inicial y aplica reglas de Falla Cerrada (Fail-Closed).
 */
class AlcanceDelActor
{
    public const TABLAS_AISLADAS_POR_AFILIADO = [
        'vehicles',
        'vehicle_documents',
        'operation_cards',
        'vehicle_inspections',
        'owners_drivers',
        'third_parties',
        'contractors',
        'fuec',
        'procedures',
    ];

    private bool $contextoResuelto = false;

    private ?User $usuario = null;

    private ?string $companyUuid = null;

    private ?string $branchUuid = null;

    private ?string $thirdPartyUuid = null;

    private ?string $affiliateUuid = null;

    /** @var array<int, string> */
    private array $roles = [];

    private bool $esSuperadmin = false;

    private bool $esAdminEmpresa = false;

    private bool $esAfiliado = false;

    private bool $esConductor = false;

    private bool $esSucursal = false;

    private bool $esSucursalRestringida = false;

    private ?string $currentContextFingerprint = null;

    /** @var array<int, string>|null */
    private ?array $vehiculosAfiliado = null;

    /** @var array<int, string>|null */
    private ?array $conductoresAfiliado = null;

    /** @var array<int, string>|null */
    private ?array $vehiculosConductor = null;

    /** @var array<int, string>|null */
    private ?array $conductoresRastreo = null;

    /** @var array<int, string>|null */
    private ?array $licenciasOwnersDrivers = null;

    /**
     * Limpia la memoria de asignaciones y el contexto resuelto.
     */
    public function limpiarCache(): void
    {
        $this->contextoResuelto = false;
        $this->usuario = null;
        $this->companyUuid = null;
        $this->branchUuid = null;
        $this->thirdPartyUuid = null;
        $this->affiliateUuid = null;
        $this->roles = [];
        $this->esSuperadmin = false;
        $this->esAdminEmpresa = false;
        $this->esAfiliado = false;
        $this->esConductor = false;
        $this->esSucursal = false;
        $this->esSucursalRestringida = false;
        $this->vehiculosAfiliado = null;
        $this->conductoresAfiliado = null;
        $this->vehiculosConductor = null;
        $this->conductoresRastreo = null;
        $this->licenciasOwnersDrivers = null;
        $this->currentContextFingerprint = null;
    }

    /**
     * Resuelve y memoriza el contexto del actor una sola vez por ciclo de petición.
     */
    public function resolverContexto(?User $usuario = null, ?string $companyUuidExplicit = null): self
    {
        $targetUser = $usuario ?? Auth::user();
        $targetUserId = $targetUser?->id;
        $mismoUsuario = $this->usuario !== null && $targetUserId !== null && $this->usuario->id === $targetUserId;
        $mismaEmpresa = ($companyUuidExplicit === null && $this->companyUuid !== null)
            || ($companyUuidExplicit !== null && $this->companyUuid === $companyUuidExplicit);

        if ($this->contextoResuelto && $mismoUsuario && $mismaEmpresa) {
            return $this;
        }

        if ($usuario !== null || $companyUuidExplicit !== null) {
            $this->limpiarCache();
        } elseif ($this->contextoResuelto) {
            return $this;
        }

        $this->usuario = $targetUser;

        if (! $this->usuario) {
            $this->contextoResuelto = true;

            return $this;
        }

        $rawRoles = [];
        if ($this->usuario->relationLoaded('roles')) {
            $rawRoles = $this->usuario->roles->pluck('name')->all();
        } elseif (method_exists($this->usuario, 'getRoleNames')) {
            $rawRoles = $this->usuario->getRoleNames()->all();
        }

        $this->roles = array_values(array_unique(array_map(function ($r) {
            return method_exists(User::class, 'normalizeRoleName')
                ? User::normalizeRoleName($r)
                : strtoupper(trim((string) $r));
        }, $rawRoles)));

        $this->esSuperadmin = in_array('SUPERADMIN', $this->roles, true);
        $this->esAdminEmpresa = in_array('ADMIN_EMPRESA', $this->roles, true);
        $this->esAfiliado = in_array('AFILIADO', $this->roles, true);
        $this->esConductor = in_array('CONDUCTOR', $this->roles, true);
        $this->esSucursal = in_array('SUCURSAL', $this->roles, true);

        $solicitada = $companyUuidExplicit
            ?? request()->attributes->get('current_company_uuid')
            ?? request()->header('X-Company-UUID')
            ?? request()->header('X-Tenant-ID')
            ?? request()->input('company_uuid')
            ?? session('current_company_uuid');

        if ($this->esSuperadmin) {
            $this->companyUuid = $solicitada ?? $this->usuario->companies()->first()?->uuid;
            $this->thirdPartyUuid = request()->attributes->get('current_third_party_uuid');
            $this->branchUuid = request()->attributes->get('current_branch_uuid');
            $this->affiliateUuid = null;
            $this->esSucursalRestringida = false;
            $this->contextoResuelto = true;

            return $this;
        }

        // Una sola consulta para resolver la tupla {empresa, sucursal, tercero}
        $queryContexto = DB::table('company_user')
            ->leftJoin('third_parties', 'company_user.third_party_uuid', '=', 'third_parties.uuid')
            ->where('company_user.user_id', $this->usuario->id)
            ->where('company_user.is_active', 1);

        if ($solicitada) {
            $queryContexto->where('company_user.company_uuid', $solicitada);
        }

        $info = $queryContexto->select([
            'company_user.company_uuid',
            'company_user.third_party_uuid',
            'third_parties.branch_uuid',
        ])->first();

        // Si no se encontró con la solicitada y no era explícita por parámetro de prueba, fallback a la primera
        if (! $info && ! $solicitada) {
            $info = DB::table('company_user')
                ->leftJoin('third_parties', 'company_user.third_party_uuid', '=', 'third_parties.uuid')
                ->where('company_user.user_id', $this->usuario->id)
                ->where('company_user.is_active', 1)
                ->select([
                    'company_user.company_uuid',
                    'company_user.third_party_uuid',
                    'third_parties.branch_uuid',
                ])->first();
        }

        // Fail-Closed: si no existe registro en company_user para el usuario, $this->companyUuid es NULL.
        // NUNCA asignar $solicitada si no está autorizada en company_user para el usuario.
        $this->companyUuid = $info?->company_uuid;
        $this->thirdPartyUuid = request()->attributes->get('current_third_party_uuid') ?? $info?->third_party_uuid;

        // Restricción por sucursal
        if (config('authorization.branch_isolation', true) && $this->esSucursal && ! ($this->esSuperadmin || $this->esAdminEmpresa)) {
            $this->esSucursalRestringida = true;
            $this->branchUuid = (string) (request()->attributes->get('current_branch_uuid') ?? $info?->branch_uuid ?? '');
        } else {
            $this->esSucursalRestringida = false;
            $this->branchUuid = null;
        }

        // Resolución de afiliado (inmediata para AFILIADO, lazy para CONDUCTOR vía affiliateUuid())
        if ($this->esAfiliado) {
            $this->affiliateUuid = $this->thirdPartyUuid;
        } else {
            $this->affiliateUuid = null;
        }

        $this->contextoResuelto = true;

        return $this;
    }

    /**
     * Obtiene el contexto completo del actor.
     *
     * @return array{empresa: ?string, sucursal: ?string, tercero: ?string, roles: array<int, string>}
     */
    public function contexto(): array
    {
        $this->asegurarContexto();

        return [
            'empresa' => $this->companyUuid,
            'sucursal' => $this->branchUuid,
            'tercero' => $this->thirdPartyUuid,
            'roles' => $this->roles,
        ];
    }

    public function companyUuid(): ?string
    {
        $this->asegurarContexto();

        return $this->companyUuid;
    }

    public function branchUuid(): ?string
    {
        $this->asegurarContexto();

        return $this->branchUuid;
    }

    public function thirdPartyUuid(): ?string
    {
        $this->asegurarContexto();

        return $this->thirdPartyUuid;
    }

    public function affiliateUuid(): ?string
    {
        $this->asegurarContexto();

        if ($this->affiliateUuid === null && $this->esConductor && $this->thirdPartyUuid) {
            $this->affiliateUuid = request()->attributes->get('current_affiliate_uuid')
                ?? app(AffiliateDriverService::class)->primaryAffiliateUuid($this->thirdPartyUuid);
        }

        return $this->affiliateUuid;
    }

    public function esSuperadmin(): bool
    {
        $this->asegurarContexto();

        return $this->esSuperadmin;
    }

    public function esAdminEmpresa(): bool
    {
        $this->asegurarContexto();

        return $this->esAdminEmpresa;
    }

    public function esAfiliado(): bool
    {
        $this->asegurarContexto();

        return $this->esAfiliado;
    }

    public function esConductor(): bool
    {
        $this->asegurarContexto();

        return $this->esConductor;
    }

    public function esSucursalRestringida(): bool
    {
        $this->asegurarContexto();

        return $this->esSucursalRestringida;
    }

    // =========================================================================
    // MEMORIZACIÓN DE ASIGNACIONES (1 sola consulta por recurso por ciclo HTTP)
    // =========================================================================

    /**
     * Obtiene los UUIDs de vehículos del afiliado.
     *
     * @return array<int, string>
     */
    public function obtenerVehiculosDeAfiliado(?string $affiliateUuid = null): array
    {
        $this->asegurarContexto();
        $targetAffiliate = $affiliateUuid ?? $this->affiliateUuid;

        if (! $targetAffiliate) {
            return [];
        }

        $esContextoActual = ($affiliateUuid === null || $affiliateUuid === $this->affiliateUuid);

        if ($esContextoActual && $this->vehiculosAfiliado !== null) {
            return $this->vehiculosAfiliado;
        }

        $vehiculos = Vehicle::withoutGlobalScopes()
            ->when($this->companyUuid, fn($q) => $q->where('vehicles.company_uuid', $this->companyUuid))
            ->where(function ($q) use ($targetAffiliate) {
                $q->where('vehicles.third_party_uuid', $targetAffiliate)
                    ->orWhereHas('owners', fn($o) => $o->where('third_party_uuid', $targetAffiliate));
            })
            ->pluck('uuid')
            ->values()
            ->all();

        if ($esContextoActual) {
            $this->vehiculosAfiliado = $vehiculos;
        }

        return $vehiculos;
    }

    /**
     * Obtiene los conductores vinculados al afiliado (vía pivote o legado).
     *
     * @return array<int, string>
     */
    public function obtenerConductoresDeAfiliado(?string $affiliateUuid = null): array
    {
        $this->asegurarContexto();
        $targetAffiliate = $affiliateUuid ?? $this->affiliateUuid;

        if (! $targetAffiliate) {
            return [];
        }

        $esContextoActual = ($affiliateUuid === null || $affiliateUuid === $this->affiliateUuid);

        if ($esContextoActual && $this->conductoresAfiliado !== null) {
            return $this->conductoresAfiliado;
        }

        $conductores = app(AffiliateDriverService::class)->driverUuidsForAffiliate($targetAffiliate);

        if ($esContextoActual) {
            $this->conductoresAfiliado = $conductores;
        }

        return $conductores;
    }

    /**
     * Obtiene los vehículos asignados al conductor según proyectos y FUEC.
     *
     * @return array<int, string>
     */
    public function obtenerVehiculosDeConductor(?string $driverThirdPartyUuid = null): array
    {
        $this->asegurarContexto();
        $targetDriver = $driverThirdPartyUuid ?? $this->thirdPartyUuid;

        if (! $targetDriver) {
            return [];
        }

        $esContextoActual = ($driverThirdPartyUuid === null || $driverThirdPartyUuid === $this->thirdPartyUuid);

        if ($esContextoActual && $this->vehiculosConductor !== null) {
            return $this->vehiculosConductor;
        }

        $vehiculos = app(VehicleService::class)->getVehicleUuidsForConductor($targetDriver);

        if ($esContextoActual) {
            $this->vehiculosConductor = $vehiculos;
        }

        return $vehiculos;
    }

    /**
     * Obtiene las licencias legadas vinculadas al afiliado mediante owners_drivers.
     *
     * @return array<int, string>
     */
    public function obtenerLicenciasDeOwnersDrivers(?string $affiliateUuid = null): array
    {
        $this->asegurarContexto();
        $targetAffiliate = $affiliateUuid ?? $this->affiliateUuid;

        if (! $targetAffiliate) {
            return [];
        }

        $esContextoActual = ($affiliateUuid === null || $affiliateUuid === $this->affiliateUuid);

        if ($esContextoActual && $this->licenciasOwnersDrivers !== null) {
            return $this->licenciasOwnersDrivers;
        }

        $licencias = OwnerDriver::withoutGlobalScopes()
            ->where('third_party_uuid', $targetAffiliate)
            ->pluck('driver_license_uuid')
            ->filter()
            ->values()
            ->all();

        if ($esContextoActual) {
            $this->licenciasOwnersDrivers = $licencias;
        }

        return $licencias;
    }

    /**
     * Obtiene el alcance de rastreo para el actor.
     * null = sin restricción (SUPERADMIN / ADMIN_EMPRESA / Empleados sin rol AFILIADO ni CONDUCTOR).
     *
     * @return array{conductores: array<int, string>, vehiculos: array<int, string>}|null
     */
    public function obtenerAlcanceRastreo(): ?array
    {
        $this->asegurarContexto();

        if (! $this->usuario || $this->esSuperadmin || $this->esAdminEmpresa) {
            return null;
        }

        // Caso 1: Afiliado
        if ($this->esAfiliado) {
            if (! $this->affiliateUuid) {
                return ['conductores' => [], 'vehiculos' => []];
            }

            if ($this->conductoresRastreo === null) {
                $vehiculos = $this->obtenerVehiculosDeAfiliado();
                $vinculados = $this->obtenerConductoresDeAfiliado();
                $asignados = empty($vehiculos)
                    ? collect()
                    : ProjectDriverVehicle::withoutGlobalScopes()
                    ->whereIn('vehicle_uuid', $vehiculos)
                    ->pluck('third_party_uuid');

                $this->conductoresRastreo = collect($vinculados)
                    ->merge($asignados)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            return [
                'conductores' => $this->conductoresRastreo,
                'vehiculos' => $this->obtenerVehiculosDeAfiliado(),
            ];
        }

        // Caso 2: Conductor puro
        if ($this->esConductor) {
            if (! $this->thirdPartyUuid) {
                return ['conductores' => [], 'vehiculos' => []];
            }

            return [
                'conductores' => [$this->thirdPartyUuid],
                'vehiculos' => $this->obtenerVehiculosDeConductor(),
            ];
        }

        // Caso 3: Sucursal restringida
        if ($this->esSucursalRestringida) {
            if (! $this->branchUuid) {
                return ['conductores' => [], 'vehiculos' => []];
            }

            $conductoresSede = DB::table('third_parties')
                ->where('branch_uuid', $this->branchUuid)
                ->pluck('uuid')
                ->all();

            $vehiculosSede = DB::table('vehicles_branches')
                ->where('branch_uuid', $this->branchUuid)
                ->where('is_active', 1)
                ->pluck('vehicle_uuid')
                ->all();

            return [
                'conductores' => $conductoresSede,
                'vehiculos' => $vehiculosSede,
            ];
        }

        return null;
    }

    /**
     * Valida si el conductor solicitado es visible/rastreable por el actor.
     */
    public function permiteConductor(string $conductorUuid): bool
    {
        $alcance = $this->obtenerAlcanceRastreo();

        return $alcance === null || in_array($conductorUuid, $alcance['conductores'], true);
    }

    // =========================================================================
    // PREDICADOS SQL REUTILIZABLES (Visibilidad sobre Eloquent Builder)
    // =========================================================================

    /**
     * Aplica el predicado de visibilidad para la consulta de VEHÍCULOS.
     */
    public function aplicarAVehiculos(Builder $query, ?string $companyUuid = null): Builder
    {
        $this->asegurarContexto();

        if (! $this->usuario) {
            return $query->whereRaw('1 = 0');
        }

        $companyEfectiva = $companyUuid ?? $this->companyUuid;

        if (! $companyEfectiva && ! $this->esSuperadmin) {
            return $query->whereRaw('1 = 0');
        }

        if ($companyEfectiva) {
            $query->where('vehicles.company_uuid', $companyEfectiva);
        }

        // Restricción por sucursal
        if ($this->esSucursalRestringida) {
            if (! $this->branchUuid) {
                return $query->whereRaw('1 = 0');
            }
            $branchUuid = $this->branchUuid;
            $query->whereHas('vehicleBranches', function (Builder $q) use ($branchUuid) {
                $q->where('vehicles_branches.branch_uuid', $branchUuid)
                    ->where('vehicles_branches.is_active', 1);
            });
        }

        // Roles administrativos tienen visión global de la empresa activa
        if ($this->esSuperadmin || $this->esAdminEmpresa) {
            return $query;
        }

        // Regla de prevalencia AFILIADO (SPEC-005): si es AFILIADO prima sobre CONDUCTOR
        if ($this->esAfiliado) {
            if (! $this->affiliateUuid) {
                return $query->whereRaw('1 = 0');
            }
            $affiliateUuid = $this->affiliateUuid;
            $query->where(function (Builder $q) use ($affiliateUuid) {
                $q->where('vehicles.third_party_uuid', $affiliateUuid)
                    ->orWhereHas('owners', function (Builder $oq) use ($affiliateUuid) {
                        $oq->where('third_party_uuid', $affiliateUuid);
                    });
            });

            return $query;
        }

        // Regla estricta CONDUCTOR puro (decisión 2026-10-20)
        if ($this->esConductor) {
            if (! $this->thirdPartyUuid) {
                return $query->whereRaw('1 = 0');
            }
            $allowedUuids = $this->obtenerVehiculosDeConductor();
            $query->whereIn('vehicles.uuid', $allowedUuids);

            return $query;
        }

        return $query;
    }

    /**
     * Alias de compatibilidad fluida: scopeVehicles.
     */
    public function scopeVehicles(Builder $query, ?string $companyUuid = null): Builder
    {
        return $this->aplicarAVehiculos($query, $companyUuid);
    }

    /**
     * Aplica el predicado de visibilidad para TERCEROS Y CONDUCTORES.
     */
    public function aplicarAConductores(Builder $query, ?string $companyUuid = null): Builder
    {
        $this->asegurarContexto();

        if (! $this->usuario) {
            return $query->whereRaw('1 = 0');
        }

        $companyEfectiva = $companyUuid ?? $this->companyUuid;

        if (! $companyEfectiva && ! $this->esSuperadmin) {
            return $query->whereRaw('1 = 0');
        }

        if ($companyEfectiva) {
            $query->where('third_parties.company_uuid', $companyEfectiva);
        }

        if ($this->esSucursalRestringida) {
            if (! $this->branchUuid) {
                return $query->whereRaw('1 = 0');
            }
            $query->where('third_parties.branch_uuid', $this->branchUuid);
        }

        // Roles administrativos tienen visión global de la empresa activa
        if ($this->esSuperadmin || $this->esAdminEmpresa) {
            return $query;
        }

        if ($this->esAfiliado) {
            if (! $this->affiliateUuid) {
                return $query->whereRaw('1 = 0');
            }
            $allowedUuids = array_unique(array_merge(
                [$this->affiliateUuid],
                $this->obtenerConductoresDeAfiliado()
            ));

            return $query->whereIn('third_parties.uuid', $allowedUuids);
        }

        if ($this->esConductor) {
            if (! $this->thirdPartyUuid) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('third_parties.uuid', $this->thirdPartyUuid);
        }

        return $query;
    }

    /**
     * Alias de compatibilidad fluida: scopeDrivers.
     */
    public function scopeDrivers(Builder $query, ?string $companyUuid = null): Builder
    {
        return $this->aplicarAConductores($query, $companyUuid);
    }

    /**
     * Aplica el predicado de visibilidad para NOTIFICACIONES.
     */
    public function aplicarANotificaciones(Builder $query, ?string $companyUuid = null): Builder
    {
        $this->asegurarContexto();

        if (! $this->usuario) {
            return $query->whereRaw('1 = 0');
        }

        $companyEfectiva = $companyUuid ?? $this->companyUuid;

        if (! $companyEfectiva && ! $this->esSuperadmin) {
            return $query->whereRaw('1 = 0');
        }

        if ($companyEfectiva) {
            $query->where('notifications.company_uuid', $companyEfectiva);
        }

        if ($this->esSucursalRestringida) {
            if (! $this->branchUuid) {
                return $query->whereRaw('1 = 0');
            }
            $branchUuid = $this->branchUuid;
            $query->where(function (Builder $sq) use ($branchUuid) {
                $sq->where(function (Builder $vq) use ($branchUuid) {
                    $vq->where('entity_type', Vehicle::class)
                        ->whereIn('entity_uuid', function ($sub) use ($branchUuid) {
                            $sub->select('vehicle_uuid')->from('vehicles_branches')
                                ->where('branch_uuid', $branchUuid)
                                ->where('is_active', 1);
                        });
                })->orWhere(function (Builder $dq) use ($branchUuid) {
                    $dq->where('entity_type', VehicleDocument::class)
                        ->whereIn('entity_uuid', function ($sub) use ($branchUuid) {
                            $sub->select('uuid')->from('vehicle_documents')
                                ->whereIn('vehicle_uuid', function ($subv) use ($branchUuid) {
                                    $subv->select('vehicle_uuid')->from('vehicles_branches')
                                        ->where('branch_uuid', $branchUuid)
                                        ->where('is_active', 1);
                                });
                        });
                })->orWhere(function (Builder $cq) use ($branchUuid) {
                    $cq->where('entity_type', OperationCard::class)
                        ->whereIn('entity_uuid', function ($sub) use ($branchUuid) {
                            $sub->select('uuid')->from('operation_cards')
                                ->whereIn('vehicle_uuid', function ($subv) use ($branchUuid) {
                                    $subv->select('vehicle_uuid')->from('vehicles_branches')
                                        ->where('branch_uuid', $branchUuid)
                                        ->where('is_active', 1);
                                });
                        });
                })->orWhere(function (Builder $lq) use ($branchUuid) {
                    $lq->where('entity_type', DriverLicense::class)
                        ->whereIn('entity_uuid', function ($sub) use ($branchUuid) {
                            $sub->select('uuid')->from('driver_licenses')
                                ->whereIn('third_party_uuid', function ($subt) use ($branchUuid) {
                                    $subt->select('uuid')->from('third_parties')
                                        ->where('branch_uuid', $branchUuid);
                                });
                        });
                });
            });
        }

        // Roles administrativos tienen visión global de la empresa activa
        if ($this->esSuperadmin || $this->esAdminEmpresa) {
            return $query;
        }

        if ($this->esAfiliado) {
            if (! $this->affiliateUuid) {
                return $query->whereRaw('1 = 0');
            }

            $affiliateUuid = $this->affiliateUuid;
            $vehiculos = $this->obtenerVehiculosDeAfiliado();
            $conductores = array_unique(array_merge([$affiliateUuid], $this->obtenerConductoresDeAfiliado()));
            $licenciasPropias = $this->obtenerLicenciasDeOwnersDrivers($affiliateUuid);

            $query->where(function (Builder $q) use ($vehiculos, $conductores, $licenciasPropias, $affiliateUuid) {
                // 1. Vehículos
                $q->orWhere(function (Builder $sub) use ($vehiculos) {
                    $sub->where('entity_type', Vehicle::class)
                        ->whereIn('entity_uuid', $vehiculos);
                })
                    // 2. Documentos de vehículo
                    ->orWhere(function (Builder $sub) use ($vehiculos) {
                        $sub->where('entity_type', VehicleDocument::class)
                            ->whereIn('entity_uuid', function ($sq) use ($vehiculos) {
                                $sq->select('uuid')->from('vehicle_documents')->whereIn('vehicle_uuid', $vehiculos);
                            });
                    })
                    // 3. Tarjetas de operación
                    ->orWhere(function (Builder $sub) use ($vehiculos) {
                        $sub->where('entity_type', OperationCard::class)
                            ->whereIn('entity_uuid', function ($sq) use ($vehiculos) {
                                $sq->select('uuid')->from('operation_cards')->whereIn('vehicle_uuid', $vehiculos);
                            });
                    })
                    // 4. Licencias de conducción (sus conductores vinculados y licencias propias)
                    ->orWhere(function (Builder $sub) use ($conductores, $licenciasPropias) {
                        $sub->where('entity_type', DriverLicense::class)
                            ->where(function (Builder $lq) use ($conductores, $licenciasPropias) {
                                $lq->whereIn('entity_uuid', function ($sq) use ($conductores) {
                                    $sq->select('uuid')->from('driver_licenses')->whereIn('third_party_uuid', $conductores);
                                })->orWhereIn('entity_uuid', $licenciasPropias);
                            });
                    })
                    // 5. Convenios de colaboración
                    ->orWhere(function (Builder $sub) use ($vehiculos) {
                        $sub->where('entity_type', BusinessCollaborationAgreement::class)
                            ->whereIn('entity_uuid', function ($sq) use ($vehiculos) {
                                $sq->select('uuid')->from('business_collaboration_agreements')->whereIn('vehicle_uuid', $vehiculos);
                            });
                    })
                    // 6. Cobros de administración de afiliado
                    ->orWhere(function (Builder $sub) use ($affiliateUuid, $vehiculos) {
                        $sub->where('entity_type', AffiliateAdminCharge::class)
                            ->where(function (Builder $cq) use ($affiliateUuid, $vehiculos) {
                                $cq->whereIn('entity_uuid', function ($sq) use ($affiliateUuid) {
                                    $sq->select('uuid')->from('affiliate_admin_charges')->where('third_party_uuid', $affiliateUuid);
                                })->orWhereIn('entity_uuid', function ($sq) use ($vehiculos) {
                                    $sq->select('uuid')->from('affiliate_admin_charges')->whereIn('vehicle_uuid', $vehiculos);
                                });
                            });
                    });
            });

            return $query;
        }

        if ($this->esConductor) {
            $driverThirdPartyUuid = $this->thirdPartyUuid;
            $vehiculos = $driverThirdPartyUuid ? $this->obtenerVehiculosDeConductor() : [];

            if (! $driverThirdPartyUuid && empty($vehiculos)) {
                return $query->whereRaw('1 = 0');
            }

            $query->where(function (Builder $q) use ($vehiculos, $driverThirdPartyUuid) {
                if (! empty($vehiculos)) {
                    $q->orWhere(function (Builder $sub) use ($vehiculos) {
                        $sub->where('entity_type', Vehicle::class)->whereIn('entity_uuid', $vehiculos);
                    })->orWhere(function (Builder $sub) use ($vehiculos) {
                        $sub->where('entity_type', VehicleDocument::class)
                            ->whereIn('entity_uuid', function ($sq) use ($vehiculos) {
                                $sq->select('uuid')->from('vehicle_documents')->whereIn('vehicle_uuid', $vehiculos);
                            });
                    })->orWhere(function (Builder $sub) use ($vehiculos) {
                        $sub->where('entity_type', OperationCard::class)
                            ->whereIn('entity_uuid', function ($sq) use ($vehiculos) {
                                $sq->select('uuid')->from('operation_cards')->whereIn('vehicle_uuid', $vehiculos);
                            });
                    });
                }

                if ($driverThirdPartyUuid) {
                    $q->orWhere(function (Builder $sub) use ($driverThirdPartyUuid) {
                        $sub->where('entity_type', DriverLicense::class)
                            ->whereIn('entity_uuid', function ($sq) use ($driverThirdPartyUuid) {
                                $sq->select('uuid')->from('driver_licenses')->where('third_party_uuid', $driverThirdPartyUuid);
                            });
                    });
                }
            });

            return $query;
        }

        return $query;
    }

    /**
     * Alias de compatibilidad fluida: scopeNotifications.
     */
    public function scopeNotifications(Builder $query, ?string $companyUuid = null): Builder
    {
        return $this->aplicarANotificaciones($query, $companyUuid);
    }

    /**
     * Aplica el predicado de visibilidad para RASTREO SATELITAL / GPS.
     */
    public function aplicarARastreo(Builder $query, ?string $companyUuid = null): Builder
    {
        $this->asegurarContexto();

        if (! $this->usuario) {
            return $query->whereRaw('1 = 0');
        }

        $companyEfectiva = $companyUuid ?? $this->companyUuid;

        if (! $companyEfectiva && ! $this->esSuperadmin) {
            return $query->whereRaw('1 = 0');
        }

        if ($companyEfectiva) {
            $query->where($query->getModel()->getTable() . '.company_uuid', $companyEfectiva);
        }

        $alcance = $this->obtenerAlcanceRastreo();

        if ($alcance !== null) {
            $conductores = $alcance['conductores'];
            $table = $query->getModel()->getTable();

            if (empty($conductores)) {
                return $query->whereRaw('1 = 0');
            }

            if ($table === 'driver_locations' || $table === 'driver_location_sessions' || $table === 'driver_location_alerts') {
                $query->whereIn($table . '.third_party_uuid', $conductores);
            }
        }

        return $query;
    }

    /**
     * Alias de compatibilidad fluida: scopeTracking.
     */
    public function scopeTracking(Builder $query, ?string $companyUuid = null): Builder
    {
        return $this->aplicarARastreo($query, $companyUuid);
    }

    // =========================================================================
    // INTEGRACIÓN CON BASESERVICE (Aislamiento Afiliado y Modelos Generales)
    // =========================================================================

    /**
     * Aplica al acceso por UUID el mismo aislamiento de afiliado que los listados.
     */
    public function aislarPorAfiliado(Builder $query, Model $model): void
    {
        $this->asegurarContexto();

        if (! $this->usuario || $this->esSuperadmin || $this->esAdminEmpresa || ! $this->esAfiliado || ! in_array($model->getTable(), self::TABLAS_AISLADAS_POR_AFILIADO, true)) {
            return;
        }

        $companyUuid = $this->companyUuid;
        if (! $companyUuid) {
            $query->whereRaw('1 = 0');

            return;
        }

        $this->aplicarFiltroAfiliado($query, $model, $companyUuid, false);
    }

    /**
     * Restringe consultas generales de BaseService para AFILIADO y CONDUCTOR.
     */
    public function aplicarFiltroAfiliado(Builder $query, Model $model, string $companyUuid, bool $incluirConductor = true): void
    {
        $this->asegurarContexto();

        if (! $this->usuario || $this->esSuperadmin || $this->esAdminEmpresa || (! $this->esAfiliado && (! $incluirConductor || ! $this->esConductor))) {
            return;
        }

        $table = $model->getTable();
        $fillable = $model->getFillable();
        $thirdPartyUuid = $this->thirdPartyUuid;
        $affiliateUuid = $this->affiliateUuid() ?? $thirdPartyUuid;

        if ($this->esAfiliado && ! $affiliateUuid) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($this->esConductor && ! $thirdPartyUuid) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($table === 'third_parties') {
            if ($this->esAfiliado) {
                $allowedUuids = array_unique(array_merge(
                    [$affiliateUuid],
                    $this->obtenerConductoresDeAfiliado($affiliateUuid)
                ));
                $query->whereIn($table . '.uuid', $allowedUuids);
            } elseif ($this->esConductor) {
                $query->where($table . '.uuid', $thirdPartyUuid);
            }
        } elseif ($table === 'driver_licenses') {
            if ($this->esAfiliado) {
                $driverUuids = $this->obtenerConductoresDeAfiliado($affiliateUuid);
                $licenciasPropias = $this->obtenerLicenciasDeOwnersDrivers($affiliateUuid);

                $query->where(function (Builder $q) use ($table, $affiliateUuid, $driverUuids, $licenciasPropias) {
                    $q->where($table . '.third_party_uuid', $affiliateUuid)
                        ->orWhereIn($table . '.third_party_uuid', $driverUuids)
                        ->orWhereIn($table . '.uuid', $licenciasPropias);
                });
            } elseif ($this->esConductor) {
                $query->where($table . '.third_party_uuid', $thirdPartyUuid);
            }
        } elseif ($table === 'social_security_contributions') {
            if ($this->esAfiliado) {
                $allowedUuids = array_unique(array_merge(
                    [$affiliateUuid],
                    $this->obtenerConductoresDeAfiliado($affiliateUuid)
                ));
                $query->whereIn($table . '.third_party_uuid', $allowedUuids);
            } elseif ($this->esConductor) {
                $query->where($table . '.third_party_uuid', $thirdPartyUuid);
            }
        } elseif ($table === 'owners_drivers') {
            if ($this->esAfiliado) {
                $query->where($table . '.third_party_uuid', $affiliateUuid);
            } elseif ($this->esConductor) {
                $driverLicenseUuids = DriverLicense::withoutGlobalScopes()
                    ->where('third_party_uuid', $thirdPartyUuid)
                    ->pluck('uuid')
                    ->toArray();
                $query->whereIn($table . '.driver_license_uuid', $driverLicenseUuids);
            }
        } elseif ($table === 'vehicles') {
            $this->aplicarAVehiculos($query, $companyUuid);
        } elseif ($table === 'vehicle_inspections') {
            if ($this->esAfiliado) {
                $query->whereHas('vehicle', function (Builder $q) use ($affiliateUuid) {
                    $q->where('vehicles.third_party_uuid', $affiliateUuid)
                        ->orWhereHas('owners', function (Builder $oq) use ($affiliateUuid) {
                            $oq->where('third_party_uuid', $affiliateUuid);
                        });
                });
            } elseif ($this->esConductor) {
                $allowedUuids = $this->obtenerVehiculosDeConductor($thirdPartyUuid);
                $query->where(function (Builder $q) use ($thirdPartyUuid, $allowedUuids) {
                    $q->where('vehicle_inspections.driver_uuid', $thirdPartyUuid)
                        ->orWhereIn('vehicle_inspections.vehicle_uuid', $allowedUuids);
                });
            }
        } elseif ($table === 'procedures') {
            if ($this->esAfiliado) {
                $query->where(function (Builder $q) use ($affiliateUuid) {
                    $q->where('procedures.third_party_uuid', $affiliateUuid)
                        ->orWhereHas('vehicle', function (Builder $vq) use ($affiliateUuid) {
                            $vq->where('vehicles.third_party_uuid', $affiliateUuid)
                                ->orWhereHas('owners', fn(Builder $o) => $o->where('third_party_uuid', $affiliateUuid));
                        });
                });
            }
        } elseif ($table === 'fuec' || $table === 'fuecs') {
            if ($this->esAfiliado) {
                $query->whereHas('vehicle', function (Builder $q) use ($affiliateUuid) {
                    $q->where('vehicles.third_party_uuid', $affiliateUuid)
                        ->orWhereHas('owners', function (Builder $oq) use ($affiliateUuid) {
                            $oq->where('third_party_uuid', $affiliateUuid);
                        });
                });
            } elseif ($this->esConductor) {
                $allowedVehicles = $this->obtenerVehiculosDeConductor($thirdPartyUuid);
                $query->where(function (Builder $q) use ($thirdPartyUuid, $allowedVehicles) {
                    $q->where('fuec.main_conductor_uuid', $thirdPartyUuid)
                        ->orWhere('fuec.secondary_conductor_uuid', $thirdPartyUuid)
                        ->orWhere('fuec.tertiary_conductor_uuid', $thirdPartyUuid)
                        ->orWhereIn('fuec.vehicle_uuid', $allowedVehicles);
                });
            }
        } elseif (in_array('third_party_uuid', $fillable, true)) {
            $targetTp = $this->esAfiliado ? $affiliateUuid : ($this->affiliateUuid() ?? $thirdPartyUuid);
            $query->where($table . '.third_party_uuid', $targetTp);
        } elseif (method_exists($model, 'third_party')) {
            $targetTp = $this->esAfiliado ? $affiliateUuid : ($this->affiliateUuid() ?? $thirdPartyUuid);
            $query->whereHas('third_party', function (Builder $q) use ($targetTp) {
                $q->where('third_parties.uuid', $targetTp);
            });
        } elseif (method_exists($model, 'thirdParty')) {
            $targetTp = $this->esAfiliado ? $affiliateUuid : ($this->affiliateUuid() ?? $thirdPartyUuid);
            $query->whereHas('thirdParty', function (Builder $q) use ($targetTp) {
                $q->where('third_parties.uuid', $targetTp);
            });
        } elseif ($this->esAfiliado && method_exists($model, 'vehicle')) {
            $query->whereHas('vehicle', function (Builder $q) use ($affiliateUuid) {
                $q->where(function (Builder $w) use ($affiliateUuid) {
                    $w->where('vehicles.third_party_uuid', $affiliateUuid)
                        ->orWhereHas('owners', fn(Builder $o) => $o->where('third_party_uuid', $affiliateUuid));
                });
            });
        }
    }

    private function asegurarContexto(): void
    {
        $req = request();
        $reqId = $req ? spl_object_id($req) : 0;
        $authId = Auth::id() ?? 0;
        $reqCompany = $req?->attributes->get('current_company_uuid') ?? '';
        $reqBranch = $req?->attributes->get('current_branch_uuid') ?? '';
        $contextFingerprint = "{$reqId}|{$authId}|{$reqCompany}|{$reqBranch}";

        if ($this->currentContextFingerprint !== null && $this->currentContextFingerprint !== $contextFingerprint) {
            $this->limpiarCache();
        }

        $this->currentContextFingerprint = $contextFingerprint;

        if (! $this->contextoResuelto) {
            $this->resolverContexto();
        }
    }
}
