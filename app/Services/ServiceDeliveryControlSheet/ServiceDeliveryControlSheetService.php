<?php

declare(strict_types=1);

namespace App\Services\ServiceDeliveryControlSheet;

use App\Models\Fuec;
use App\Models\Project;
use App\Models\ProjectDriverVehicle;
use App\Models\ServiceDeliveryControlSheet;
use App\Models\ServiceDeliveryControlSheetRoute;
use App\Models\Signature;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Services\BaseService;
use App\Services\Signature\SignatureService;
use App\Utils\Logger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para la gestión de hojas de control de entrega de servicios.
 * Soporta: proyecto base, N recorridos por planilla, multi-día padre/hijos,
 * vehículo interno (directo) y vehículo externo/subcontratado de plataforma.
 *
 * @author Darwin Montes
 * @version V 1.1.0
 * @since V 1.1.0
 * @created 2026-06-19
 */
class ServiceDeliveryControlSheetService extends BaseService
{
    public function __construct(
        private readonly ServiceInternalControlService $serviceInternalControlService,
        private readonly ServiceInternalControlSubcontractedService $serviceInternalControlSubcontractedService,
        private readonly SignatureService $signatureService,
        private readonly PcpEvidence $evidencia
    ) {
        parent::__construct();
    }

    protected array $searchableFields = [
        'official_name_and_surname',
        'daily_route',
    ];

    protected array $sortableFields = [
        'vehicle_license_plate',
        'start_date',
        'service_date',
    ];

    protected function getModelInstance(): Model
    {
        return new ServiceDeliveryControlSheet;
    }

    public function getAllServiceDeliveryControlSheetsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $projectUuid = null,
        bool $soloCerradas = false,
        bool $agruparPorProyecto = false,
        ?string $estadoFiltro = null,
        ?string $sortBy = null,
        string $sortOrder = 'desc'
    ): LengthAwarePaginator {
        $query = $this->query()
            ->whereNull('parent_uuid')
            ->with(['project', 'routes', 'children.routes'])
            ->withCount(['children as children_total'])
            ->withCount(['children as children_open' => function ($q) {
                $q->where('is_active', true);
            }]);

        // SPEC-009: las hojas cuyo coche fue retirado del proyecto se conservan
        // como histórico pero ya no se listan; el módulo refleja la asignación
        // vigente.
        $query->where('retirada_de_proyecto', false);

        $this->aplicarFiltroConductor($query, $companyUuid);

        if ($estadoFiltro === 'cerradas' || ($estadoFiltro === null && $soloCerradas)) {
            // Solo fechas cerradas que imprimen PDF:
            // - Un día: padre sin hijos y con is_active=false.
            // - Multi-día: padre con al menos un hijo cerrado (aunque el padre siga parcial).
            // El expandible del frontend oculta los hijos abiertos y deja solo los cerrados.
            $query->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->where('is_active', false)
                        ->whereDoesntHave('children', fn ($c) => $c->where('is_active', true));
                })->orWhereHas('children', fn ($c) => $c->where('is_active', false));
            });
        } elseif ($estadoFiltro === 'en_curso') {
            $query->where(function ($q) {
                $q->where('is_active', true)
                    ->orWhereHas('children', fn ($c) => $c->where('is_active', true));
            });
        } elseif ($estadoFiltro === 'con_pendientes') {
            $subconsultaCertificado = static function ($sub, string $columnaId): void {
                $sub->selectRaw('1')
                    ->from('signatures')
                    ->whereColumn('signatures.entity_id', $columnaId)
                    ->where('signatures.entity_type', PcpEvidence::ENTITY_COORDINADOR)
                    ->where('signatures.signer_role', Signature::ROL_COORDINADOR)
                    ->where('signatures.status', Signature::STATUS_VIGENTE);
            };

            $query->where(function ($q) use ($subconsultaCertificado) {
                // Caso 1: Planilla de un solo día (sin hijos)
                $q->where(function ($single) use ($subconsultaCertificado) {
                    $single->whereDoesntHave('children')
                        ->where(function ($cond) use ($subconsultaCertificado) {
                            $cond->where('is_active', true)
                                ->orWhere(function ($closed) use ($subconsultaCertificado) {
                                    $closed->where(fn ($ex) => $ex->whereNull('exception_reason')->orWhere('exception_reason', ''))
                                        ->where(function ($inc) use ($subconsultaCertificado) {
                                            $inc->whereNull('end_time')
                                                ->orWhereNull('ending_kilometer')
                                                ->orWhereNotExists(fn ($sub) => $subconsultaCertificado($sub, 'service_delivery_control_sheet.id'));
                                        });
                                });
                        });
                })
                // Caso 2: Planilla multi-día (con hijos) -> al menos un hijo con pendientes
                ->orWhereHas('children', function ($child) use ($subconsultaCertificado) {
                    $child->where('is_active', true)
                        ->orWhere(function ($closed) use ($subconsultaCertificado) {
                            $closed->where(fn ($ex) => $ex->whereNull('exception_reason')->orWhere('exception_reason', ''))
                                ->where(function ($inc) use ($subconsultaCertificado) {
                                    $inc->whereNull('end_time')
                                        ->orWhereNull('ending_kilometer')
                                        ->orWhereNotExists(fn ($sub) => $subconsultaCertificado($sub, 'service_delivery_control_sheet.id'));
                                });
                        });
                });
            });
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($projectUuid) {
            $query->where('project_uuid', $projectUuid);
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
                // Buscar también por nombre de proyecto
                $q->orWhereHas('project', fn ($pq) => $pq->where('project_name', 'like', "%{$search}%"));
                // ...por placa y conductor (vehículo interno o subcontratado).
                $q->orWhereHas('internalControl', function ($ic) use ($search) {
                    $ic->whereHas('vehicle', fn ($v) => $v->where('vehicle_license_plate', 'like', "%{$search}%"))
                        ->orWhereHas('thirdParty', fn ($t) => $t->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%"));
                });
                $q->orWhereHas('subcontractedControl', fn ($sc) => $sc->where('vehicle_license_plate', 'like', "%{$search}%")
                    ->orWhere('driver_name_and_surname', 'like', "%{$search}%"));
            });
        }

        $direction = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

        if ($sortBy !== null && in_array($sortBy, $this->sortableFields, true)) {
            if ($sortBy === 'vehicle_license_plate') {
                $query->orderByRaw(
                    "COALESCE(
                        (SELECT v.vehicle_license_plate FROM service_internal_controls sic JOIN vehicles v ON v.uuid = sic.vehicle_uuid WHERE sic.service_delivery_control_sheet_uuid = service_delivery_control_sheet.uuid LIMIT 1),
                        (SELECT sics.vehicle_license_plate FROM service_internal_controls_subcontracted sics WHERE sics.service_delivery_control_sheet_uuid = service_delivery_control_sheet.uuid LIMIT 1)
                    ) {$direction}"
                );
            } else {
                $query->orderByRaw("COALESCE(service_delivery_control_sheet.start_date, service_delivery_control_sheet.service_date) {$direction}");
            }
        } elseif ($agruparPorProyecto) {
            // Agrupación por proyecto: la paginación es del servidor, así que el orden
            // por nombre de proyecto mantiene juntas las filas de cada grupo.
            $query->orderBy(Project::query()->select('project_name')
                ->whereColumn('projects.uuid', 'service_delivery_control_sheet.project_uuid')
                ->limit(1))
                ->orderByDesc('service_delivery_control_sheet.service_date');
        } else {
            $query->orderByRaw('COALESCE(service_delivery_control_sheet.start_date, service_delivery_control_sheet.service_date) DESC')
                ->orderByDesc('service_delivery_control_sheet.id');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // La decoración de evidencia vive en PcpEvidence: estado, pendientes y
        // agregados se calculan con firmas cargadas en lote (§7.5).
        $this->evidencia->decorarColeccion($paginator->getCollection());

        return $paginator;
    }

    /**
     * Resumen global por día de un proyecto, independiente de la página visible.
     * La agregación vive en PcpEvidence; aquí solo se conserva el contrato.
     *
     * @return array{total_dias: int, cerrados: int, en_curso: int, evidencia_incompleta: int, pendiente_certificacion: int, excepciones: int, certificados: int}
     */
    public function getProjectEvidenceSummary(string $projectUuid): array
    {
        return $this->evidencia->resumenProyecto($projectUuid);
    }

    /**
     * Resumen en lote de todos los proyectos activos de la empresa/conductor.
     * Alimenta el selector/switcher de tarjetas de proyectos sin disparar N peticiones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProjectsOverview(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        $resolvedThirdParty = $thirdPartyUuid ?? $this->conductorThirdPartyUuid($companyUuid);
        if ($resolvedThirdParty === null) {
            return [];
        }

        $projectQuery = Project::query()
            ->withCount(['driverVehicleAssignments as assignments_count' => fn ($q) => $q->active()])
            ->orderBy('project_name');

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid')
                ?? Auth::user()?->companies()->first()?->uuid;
        }

        if ($companyUuid) {
            $projectQuery->where('company_uuid', $companyUuid);
        }

        if ($resolvedThirdParty !== false) {
            $projectQuery->whereHas('driverVehicleAssignments', fn ($q) => $q->active()->where('third_party_uuid', $resolvedThirdParty));
        }

        $projects = $projectQuery->get([
            'uuid',
            'company_uuid',
            'project_name',
            'start_date',
            'completion_date',
            'project_value',
            'purchase_order',
        ]);

        if ($projects->isEmpty()) {
            return [];
        }

        $projectUuids = $projects->pluck('uuid')->all();
        $filtroConductor = null;
        if ($resolvedThirdParty !== false) {
            $filtroConductor = function ($q) use ($resolvedThirdParty) {
                $q->where(function ($sub) use ($resolvedThirdParty) {
                    $sub->whereHas('internalControl', fn ($c) => $c->where('third_party_uuid', $resolvedThirdParty))
                        ->orWhereHas('children.internalControl', fn ($c) => $c->where('third_party_uuid', $resolvedThirdParty));
                });
            };
        }

        $summaries = $this->evidencia->resumenProyectosEnLote($projectUuids, $filtroConductor);

        return $projects->map(function (Project $p) use ($summaries) {
            $summary = $summaries[$p->uuid] ?? [
                'total_planillas' => 0,
                'total_dias' => 0,
                'cerrados' => 0,
                'en_curso' => 0,
                'evidencia_incompleta' => 0,
                'pendiente_certificacion' => 0,
                'excepciones' => 0,
                'certificados' => 0,
                'porcentaje_al_dia' => 100,
            ];

            return [
                'uuid' => $p->uuid,
                'company_uuid' => $p->company_uuid,
                'project_name' => $p->project_name,
                'start_date' => $p->start_date?->toDateString(),
                'completion_date' => $p->completion_date?->toDateString(),
                'assignments_count' => (int) ($p->assignments_count ?? 0),
                'summary' => $summary,
            ];
        })->all();
    }

    public function getAllServiceDeliveryControlSheets(): Collection
    {
        // SPEC-009: igual que el listado paginado, excluye las retiradas.
        $query = $this->query()
            ->whereNull('parent_uuid')
            ->where('retirada_de_proyecto', false)
            ->with(['project', 'routes', 'children.routes', 'children.internalControl', 'children.subcontractedControl']);

        $this->aplicarFiltroConductor($query);

        return $query->get();
    }

    /**
     * Si el usuario autenticado es CONDUCTOR (y no un rol administrativo/afiliado),
     * restringe las planillas a las suyas: aquellas cuyo control interno (o el de
     * alguno de sus días hijos) está asignado a su tercero. Sin tercero asociado
     * no devuelve nada.
     */
    private function aplicarFiltroConductor($query, ?string $companyUuid = null): void
    {
        $thirdPartyUuid = $this->conductorThirdPartyUuid($companyUuid);

        if ($thirdPartyUuid === false) {
            return;
        }

        if ($thirdPartyUuid === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($q) use ($thirdPartyUuid) {
            $q->whereHas('internalControl', fn ($c) => $c->where('third_party_uuid', $thirdPartyUuid))
                ->orWhereHas('children.internalControl', fn ($c) => $c->where('third_party_uuid', $thirdPartyUuid));
        });
    }

    /**
     * @return string|null|false false = el usuario no está limitado como conductor;
     *                           null = es conductor pero sin tercero asociado.
     */
    private function conductorThirdPartyUuid(?string $companyUuid = null): string|null|false
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('CONDUCTOR')
            || $user->hasAnyRole(['SUPERADMIN', 'ADMIN_EMPRESA', 'EMPLEADO', 'AFILIADO'])) {
            return false;
        }

        $thirdPartyUuid = request()->attributes->get('current_third_party_uuid');

        if (! $thirdPartyUuid) {
            $companyUser = ($companyUuid
                    ? $user->companies()->where('company_user.company_uuid', $companyUuid)->first()
                    : null)
                ?? $user->companies()->first();
            $thirdPartyUuid = $companyUser?->pivot?->third_party_uuid;
        }

        return $thirdPartyUuid ?: null;
    }

    /**
     * SPEC-002 §7.2 — Congelamiento de la evidencia al cerrar.
     *
     * Una planilla cerrada es la evidencia administrativa del día: si admite
     * cambios, el PDF que se reimprime deja de coincidir con el que ya se entregó.
     * La UI siempre ocultó la edición cuando is_active=false, pero nada lo
     * garantizaba en el backend: estas guardas son el congelamiento real.
     *
     * Siguen permitidos después del cierre (ver §7.2): la firma del coordinador
     * por enlace público y las firmas por recorrido vía closeRoute.
     */
    private function asegurarEditable(Model $record, string $campo): void
    {
        if ((bool) $record->is_active) {
            return;
        }

        throw ValidationException::withMessages([
            $campo => 'La planilla está cerrada y su evidencia ya no puede modificarse.',
        ]);
    }

    /**
     * SPEC-002 §5.2 — Reglas de una jornada declarada en disponibilidad.
     *
     * Exige motivo (no basta con "no tener recorridos") y prohíbe recorridos:
     * son estados excluyentes y mezclarlos deja evidencia inconsistente.
     */
    private function validarDisponibilidad(ServiceDeliveryControlSheet $record, ?string $dayKind, mixed $motivo): void
    {
        if ($dayKind !== ServiceDeliveryControlSheet::DIA_DISPONIBILIDAD) {
            return;
        }

        $motivo = is_string($motivo) ? trim($motivo) : '';

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'availability_reason' => 'Indique el motivo por el que el vehículo queda en disponibilidad.',
            ]);
        }

        $tieneRutas = $record->relationLoaded('routes')
            ? $record->routes->isNotEmpty()
            : $record->routes()->exists();

        if ($tieneRutas) {
            throw ValidationException::withMessages([
                'day_kind' => 'No se puede declarar en disponibilidad una planilla que tiene recorridos.',
            ]);
        }
    }

    public function getServiceDeliveryControlSheetByUuid(string $uuid): ?Model
    {
        $query = ServiceDeliveryControlSheet::where('uuid', $uuid)
            ->with(['project', 'routes', 'children.routes', 'children.internalControl', 'children.subcontractedControl']);

        $thirdPartyUuid = $this->conductorThirdPartyUuid();
        if ($thirdPartyUuid !== false) {
            $query->where(function ($q) use ($thirdPartyUuid) {
                if ($thirdPartyUuid === null) {
                    $q->whereRaw('1 = 0');

                    return;
                }
                $q->whereHas('internalControl', fn ($c) => $c->where('third_party_uuid', $thirdPartyUuid))
                    ->orWhereHas('children.internalControl', fn ($c) => $c->where('third_party_uuid', $thirdPartyUuid))
                    ->orWhereHas('parent.children.internalControl', fn ($c) => $c->where('third_party_uuid', $thirdPartyUuid));
            });
        }

        return $query->first();
    }

    /**
     * Normaliza el tipo de planilla a los 4 valores permitidos.
     */
    private function normalizeType(mixed $type): string
    {
        $allowed = ['DIRECTO_CON_LA_EMPRESA', 'SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'];

        return in_array($type, $allowed, true) ? $type : 'DIRECTO_CON_LA_EMPRESA';
    }

    private function isExternalType(string $type): bool
    {
        return in_array($type, ['SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'], true);
    }

    /**
     * Empresa efectiva del registro: manda el contexto de la petición (ya
     * validado por `SetCompanyContext`); el valor del cuerpo solo se usa en
     * flujos internos sin petición.
     */
    private function resolverEmpresa(?string $companyUuid): ?string
    {
        $contexto = request()->attributes->get('current_company_uuid');
        if ($contexto) {
            return $contexto;
        }

        if ($companyUuid) {
            return $companyUuid;
        }

        return Auth::user()?->companies()->first()?->uuid;
    }

    /**
     * Defensa en profundidad: el request ya acota proyecto, vehículo, tercero y
     * FUEC con sus global scopes, pero el servicio también se invoca desde otros
     * flujos y no debe guardar vínculos de otra empresa o sede.
     *
     * @param array<string, mixed> $data
     */
    private function validarPertenencia(string $companyUuid, array $data): void
    {
        if (! empty($data['project_uuid'])
            && ! Project::query()->where('company_uuid', $companyUuid)->where('uuid', $data['project_uuid'])->exists()) {
            throw ValidationException::withMessages([
                'project_uuid' => 'El proyecto no pertenece a la empresa activa.',
            ]);
        }

        if (! empty($data['vehicle_uuid'])
            && ! Vehicle::query()->where('company_uuid', $companyUuid)->where('uuid', $data['vehicle_uuid'])->exists()) {
            throw ValidationException::withMessages([
                'vehicle_uuid' => 'El vehículo no pertenece a la empresa o sede activa.',
            ]);
        }

        if (! empty($data['third_party_uuid'])
            && ! ThirdParty::query()->where('company_uuid', $companyUuid)->where('uuid', $data['third_party_uuid'])->exists()) {
            throw ValidationException::withMessages([
                'third_party_uuid' => 'El tercero no pertenece a la empresa o sede activa.',
            ]);
        }

        if (! empty($data['fuec_uuid'])
            && ! Fuec::query()->where('company_uuid', $companyUuid)->where('uuid', $data['fuec_uuid'])->exists()) {
            throw ValidationException::withMessages([
                'fuec_uuid' => 'El FUEC no pertenece a la empresa activa.',
            ]);
        }
    }

    /**
     * Valida que las fechas de la planilla estén contenidas en el proyecto.
     */
    private function validateProjectDates(?string $projectUuid, ?string $startDate, ?string $endDate): void
    {
        if (! $projectUuid) {
            return;
        }
        $project = Project::where('uuid', $projectUuid)->first();
        if (! $project) {
            throw ValidationException::withMessages(['project_uuid' => 'El proyecto seleccionado no existe.']);
        }
        if ($startDate && $project->start_date && Carbon::parse($startDate)->lt(Carbon::parse($project->start_date))) {
            throw ValidationException::withMessages(['start_date' => 'La fecha de inicio no puede ser anterior al inicio del proyecto ('.$project->start_date->format('d/m/Y').').']);
        }
        if ($endDate && $project->completion_date && Carbon::parse($endDate)->gt(Carbon::parse($project->completion_date))) {
            throw ValidationException::withMessages(['end_date' => 'La fecha de fin no puede superar el fin del proyecto ('.$project->completion_date->format('d/m/Y').').']);
        }
    }

    /**
     * Construye daily_route legacy a partir de routes (compatibilidad PDF/listado).
     */
    private function buildDailyRoute(?string $dailyRoute, mixed $routes): ?string
    {
        if (is_array($routes) && count($routes) > 0) {
            $names = [];
            foreach ($routes as $r) {
                $label = trim(($r['origin'] ?? '').' - '.($r['destination'] ?? ''), ' -');
                if ($label) {
                    $names[] = $label;
                }
            }
            if (! empty($names)) {
                return mb_substr(implode(' · ', $names), 0, 255);
            }
        }

        return $dailyRoute;
    }

    private function syncRoutes(ServiceDeliveryControlSheet $record, mixed $routes): void
    {
        if (! is_array($routes)) {
            return;
        }

        $existingRoutes = $record->routes()->orderBy('order_index')->get();
        $processedRouteUuids = [];

        foreach (array_values($routes) as $index => $routeData) {
            if (! is_array($routeData)) {
                continue;
            }
            if (empty($routeData['origin']) && empty($routeData['destination'])) {
                continue;
            }

            $orderIndex = isset($routeData['order_index']) && (int) $routeData['order_index'] > 0
                ? (int) $routeData['order_index']
                : ($index + 1);

            $targetRoute = null;
            if (! empty($routeData['uuid'])) {
                $targetRoute = $existingRoutes->firstWhere('uuid', $routeData['uuid']);
            }
            if (! $targetRoute) {
                $targetRoute = $existingRoutes->firstWhere('order_index', $orderIndex);
            }

            $funcionarioNombre = isset($routeData['funcionario_nombre']) && trim((string) $routeData['funcionario_nombre']) !== ''
                ? mb_substr(trim((string) $routeData['funcionario_nombre']), 0, 150)
                : ($targetRoute?->funcionario_nombre ?? null);

            $funcionarioCc = isset($routeData['funcionario_cc']) && trim((string) $routeData['funcionario_cc']) !== ''
                ? mb_substr(trim((string) $routeData['funcionario_cc']), 0, 30)
                : ($targetRoute?->funcionario_cc ?? null);

            if ($targetRoute) {
                $targetRoute->update([
                    'order_index' => $orderIndex,
                    'origin' => $routeData['origin'] ?? $targetRoute->origin,
                    'destination' => $routeData['destination'] ?? $targetRoute->destination,
                    'funcionario_nombre' => $funcionarioNombre,
                    'funcionario_cc' => $funcionarioCc,
                    'is_active' => true,
                ]);
                $processedRouteUuids[] = $targetRoute->uuid;
            } else {
                $newRoute = ServiceDeliveryControlSheetRoute::create([
                    'uuid' => (string) Str::uuid(),
                    'service_delivery_control_sheet_uuid' => $record->uuid,
                    'order_index' => $orderIndex,
                    'origin' => $routeData['origin'] ?? null,
                    'destination' => $routeData['destination'] ?? null,
                    'funcionario_nombre' => $funcionarioNombre,
                    'funcionario_cc' => $funcionarioCc,
                    'is_active' => true,
                ]);
                $processedRouteUuids[] = $newRoute->uuid;
            }
        }

        // Eliminar solo rutas sobrantes que no hayan sido procesadas y que NO tengan cierres
        foreach ($existingRoutes as $oldRoute) {
            if (! in_array($oldRoute->uuid, $processedRouteUuids, true)) {
                if (empty($oldRoute->end_time) && ($oldRoute->ending_kilometer === null || $oldRoute->ending_kilometer === '')) {
                    $oldRoute->delete();
                }
            }
        }
    }

    /**
     * Pega la captura del mapa del recorrido (tomada en el frontend) a las
     * planillas del día del conductor. Resuelve por conductor + fecha y, como
     * respaldo, por vehículo/proyecto del GPS de ese día.
     *
     * @return array{planillas: array<int, string>, service_date: string}
     */
    public function attachRouteMapByDriverDate(string $thirdPartyUuid, string $serviceDate, string $imageBase64): array
    {
        $fecha = Carbon::parse($serviceDate)->toDateString();

        $candidatas = ServiceDeliveryControlSheet::query()
            ->whereDate('service_date', $fecha)
            ->whereHas('internalControl', fn ($q) => $q->where('third_party_uuid', $thirdPartyUuid))
            ->get();

        if ($candidatas->isEmpty()) {
            $gps = \App\Models\DriverLocation::query()
                ->where('third_party_uuid', $thirdPartyUuid)
                ->whereDate('recorded_at', $fecha)
                ->orderBy('recorded_at', 'asc')
                ->first(['vehicle_uuid', 'project_uuid', 'company_uuid']);

            if ($gps) {
                $placaGps = $gps->vehicle_uuid
                    ? \App\Models\Vehicle::where('uuid', $gps->vehicle_uuid)->value('vehicle_license_plate')
                    : null;
                $candidatas = ServiceDeliveryControlSheet::query()
                    ->whereDate('service_date', $fecha)
                    ->when($gps->project_uuid, fn ($q) => $q->where('project_uuid', $gps->project_uuid))
                    ->where(function ($q) use ($gps, $placaGps) {
                        if ($gps->vehicle_uuid) {
                            $q->whereHas('internalControl', fn ($qq) => $qq->where('vehicle_uuid', $gps->vehicle_uuid));
                        }
                        if ($placaGps) {
                            $q->orWhereHas('subcontractedControl', fn ($qq) => $qq->where('vehicle_license_plate', $placaGps));
                        }
                    })
                    ->get();
            }
        }

        $binario = $this->decodificarImagenMapa($imageBase64);
        // SPEC-002 §7.2: la evidencia de un día cerrado no se toca. Las candidatas
        // cerradas se descartan; si solo había cerradas, se explica por qué.
        $abiertas = $candidatas->filter(fn ($p) => (bool) $p->is_active)->values();
        $cerradas = $candidatas->filter(fn ($p) => ! $p->is_active)->values();

        if ($abiertas->isEmpty()) {
            throw ValidationException::withMessages([
                'service_date' => $cerradas->isNotEmpty()
                    ? 'La planilla de esa fecha ya está cerrada; su evidencia no se puede modificar.'
                    : 'No hay planilla del conductor para esa fecha; no se pudo pegar la captura.',
            ]);
        }

        $pegadas = [];
        foreach ($abiertas as $planilla) {
            $this->guardarImagenMapa($planilla, $binario, $fecha);
            $pegadas[] = $planilla->uuid;
        }

        return ['planillas' => $pegadas, 'service_date' => $fecha];
    }

    /**
     * Valida y decodifica la imagen base64 de la captura del mapa.
     */
    private function decodificarImagenMapa(string $imageBase64): string
    {
        $crudo = trim($imageBase64);
        if (str_contains($crudo, ',')) {
            [, $crudo] = explode(',', $crudo, 2);
        }
        $binario = base64_decode($crudo, true);
        if ($binario === false || strlen($binario) < 1000) {
            throw ValidationException::withMessages(['image_base64' => 'La captura del mapa llegó vacía o corrupta.']);
        }
        if (strlen($binario) > 4 * 1024 * 1024) {
            throw ValidationException::withMessages(['image_base64' => 'La captura supera los 4 MB permitidos.']);
        }
        $info = @getimagesizefromstring($binario);
        if (! $info || ! in_array($info['mime'], ['image/png', 'image/jpeg'], true)) {
            throw ValidationException::withMessages(['image_base64' => 'La captura debe ser una imagen PNG o JPG.']);
        }
        if ($info[0] < 300 || $info[1] < 150) {
            throw ValidationException::withMessages(['image_base64' => 'La captura es demasiado pequeña para el reporte.']);
        }

        return $binario;
    }

    /**
     * Guarda la captura como archivo único ROUTE_MAP de la planilla.
     * Si el ancho supera 1200 px se reduce con GD para no engordar el PDF.
     */
    private function guardarImagenMapa(ServiceDeliveryControlSheet $planilla, string $binario, string $fecha): void
    {
        try {
            if (extension_loaded('gd')) {
                $img = @imagecreatefromstring($binario);
                if ($img) {
                    $ancho = imagesx($img);
                    $alto = imagesy($img);
                    if ($ancho > 1200) {
                        $nuevoAncho = 1200;
                        $nuevoAlto = (int) round($alto * $nuevoAncho / $ancho);
                        $red = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
                        imagecopyresampled($red, $img, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
                        ob_start();
                        imagepng($red);
                        $binario = (string) ob_get_clean();
                        imagedestroy($red);
                    }
                    imagedestroy($img);
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('No se pudo optimizar la captura del mapa: '.$e->getMessage());
        }

        $temporal = tempnam(sys_get_temp_dir(), 'mapa_').'.png';
        file_put_contents($temporal, $binario);

        try {
            $planilla->clearMediaCollection('ROUTE_MAP');
            $planilla->addMedia($temporal)
                ->usingFileName('mapa-recorrido-'.str_replace('-', '', $fecha).'.png')
                ->withCustomProperties(['source' => 'frontend', 'captured_at' => now()->toIso8601String()])
                ->toMediaCollection('ROUTE_MAP');
        } finally {
            @unlink($temporal);
        }
    }

    /**
     * Guarda el cierre de UN solo recorrido (cierre parcial por recorrido).
     * Permite al conductor certificar cada recorrido conforme lo termina, sin
     * verse obligado a diligenciar todos de una vez; la planilla sigue en curso.
     *
     * @return array{planilla: ?Model, route: ?ServiceDeliveryControlSheetRoute}
     */
    public function closeRoute(string $uuid, array $data): array
    {
        $record = $this->getServiceDeliveryControlSheetByUuid($uuid);
        if (! $record) {
            return ['planilla' => null, 'route' => null];
        }

        $routes = $record->routes()->orderBy('order_index')->get();

        $route = null;
        if (! empty($data['route_uuid'])) {
            $route = $routes->firstWhere('uuid', $data['route_uuid']);
        } elseif (isset($data['route_index'])) {
            $route = $routes->get((int) $data['route_index']);
        }

        if (! $route) {
            return ['planilla' => $record, 'route' => null];
        }

        $this->applyRouteClosure($route, $data, $record->company_uuid);

        return [
            'planilla' => $record->fresh(['routes']),
            'route' => $route->fresh(),
        ];
    }

    /**
     * Busca funcionarios ya registrados por número de CC dentro de la empresa.
     * Retorna coincidencias con nombre y veces usado, para reutilizar datos.
     *
     * @return array<int, array{funcionario_cc: string, funcionario_nombre: string, usos: int}>
     */
    public function searchFuncionarios(string $companyUuid, string $cc): array
    {
        $cc = trim($cc);
        if ($cc === '') {
            return [];
        }

        return ServiceDeliveryControlSheetRoute::query()
            ->join('service_delivery_control_sheet as s', 's.uuid', '=', 'service_delivery_control_sheet_routes.service_delivery_control_sheet_uuid')
            ->where('s.company_uuid', $companyUuid)
            ->whereNotNull('service_delivery_control_sheet_routes.funcionario_cc')
            ->where('service_delivery_control_sheet_routes.funcionario_cc', 'like', '%'.$cc.'%')
            ->selectRaw('service_delivery_control_sheet_routes.funcionario_cc, MAX(service_delivery_control_sheet_routes.funcionario_nombre) as funcionario_nombre, COUNT(*) as usos')
            ->groupBy('service_delivery_control_sheet_routes.funcionario_cc')
            ->orderByDesc('usos')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'funcionario_cc' => $row->funcionario_cc,
                'funcionario_nombre' => $row->funcionario_nombre,
                'usos' => (int) $row->usos,
            ])
            ->all();
    }

    /**
     * Persiste el cierre de cada recorrido (hora fin, km final, peajes, novedad)
     * y sus dos firmas digitales cuando la planilla tiene más de un recorrido.
     */
    private function syncRouteClosures(ServiceDeliveryControlSheet $record, array $data): void
    {
        $routesData = $data['routes'] ?? null;
        if (! is_array($routesData) || count($routesData) === 0) {
            return;
        }

        $existing = $record->routes()->orderBy('order_index')->get();

        foreach ($existing as $index => $route) {
            $closure = $routesData[$index] ?? null;
            if (! is_array($closure)) {
                continue;
            }

            $this->applyRouteClosure($route, $closure, $record->company_uuid);
        }
    }

    private function applyRouteClosure(ServiceDeliveryControlSheetRoute $route, array $closure, string $companyUuid): void
    {
        $funcionarioNombre = isset($closure['funcionario_nombre']) && trim((string) $closure['funcionario_nombre']) !== ''
            ? mb_substr(trim((string) $closure['funcionario_nombre']), 0, 150)
            : ($route->funcionario_nombre ?? null);
        $funcionarioCc = isset($closure['funcionario_cc']) && trim((string) $closure['funcionario_cc']) !== ''
            ? mb_substr(trim((string) $closure['funcionario_cc']), 0, 30)
            : ($route->funcionario_cc ?? null);

        if (empty($funcionarioNombre)) {
            throw ValidationException::withMessages(['funcionario_nombre' => 'El nombre y apellido del funcionario es obligatorio para cada recorrido.']);
        }
        if (empty($funcionarioCc)) {
            throw ValidationException::withMessages(['funcionario_cc' => 'El número de CC del funcionario es obligatorio para cada recorrido.']);
        }

        // SPEC-002 §7.3: un envío parcial NO debe borrar lo ya cerrado. Antes cada
        // campo ausente se escribía como null/0, así que reenviar solo las firmas
        // dejaba el recorrido sin hora ni kilometraje de llegada.
        $cambios = [
            'funcionario_nombre' => $funcionarioNombre,
            'funcionario_cc' => $funcionarioCc,
        ];
        if (array_key_exists('end_time', $closure)) {
            $cambios['end_time'] = $closure['end_time'] ?: null;
        }
        if (array_key_exists('ending_kilometer', $closure)) {
            $cambios['ending_kilometer'] = ($closure['ending_kilometer'] !== null && $closure['ending_kilometer'] !== '')
                ? $closure['ending_kilometer']
                : null;
        }
        if (array_key_exists('number_of_tolls', $closure)) {
            $cambios['number_of_tolls'] = $closure['number_of_tolls'] ?: 0;
        }
        if (array_key_exists('total_toll_value', $closure)) {
            $cambios['total_toll_value'] = $closure['total_toll_value'] ?: 0;
        }
        if (array_key_exists('end_novelty', $closure)) {
            $cambios['end_novelty'] = $closure['end_novelty'] ?: null;
        }

        $route->update($cambios);

        // SPEC-002 §7.3: ya no se borran las firmas anteriores. La firma que llega
        // reemplaza solo la de SU rol; un reenvío parcial conserva la otra.
        if (! empty($closure['funcionario_signature'])) {
            $this->signatureService->store([
                'signature' => $closure['funcionario_signature'],
                'entity_type' => 'App\\Models\\ServiceDeliveryControlSheetRoute',
                'entity_id' => $route->id,
                'company_uuid' => $companyUuid,
                'signer_role' => \App\Models\Signature::ROL_FUNCIONARIO,
                'scope' => 'recorrido',
            ]);
        }

        if (! empty($closure['conductor_signature'])) {
            $this->signatureService->store([
                'signature' => $closure['conductor_signature'],
                'entity_type' => 'App\\Models\\ServiceDeliveryControlSheetRoute',
                'entity_id' => $route->id,
                'company_uuid' => $companyUuid,
                'signer_role' => \App\Models\Signature::ROL_CONDUCTOR,
                'scope' => 'recorrido',
            ]);
        }
    }

    private function createControlForSheet(ServiceDeliveryControlSheet $sheet, array $data, string $type, bool $isChild = false): void
    {
        if (! $this->isExternalType($type)) {
            $this->serviceInternalControlService->createServiceInternalControl([
                'company_uuid' => $sheet->company_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'] ?? null,
                'fuec_uuid' => $data['fuec_uuid'] ?? null,
                'service_delivery_control_sheet_uuid' => $sheet->uuid,
                'is_active' => $isChild ? false : ($data['is_active'] ?? true),
            ]);

            return;
        }

        // Vehículo externo / subcontratado: placa + conductor en texto libre.
        // Si además viene vehicle_uuid de plataforma, se guarda también el vínculo interno para trazabilidad.
        if (! empty($data['vehicle_uuid']) || ! empty($data['third_party_uuid'])) {
            $this->serviceInternalControlService->createServiceInternalControl([
                'company_uuid' => $sheet->company_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'] ?? null,
                'fuec_uuid' => $data['fuec_uuid'] ?? null,
                'service_delivery_control_sheet_uuid' => $sheet->uuid,
                'is_active' => $isChild ? false : ($data['is_active'] ?? true),
            ]);
        }

        if (! empty($data['vehicle_license_plate']) && ! empty($data['driver_name_and_surname'])) {
            $this->serviceInternalControlSubcontractedService->createServiceInternalControlSubcontracted([
                'vehicle_class_uuid' => $data['vehicle_class_uuid'] ?? null,
                'service_delivery_control_sheet_uuid' => $sheet->uuid,
                'fuec_uuid' => $data['fuec_uuid'] ?? null,
                'vehicle_license_plate' => $data['vehicle_license_plate'],
                'driver_name_and_surname' => $data['driver_name_and_surname'],
                'driver_license_number' => $data['driver_license_number'] ?? 'N/A',
                'is_active' => $isChild ? false : ($data['is_active'] ?? true),
            ]);
        }
    }

    public function createServiceDeliveryControlSheet(array $data): Model
    {
        $type = $this->normalizeType($data['type_of_control_sheet'] ?? 'DIRECTO_CON_LA_EMPRESA');

        return $this->transaction(fn () => $this->persistirHoja($data, $type));
    }

    /**
     * Creación masiva por proyecto (SPEC-009): una hoja de control directo por
     * cada coche marcado, cuya asignación activa resuelve el par
     * conductor↔vehículo y el responsable de servicio. La fuente de verdad es
     * el proyecto: lo que venga en el cuerpo sobre pares, responsable o FUEC
     * se ignora.
     *
     * @param  array<string, mixed>  $data
     * @return array{created: int, daily_sheets: int, sheets: array<int, Model>}
     */
    public function createBulkForProject(array $data): array
    {
        $companyUuid = $this->resolverEmpresa($data['company_uuid'] ?? null);
        if (! $companyUuid) {
            throw ValidationException::withMessages([
                'company_uuid' => 'No hay empresa activa en la petición.',
            ]);
        }

        $projectUuid = (string) $data['project_uuid'];
        // Defensa en profundidad: el request ya acota el proyecto con sus
        // scopes, pero el servicio también puede invocarse desde otros flujos.
        if (! Project::query()->where('company_uuid', $companyUuid)->where('uuid', $projectUuid)->exists()) {
            throw ValidationException::withMessages([
                'project_uuid' => 'El proyecto no pertenece a la empresa activa.',
            ]);
        }

        $porVehiculo = $this->asignacionesActivasPorVehiculo($projectUuid);
        if (empty($porVehiculo)) {
            throw ValidationException::withMessages([
                'vehicle_uuids' => 'El proyecto no tiene asignaciones activas de vehículos.',
            ]);
        }

        $solicitados = array_values(array_unique(array_filter((array) ($data['vehicle_uuids'] ?? []))));
        $this->validarCochesSolicitados($solicitados, $porVehiculo);

        // Fechas y ruta diaria se validan UNA vez para todo el bulk, no por hoja.
        $startRaw = $data['start_date'] ?? $data['service_date'] ?? null;
        $endRaw = $data['end_date'] ?? null;
        $this->validateProjectDates($projectUuid, $startRaw, $endRaw);

        $hojas = $this->transaction(function () use ($data, $companyUuid, $projectUuid, $solicitados, $porVehiculo) {
            $creadas = [];
            foreach ($solicitados as $vehicleUuid) {
                $asignacion = $porVehiculo[$vehicleUuid];

                // Ignora cualquier par/responsable/FUEC del cuerpo: la
                // asignación vigente del proyecto manda (SPEC-009 decisión 5).
                $datosHoja = $data;
                $datosHoja['company_uuid'] = $companyUuid;
                $datosHoja['project_uuid'] = $projectUuid;
                $datosHoja['type_of_control_sheet'] = 'DIRECTO_CON_LA_EMPRESA';
                $datosHoja['vehicle_uuid'] = $vehicleUuid;
                $datosHoja['third_party_uuid'] = $asignacion->third_party_uuid;
                $datosHoja['official_name_and_surname'] = $this->nombreDeConductor($asignacion->thirdParty);
                $datosHoja['fuec_uuid'] = null;
                $datosHoja['vehicle_license_plate'] = null;
                $datosHoja['driver_name_and_surname'] = null;
                $datosHoja['driver_license_number'] = null;
                unset($datosHoja['vehicle_uuids']);

                $creadas[] = $this->persistirHoja($datosHoja, 'DIRECTO_CON_LA_EMPRESA', false);
            }

            return $creadas;
        });

        // Transacción única: o se crean todas (con sus planillas diarias) o no
        // persiste ninguna.
        $dailySheets = ServiceDeliveryControlSheet::query()
            ->whereIn('parent_uuid', array_column($hojas, 'uuid'))
            ->count('uuid');

        return [
            'created' => count($hojas),
            'daily_sheets' => (int) $dailySheets,
            'sheets' => $hojas,
        ];
    }

    /**
     * Actualización masiva de todas las hojas de control abiertas de un proyecto:
     * permite ampliar o modificar el rango de fechas (y opcionalmente el recorrido)
     * en bloque para el proyecto completo o para los vehículos seleccionados,
     * sincronizando automáticamente las planillas diarias hijas y la vigencia del proyecto.
     *
     * @param  array<string, mixed>  $data
     * @return array{updated: int, daily_sheets: int, sheets: array<int, Model>}
     */
    public function updateBulkForProject(array $data): array
    {
        $companyUuid = $this->resolverEmpresa($data['company_uuid'] ?? null);
        if (! $companyUuid) {
            throw ValidationException::withMessages([
                'company_uuid' => 'No hay empresa activa en la petición.',
            ]);
        }

        $projectUuid = (string) $data['project_uuid'];
        $project = Project::query()
            ->where('company_uuid', $companyUuid)
            ->where('uuid', $projectUuid)
            ->first();

        if (! $project) {
            throw ValidationException::withMessages([
                'project_uuid' => 'El proyecto no pertenece a la empresa activa.',
            ]);
        }

        $newStart = ! empty($data['start_date'])
            ? Carbon::parse($data['start_date'])->toDateString()
            : null;
        $newEnd = array_key_exists('end_date', $data)
            ? (! empty($data['end_date']) ? Carbon::parse($data['end_date'])->toDateString() : $newStart)
            : null;

        if ($newStart && $newEnd && Carbon::parse($newEnd)->lt(Carbon::parse($newStart))) {
            throw ValidationException::withMessages([
                'end_date' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            ]);
        }

        $dailyRoute = array_key_exists('daily_route', $data)
            ? trim((string) ($data['daily_route'] ?? ''))
            : null;

        $vehicleUuids = ! empty($data['vehicle_uuids'])
            ? array_values(array_unique(array_filter((array) $data['vehicle_uuids'])))
            : null;

        $actualizadas = $this->transaction(function () use ($project, $newStart, $newEnd, $dailyRoute, $vehicleUuids) {
            // Si las nuevas fechas amplían la vigencia actual del proyecto, se
            // extiende el proyecto para mantener coherencia entre proyecto y hojas.
            $projStart = $project->start_date ? Carbon::parse($project->start_date)->toDateString() : null;
            $projEnd = $project->completion_date ? Carbon::parse($project->completion_date)->toDateString() : null;
            $cambiosProyecto = [];

            if ($newStart && (! $projStart || $newStart < $projStart)) {
                $cambiosProyecto['start_date'] = $newStart;
            }
            if ($newEnd && (! $projEnd || $newEnd > $projEnd)) {
                $cambiosProyecto['completion_date'] = $newEnd;
            }
            if (! empty($cambiosProyecto)) {
                $project->update($cambiosProyecto);
            }

            return $this->syncProjectControlSheetsDates(
                $project,
                $newStart,
                $newEnd,
                $dailyRoute,
                $vehicleUuids
            );
        });

        $uuidsPadres = array_map(static fn (Model $h) => $h->getAttribute('uuid'), $actualizadas);
        $dailySheets = empty($uuidsPadres)
            ? 0
            : ServiceDeliveryControlSheet::query()
                ->whereIn('parent_uuid', $uuidsPadres)
                ->count('uuid');

        return [
            'updated' => count($actualizadas),
            'daily_sheets' => (int) $dailySheets,
            'sheets' => $actualizadas,
        ];
    }

    /**
     * Sincroniza las fechas (y opcionalmente el recorrido) de todas las hojas
     * padre de un proyecto (incluyendo las que ya se habían cerrado al terminar
     * su vigencia anterior), propagando a sus planillas diarias abiertas y
     * generando los días nuevos cuando el rango se extiende.
     *
     * @param  array<int, string>|null  $vehicleUuids
     * @return array<int, Model>
     */
    public function syncProjectControlSheetsDates(
        Project $project,
        ?string $newStart = null,
        ?string $newEnd = null,
        ?string $dailyRoute = null,
        ?array $vehicleUuids = null
    ): array {
        $query = ServiceDeliveryControlSheet::withoutGlobalScopes()
            ->where('project_uuid', $project->uuid)
            ->whereNull('parent_uuid')
            ->where('retirada_de_proyecto', false)
            ->orderBy('id');

        if (! empty($vehicleUuids)) {
            $query->whereHas('internalControl', fn ($q) => $q->whereIn('vehicle_uuid', $vehicleUuids));
        }

        $hojasProyecto = $query->get();
        $actualizadas = [];

        foreach ($hojasProyecto as $hoja) {
            $estabaCerrada = ! (bool) $hoja->is_active;

            $hojaStart = $hoja->start_date
                ? Carbon::parse($hoja->start_date)->toDateString()
                : ($hoja->service_date ? Carbon::parse($hoja->service_date)->toDateString() : null);
            $hojaEnd = $hoja->end_date
                ? Carbon::parse($hoja->end_date)->toDateString()
                : $hojaStart;

            $targetStart = $newStart ?? $hojaStart;
            $targetEnd = $newEnd ?? $hojaEnd;

            $payload = [
                'start_date' => $targetStart,
                'end_date' => $targetEnd,
                'service_date' => $targetStart,
            ];

            if ($dailyRoute !== null) {
                $payload['daily_route'] = $dailyRoute !== '' ? $dailyRoute : null;
            }

            // Si la hoja padre estaba cerrada (porque había finalizado sus días
            // previos), se habilita para permitir la extensión de fechas y la
            // creación de los nuevos días hijos, conservando intactos los días
            // hijos que ya estaban cerrados.
            if ($estabaCerrada) {
                $hoja->update(['is_active' => true]);
            }

            $hojaActualizada = $this->updateServiceDeliveryControlSheet($hoja->uuid, $payload);

            if ($estabaCerrada) {
                $tieneDiasAbiertos = ServiceDeliveryControlSheet::withoutGlobalScopes()
                    ->where('parent_uuid', $hoja->uuid)
                    ->where('is_active', true)
                    ->exists();

                if (! $tieneDiasAbiertos) {
                    $hojaActualizada->update(['is_active' => false]);
                    $hojaActualizada = $hojaActualizada->fresh(['project', 'routes', 'internalControl', 'subcontractedControl']);
                }
            }

            $actualizadas[] = $hojaActualizada;
        }

        return $actualizadas;
    }

    /**
     * Asignaciones activas del proyecto agrupadas por vehículo, con tercero y
     * vehículo precargados. Una fila sin vehículo o sin conductor no puede
     * crear hoja. La práctica actual es una activa por vehículo; si
     * aparece más de una, gana la de ingreso más antiguo.
     *
     * @return array<string, ProjectDriverVehicle>
     */
    private function asignacionesActivasPorVehiculo(string $projectUuid): array
    {
        $asignaciones = ProjectDriverVehicle::query()
            ->where('project_uuid', $projectUuid)
            ->active()
            ->with(['thirdParty', 'vehicle'])
            ->orderBy('id')
            ->get();

        $porVehiculo = [];
        foreach ($asignaciones as $asignacion) {
            if (! $asignacion->vehicle_uuid || ! $asignacion->third_party_uuid) {
                continue;
            }
            $porVehiculo[$asignacion->vehicle_uuid] ??= $asignacion;
        }

        return $porVehiculo;
    }

    /**
     * Todo o nada antes de crear: un coche marcado sin asignación activa en el
     * proyecto cancela el bulk completo con el motivo por cada coche.
     *
     * @param  array<int, string>  $solicitados
     * @param  array<string, ProjectDriverVehicle>  $porVehiculo
     */
    private function validarCochesSolicitados(array $solicitados, array $porVehiculo): void
    {
        $faltantes = array_diff($solicitados, array_keys($porVehiculo));
        if ($faltantes === []) {
            return;
        }

        // Con el scope de empresa activa: no se filtran placas de otras cuentas.
        $placas = Vehicle::query()
            ->whereIn('uuid', $faltantes)
            ->pluck('vehicle_license_plate', 'uuid');

        $mensajes = [];
        foreach ($faltantes as $uuid) {
            $placa = $placas[$uuid] ?? null;
            $coche = $placa ? "el vehículo con placa {$placa}" : "el vehículo {$uuid}";
            $mensajes[] = "{$coche} no tiene una asignación activa en el proyecto.";
        }

        throw ValidationException::withMessages(['vehicle_uuids' => $mensajes]);
    }

    /**
     * Nombre para el responsable de servicio: preferencia del natural
     * (primero + apellido) o razón social del jurídico.
     */
    private function nombreDeConductor(?ThirdParty $conductor): ?string
    {
        if (! $conductor) {
            return null;
        }

        if ($conductor->person_type === 'JURIDICA') {
            return $conductor->business_name ?: null;
        }

        return trim(($conductor->first_name ?? '').' '.($conductor->last_name ?? '')) ?: null;
    }

    /**
     * Cuerpo transaccional de la creación de UNA hoja. No abre su propia
     * transacción: el flujo individual la envuelve y el bulk comparte una
     * única para todas (evita N transacciones anidadas).
     *
     * @param  array<string, mixed>  $data
     */
    private function persistirHoja(array $data, string $type, bool $validarFechas = true): Model
    {
        $companyUuid = $this->resolverEmpresa($data['company_uuid'] ?? null);
        if (! $companyUuid) {
            throw ValidationException::withMessages([
                'company_uuid' => 'No hay empresa activa en la petición.',
            ]);
        }

        $this->validarPertenencia($companyUuid, $data);

        $startRaw = $data['start_date'] ?? $data['service_date'] ?? null;
        $endRaw = $data['end_date'] ?? null;
        if ($validarFechas) {
            $this->validateProjectDates($data['project_uuid'] ?? null, $startRaw, $endRaw);
        }

        $dailyRoute = $this->buildDailyRoute($data['daily_route'] ?? null, $data['routes'] ?? null);

        $record = ServiceDeliveryControlSheet::create([
            'official_name_and_surname' => $data['official_name_and_surname'] ?? null,
            'service_date' => $startRaw,
            'start_date' => $startRaw,
            'end_date' => $endRaw,
            'daily_route' => $dailyRoute,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'total_hours' => $data['total_hours'] ?? null,
            'starting_kilometer' => $data['starting_kilometer'] ?? null,
            'ending_kilometer' => $data['ending_kilometer'] ?? null,
            'number_of_tolls' => $data['number_of_tolls'] ?? null,
            'total_toll_value' => $data['total_toll_value'] ?? null,
            'type_of_control_sheet' => $type,
            'is_active' => $data['is_active'] ?? true,
            'company_uuid' => $companyUuid,
            'project_uuid' => $data['project_uuid'] ?? null,
        ]);

        $this->createControlForSheet($record, $data, $type, false);
        $this->syncRoutes($record, $data['routes'] ?? null);

        // Si daily_route no venía pero sí routes, ya quedó construido. Si no hay routes ni daily_route, se deja null (compatible).
        // Multi-día: generar hijos diarios
        $startDate = $startRaw ? Carbon::parse($startRaw) : null;
        $endDate = $endRaw ? Carbon::parse($endRaw) : null;

        if ($startDate && $endDate && $endDate->greaterThan($startDate)) {
            // Los hijos cubren TODOS los días incluyendo el de inicio, para que
            // cada día (incluido el primero) tenga su planilla diaria trabajable.
            $currentDate = $startDate->copy();

            while (! $currentDate->greaterThan($endDate)) {
                $this->crearHijaDiaria($record, $currentDate->copy(), $data, $type);
                $currentDate->addDay();
            }
        }

        return $record->fresh(['project', 'routes', 'children']);
    }

    /**
     * Crea una planilla diaria hija con su control y los recorridos heredados
     * del padre. La usan la creación y la ampliación de rango al editar.
     */
    private function crearHijaDiaria(ServiceDeliveryControlSheet $parent, Carbon $fecha, array $data, string $type): ServiceDeliveryControlSheet
    {
        $child = ServiceDeliveryControlSheet::create([
            'official_name_and_surname' => $data['official_name_and_surname'] ?? $parent->official_name_and_surname,
            'service_date' => $fecha->toDateString(),
            'start_date' => $parent->start_date,
            'end_date' => $parent->end_date,
            'parent_uuid' => $parent->uuid,
            'daily_route' => $parent->daily_route,
            'type_of_control_sheet' => $type,
            'is_active' => true,
            'company_uuid' => $parent->company_uuid,
            'project_uuid' => $parent->project_uuid,
        ]);

        // La edición puede ser parcial (p. ej. solo el proyecto): el control de
        // la hija nueva hereda la pareja, el FUEC y los datos del conductor del
        // padre cuando el cuerpo no los trae. Sin esto, `vehicle_uuid` y
        // `third_party_uuid` (NOT NULL) romperían el alta del control.
        $this->createControlForSheet($child, $this->datosDeControlHeredados($parent, $data), $type, true);

        foreach ($parent->routes()->get() as $route) {
            ServiceDeliveryControlSheetRoute::create([
                'uuid' => (string) Str::uuid(),
                'service_delivery_control_sheet_uuid' => $child->uuid,
                'order_index' => $route->order_index,
                'origin' => $route->origin,
                'destination' => $route->destination,
                'funcionario_nombre' => $route->funcionario_nombre,
                'funcionario_cc' => $route->funcionario_cc,
                'is_active' => true,
            ]);
        }

        return $child;
    }

    public function updateServiceDeliveryControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $this->asegurarEditable($record, 'update');

            // El tipo define el control hijo y el flujo de cierre: no se cruza
            // interno ↔ externo después de creada (dejaría controles huérfanos).
            if (isset($data['type_of_control_sheet'])
                && $this->normalizeType($data['type_of_control_sheet']) !== $record->type_of_control_sheet) {
                throw ValidationException::withMessages([
                    'type_of_control_sheet' => 'El tipo de hoja no se puede cambiar después de crearla.',
                ]);
            }
            $type = $record->type_of_control_sheet;

            $newProject = $data['project_uuid'] ?? $record->project_uuid;
            $newStart = ! empty($data['start_date'])
                ? Carbon::parse($data['start_date'])->toDateString()
                : ($record->start_date ? Carbon::parse($record->start_date)->toDateString() : null);

            // `end_date` vacío significa servicio de un solo día (mismo criterio
            // del formulario); si la clave no llega, se conserva la del registro.
            $newEnd = array_key_exists('end_date', $data)
                ? (! empty($data['end_date']) ? Carbon::parse($data['end_date'])->toDateString() : $newStart)
                : ($record->end_date ? Carbon::parse($record->end_date)->toDateString() : $newStart);

            if ($newStart && $newEnd && Carbon::parse($newEnd)->lt(Carbon::parse($newStart))) {
                throw ValidationException::withMessages([
                    'end_date' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
                ]);
            }

            // El proyecto, el vehículo, el tercero y el FUEC deben ser de la
            // empresa del registro; la planilla no cambia de empresa.
            $this->validarPertenencia($record->company_uuid, [
                'project_uuid' => $newProject,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'] ?? null,
                'fuec_uuid' => $data['fuec_uuid'] ?? null,
            ]);
            $this->validateProjectDates($newProject, $newStart, $newEnd);

            $dailyRoute = $this->buildDailyRoute(
                $data['daily_route'] ?? $record->daily_route,
                $data['routes'] ?? null
            );

            // SPEC-002 §5.2: la declaración de disponibilidad se valida contra
            // el estado resultante (motivo + ausencia de recorridos).
            $dayKind = $data['day_kind'] ?? $record->day_kind;
            $availabilityReason = $data['availability_reason'] ?? $record->availability_reason;
            $this->validarDisponibilidad($record, $dayKind, $availabilityReason);

            $record->update([
                'official_name_and_surname' => $data['official_name_and_surname'] ?? $record->official_name_and_surname,
                // La hija conserva su día; el padre toma la fecha de inicio.
                'service_date' => $record->parent_uuid
                    ? ($data['service_date'] ?? $record->service_date)
                    : ($newStart ?? $record->service_date),
                'start_date' => $newStart,
                'end_date' => $newEnd,
                'daily_route' => $dailyRoute,
                'start_time' => $data['start_time'] ?? $record->start_time,
                'end_time' => $data['end_time'] ?? $record->end_time,
                'total_hours' => $data['total_hours'] ?? $record->total_hours,
                'starting_kilometer' => $data['starting_kilometer'] ?? $record->starting_kilometer,
                'ending_kilometer' => $data['ending_kilometer'] ?? $record->ending_kilometer,
                'number_of_tolls' => $data['number_of_tolls'] ?? $record->number_of_tolls,
                'total_toll_value' => $data['total_toll_value'] ?? $record->total_toll_value,
                'type_of_control_sheet' => $type,
                'day_kind' => $dayKind,
                'availability_reason' => $dayKind === ServiceDeliveryControlSheet::DIA_DISPONIBILIDAD
                    ? mb_substr(trim((string) $availabilityReason), 0, 255)
                    : null,
                // `is_active` no se toma del cuerpo: lo controla el cierre.
                'project_uuid' => $newProject,
            ]);

            if (array_key_exists('routes', $data)) {
                $this->syncRoutes($record, $data['routes']);
            }

            $this->sincronizarControlDeHoja($record, $data, $type);

            if (! $record->parent_uuid) {
                $this->sincronizarDiasDeLaHoja($record, $data, $type);
            }

            return $record->fresh(['project', 'routes', 'internalControl', 'subcontractedControl']);
        });
    }

    /**
     * Sincroniza el control interno/subcontratado de la hoja con los datos del
     * formulario (vehículo, conductor, placa, clase y FUEC). Si la hoja no
     * tenía control, lo crea; así la edición también repara hojas históricas.
     *
     * @param  array<string, mixed>  $data
     */
    private function sincronizarControlDeHoja(ServiceDeliveryControlSheet $sheet, array $data, string $type): void
    {
        $valor = static fn (string $clave, mixed $actual): mixed => array_key_exists($clave, $data)
            ? $data[$clave]
            : $actual;

        $control = $sheet->internalControl()->first();

        if (! $this->isExternalType($type)) {
            $vehicleUuid = $valor('vehicle_uuid', $control?->vehicle_uuid);
            $thirdPartyUuid = $valor('third_party_uuid', $control?->third_party_uuid);

            if (! $control && ! $vehicleUuid && ! $thirdPartyUuid) {
                return;
            }

            $payload = [
                'company_uuid' => $sheet->company_uuid,
                'vehicle_uuid' => $vehicleUuid,
                'third_party_uuid' => $thirdPartyUuid,
                'fuec_uuid' => $valor('fuec_uuid', $control?->fuec_uuid),
                'service_delivery_control_sheet_uuid' => $sheet->uuid,
                'is_active' => $control ? (bool) $control->is_active : true,
            ];

            if ($control) {
                $this->serviceInternalControlService->updateServiceInternalControl($control->uuid, $payload);
            } else {
                $this->serviceInternalControlService->createServiceInternalControl($payload);
            }

            return;
        }

        // Externo/subcontratado: el vínculo de plataforma es opcional y solo se
        // mantiene si la hoja ya lo tenía o si el formulario envía un par.
        $vehicleUuid = $valor('vehicle_uuid', $control?->vehicle_uuid);
        $thirdPartyUuid = $valor('third_party_uuid', $control?->third_party_uuid);

        if ($control || $vehicleUuid || $thirdPartyUuid) {
            $payload = [
                'company_uuid' => $sheet->company_uuid,
                'vehicle_uuid' => $vehicleUuid,
                'third_party_uuid' => $thirdPartyUuid,
                'fuec_uuid' => $valor('fuec_uuid', $control?->fuec_uuid),
                'service_delivery_control_sheet_uuid' => $sheet->uuid,
                'is_active' => $control ? (bool) $control->is_active : true,
            ];

            if ($control) {
                $this->serviceInternalControlService->updateServiceInternalControl($control->uuid, $payload);
            } else {
                $this->serviceInternalControlService->createServiceInternalControl($payload);
            }
        }

        $sub = $sheet->subcontractedControl()->first();
        $placa = $valor('vehicle_license_plate', $sub?->vehicle_license_plate);
        $conductor = $valor('driver_name_and_surname', $sub?->driver_name_and_surname);

        if (! $placa && ! $conductor && ! $sub) {
            return;
        }

        $payload = [
            'vehicle_class_uuid' => $valor('vehicle_class_uuid', $sub?->vehicle_class_uuid),
            'fuec_uuid' => $valor('fuec_uuid', $sub?->fuec_uuid),
            'service_delivery_control_sheet_uuid' => $sheet->uuid,
            'vehicle_license_plate' => $placa,
            'driver_name_and_surname' => $conductor,
            'driver_license_number' => $valor('driver_license_number', $sub?->driver_license_number) ?: 'N/A',
            'is_active' => $sub ? (bool) $sub->is_active : true,
        ];

        if ($sub) {
            $this->serviceInternalControlSubcontractedService->updateServiceInternalControlSubcontracted($sub->uuid, $payload);
        } else {
            $this->serviceInternalControlSubcontractedService->createServiceInternalControlSubcontracted($payload);
        }
    }

    /**
     * Ajusta las planillas diarias al rango de fechas del padre: propaga a las
     * abiertas, crea los días nuevos y elimina solo días abiertos sin evidencia
     * (los cerrados o con datos operativos bloquean el recorte con 422).
     *
     * @param  array<string, mixed>  $data
     */
    private function sincronizarDiasDeLaHoja(ServiceDeliveryControlSheet $parent, array $data, string $type): void
    {
        if (! $parent->start_date || ! $parent->end_date) {
            return;
        }

        $inicio = Carbon::parse($parent->start_date)->startOfDay();
        $fin = Carbon::parse($parent->end_date)->startOfDay();

        $esperados = [];
        for ($dia = $inicio->copy(); ! $dia->greaterThan($fin); $dia->addDay()) {
            $esperados[] = $dia->toDateString();
        }

        $hijas = $parent->children()->get()
            ->filter(static fn (ServiceDeliveryControlSheet $hija) => $hija->service_date !== null)
            ->keyBy(
                static fn (ServiceDeliveryControlSheet $hija) => Carbon::parse($hija->service_date)->toDateString()
            );

        // Primero se validan los recortes: si alguno tiene evidencia, no se
        // toca nada (la transacción revierte cualquier cambio previo).
        foreach ($hijas as $fecha => $hija) {
            if (in_array($fecha, $esperados, true)) {
                continue;
            }

            if (! $hija->is_active || $this->hijaTieneEvidencia($hija)) {
                throw ValidationException::withMessages([
                    'end_date' => 'No se puede recortar el rango: la planilla del '
                        .Carbon::parse($fecha)->format('d/m/Y')
                        .' ya tiene evidencia registrada.',
                ]);
            }
        }

        // Propaga proyecto, tipo, rango y pareja a las hijas abiertas; las
        // cerradas son evidencia y no se modifican.
        foreach ($hijas as $hija) {
            if (! $hija->is_active) {
                continue;
            }

            $hija->update([
                'project_uuid' => $parent->project_uuid,
                'type_of_control_sheet' => $type,
                'start_date' => $parent->start_date,
                'end_date' => $parent->end_date,
                'official_name_and_surname' => $parent->official_name_and_surname,
            ]);

            $this->sincronizarControlDeHoja($hija, $data, $type);
        }

        // Elimina los días que quedaron fuera del rango (ya validados).
        foreach ($hijas as $fecha => $hija) {
            if (in_array($fecha, $esperados, true)) {
                continue;
            }

            $hija->internalControl()->delete();
            $hija->subcontractedControl()->delete();
            $hija->routes()->delete();
            $hija->delete();
        }

        // Crea las planillas diarias de los días nuevos. La hoja de un solo día
        // es su propia planilla (mismo criterio de la creación): no se crea una
        // hija que duplique ese día.
        if (! $inicio->equalTo($fin)) {
            foreach ($esperados as $fecha) {
                if ($hijas->has($fecha)) {
                    continue;
                }

                $this->crearHijaDiaria($parent, Carbon::parse($fecha), $data, $type);
            }
        }
    }

    /**
     * Datos del control que hereda una hija nueva del padre cuando el cuerpo de
     * la edición no los envía (edición parcial).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function datosDeControlHeredados(ServiceDeliveryControlSheet $parent, array $data): array
    {
        $interno = $parent->internalControl()->first();
        $subcontratado = $parent->subcontractedControl()->first();

        return array_filter($data, static fn (mixed $valor): bool => $valor !== null) + [
            'vehicle_uuid' => $interno?->vehicle_uuid,
            'third_party_uuid' => $interno?->third_party_uuid,
            'fuec_uuid' => $interno?->fuec_uuid ?? $subcontratado?->fuec_uuid,
            'vehicle_class_uuid' => $subcontratado?->vehicle_class_uuid,
            'vehicle_license_plate' => $subcontratado?->vehicle_license_plate,
            'driver_name_and_surname' => $subcontratado?->driver_name_and_surname,
            'driver_license_number' => $subcontratado?->driver_license_number,
        ];
    }

    /**
     * ¿La planilla diaria ya registra operación? Un día cerrado o con horas,
     * kilómetros, recorridos o firmas no puede desaparecer al recortar el rango.
     */
    private function hijaTieneEvidencia(ServiceDeliveryControlSheet $hija): bool
    {
        foreach (['start_time', 'end_time', 'total_hours', 'starting_kilometer', 'ending_kilometer'] as $campo) {
            if (! empty($hija->{$campo})) {
                return true;
            }
        }

        if ($hija->routes()->exists()) {
            return true;
        }

        return Signature::query()
            ->where('entity_type', $hija->getMorphClass())
            ->where('entity_id', $hija->getKey())
            ->exists();
    }

    public function deleteServiceDeliveryControlSheet(string $uuid): void
    {
        try {
            $this->asegurarEditable($this->findByUuid($uuid), 'delete');
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('ServiceDeliveryControlSheetService@deleteServiceDeliveryControlSheet: '.$e->getMessage());
            throw $e;
        }
    }

    public function startServiceDeliveryControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            // SPEC-002 §7.2: /start también muta la planilla (hora, km, FUEC).
            $this->asegurarEditable($record, 'start');
            $record->update([
                'start_time' => $data['start_time'] ?? $record->start_time,
                'starting_kilometer' => $data['starting_kilometer'] ?? $record->starting_kilometer,
            ]);

            if (! empty($data['fuec_uuid'])) {
                if (! $this->isExternalType($record->type_of_control_sheet) && $record->internalControl) {
                    $this->serviceInternalControlService->updateServiceInternalControl($record->internalControl->uuid, [
                        'fuec_uuid' => $data['fuec_uuid'],
                    ]);
                }
            }

            return $record->fresh();
        });
    }

    public function closeServiceDeliveryControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            // Evita el doble cierre: reescribiría horas, km y volvería a firmar.
            $this->asegurarEditable($record, 'close');

            $pendingChildren = ServiceDeliveryControlSheet::query()
                ->where('parent_uuid', $record->uuid)
                ->where('is_active', true)
                ->count();

            if ($pendingChildren > 0) {
                throw ValidationException::withMessages([
                    'close' => "No se puede cerrar el servicio hasta completar todas las planillas diarias ({$pendingChildren} pendiente(s)).",
                ]);
            }

            // SPEC-002 §5.2: al cerrar se persiste la declaración del día.
            // La UI envía day_kind/availability_reason cuando no hubo recorridos.
            $dayKind = $data['day_kind'] ?? $record->day_kind ?? ServiceDeliveryControlSheet::DIA_OPERACION;
            $availabilityReason = $data['availability_reason'] ?? $record->availability_reason;
            $this->validarDisponibilidad($record, $dayKind, $availabilityReason);

            $record->update([
                'end_time' => $data['end_time'] ?? $record->end_time,
                'ending_kilometer' => $data['ending_kilometer'] ?? $record->ending_kilometer,
                'total_hours' => $data['total_hours'] ?? $record->total_hours,
                'number_of_tolls' => $data['number_of_tolls'] ?? $record->number_of_tolls,
                'total_toll_value' => $data['total_toll_value'] ?? $record->total_toll_value,
                'day_kind' => $dayKind,
                'availability_reason' => $dayKind === ServiceDeliveryControlSheet::DIA_DISPONIBILIDAD
                    ? mb_substr(trim((string) $availabilityReason), 0, 255)
                    : null,
                'is_active' => false,
            ]);

            if (! empty($data['funcionario_signature'])) {
                $this->signatureService->store([
                    'signature' => $data['funcionario_signature'],
                    'entity_type' => 'App\\Models\\ServiceDeliveryControlSheet',
                    'entity_id' => $record->id,
                    'company_uuid' => $record->company_uuid,
                    'signer_role' => \App\Models\Signature::ROL_FUNCIONARIO,
                    'scope' => 'planilla',
                ]);
            }

            if (! empty($data['conductor_signature'])) {
                $this->signatureService->store([
                    'signature' => $data['conductor_signature'],
                    'entity_type' => 'App\\Models\\ServiceDeliveryControlSheet',
                    'entity_id' => $record->id,
                    'company_uuid' => $record->company_uuid,
                    'signer_role' => \App\Models\Signature::ROL_CONDUCTOR,
                    'scope' => 'planilla',
                ]);
            }

            // Cierre por recorrido: cuando la planilla tiene más de un recorrido,
            // cada uno persiste sus datos de llegada y sus dos firmas.
            // Con un solo recorrido también se persiste (el frontend envía routes[0]
            // con funcionario y cierre) para que el funcionario no se pierda.
            $this->syncRouteClosures($record, $data);

            if (isset($data['routes']) && is_array($data['routes'])) {
                $this->syncRoutes($record, $data['routes']);
            }

            // Validación final: si la planilla tiene recorridos, cada uno debe tener
            // funcionario almacenado. Evita cerrar el recorrido 1 sin nombre/CC.
            $rutasFinales = $record->routes()->orderBy('order_index')->get();
            foreach ($rutasFinales as $idx => $rutaFinal) {
                if (empty(trim((string) ($rutaFinal->funcionario_nombre ?? '')))
                    || empty(trim((string) ($rutaFinal->funcionario_cc ?? '')))) {
                    throw ValidationException::withMessages([
                        'funcionario_cc' => 'El recorrido '.($idx + 1).' requiere nombre y apellido del funcionario y número de CC por que no se esta almacenando la informacion.',
                    ]);
                }
            }

            if (! empty($record->parent_uuid)) {
                $remainingChildren = ServiceDeliveryControlSheet::query()
                    ->where('parent_uuid', $record->parent_uuid)
                    ->where('is_active', true)
                    ->count();

                if ($remainingChildren === 0) {
                    ServiceDeliveryControlSheet::query()
                        ->where('uuid', $record->parent_uuid)
                        ->update(['is_active' => false]);
                }
            }

            return $record->fresh();
        });
    }

    /**
     * SPEC-002 §4 — Cierre con excepción aprobada.
     *
     * Es la salida legítima para una unidad operativa que no pudo completarse:
     * exige motivo y queda con el mismo congelamiento que un cierre normal, pero
     * con distintivo propio en UI y PDF para no pasar por "cerrada normal".
     *
     * A diferencia de close(), NO exige completar recorridos ni firmas: ese es
     * precisamente el motivo de la excepción. Solo ADMIN_EMPRESA y SUPERADMIN
     * pueden invocarlo (permiso service_delivery_control_sheets.close_exception).
     *
     * @param  string  $approvedByUuid  UUID del usuario que aprueba.
     */
    public function cerrarConExcepcion(string $uuid, array $data, string $approvedByUuid): Model
    {
        return $this->transaction(function () use ($uuid, $data, $approvedByUuid) {
            $record = $this->findByUuid($uuid);

            // La excepción no es una puerta trasera al congelamiento (§7.2): solo
            // aplica a una planilla abierta. Una ya cerrada (con o sin excepción)
            // conserva su evidencia y no se puede volver a marcar.
            $this->asegurarEditable($record, 'close_exception');

            $motivo = trim((string) ($data['exception_reason'] ?? ''));
            if ($motivo === '') {
                throw ValidationException::withMessages([
                    'exception_reason' => 'Indique el motivo por el que la planilla no pudo completarse.',
                ]);
            }

            $cambios = [
                'exception_reason' => mb_substr($motivo, 0, 255),
                'exception_approved_by' => $approvedByUuid ?: null,
                'exception_approved_at' => now(),
                'is_active' => false,
            ];

            // Si es día-hijo, el padre se cierra cuando ya no quedan días abiertos.
            $record->update($cambios);

            if (! empty($record->parent_uuid)) {
                $pendientes = ServiceDeliveryControlSheet::query()
                    ->where('parent_uuid', $record->parent_uuid)
                    ->where('is_active', true)
                    ->count();

                if ($pendientes === 0) {
                    ServiceDeliveryControlSheet::query()
                        ->where('uuid', $record->parent_uuid)
                        ->update(['is_active' => false]);
                }
            }

            return $record->fresh(['project', 'routes']);
        });
    }
}
