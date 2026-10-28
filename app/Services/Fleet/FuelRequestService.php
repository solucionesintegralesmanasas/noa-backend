<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\FuelRequest;
use App\Models\User;
use App\Services\BaseService;
use App\Utils\Logger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Solicitudes de tanqueo con aprobación de la empresa.
 *
 * PENDIENTE → APROBADA → USADA
 * PENDIENTE → RECHAZADA
 * PENDIENTE/APROBADA → ANULADA
 * APROBADA → VENCIDA (24 h después de la aprobación)
 */
class FuelRequestService extends BaseService
{
    public const VIGENCIA_HORAS = 24;

    public const ESTADOS_ACTIVOS = ['PENDIENTE', 'APROBADA'];

    protected array $searchableFields = ['code'];

    public function __construct(private readonly VehicleService $vehicleService)
    {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new FuelRequest;
    }

    public function query(): Builder
    {
        return parent::query()->with([
            'vehicle:id,uuid,vehicle_license_plate,brand_uuid,vehicle_class_uuid,line,model',
            'vehicle.brand:uuid,description',
            'vehicle.vehicleClass:uuid,description',
            'driver:uuid,first_name,last_name,company_name,trade_name',
            'approvedBy:uuid,name',
        ]);
    }

    // ─── Creación ──────────────────────────────────────────────────────────

    public function crear(
        string $vehicleUuid,
        ?string $driverUuid = null,
        ?User $usuario = null,
        ?string $companyUuid = null,
        ?string $notes = null
    ): FuelRequest {
        return $this->transaction(function () use ($vehicleUuid, $driverUuid, $usuario, $companyUuid, $notes) {
            // Con scopes (empresa y sede del contexto): un vehículo ajeno no
            // existe aquí; antes el lookup pelado permitía crear solicitudes
            // para el vehículo de otra empresa.
            $vehiculo = \App\Models\Vehicle::query()
                ->with(['company', 'brand', 'vehicleClass'])
                ->where('uuid', $vehicleUuid)
                ->first();

            if (! $vehiculo) {
                throw new \InvalidArgumentException('El vehículo no pertenece a la empresa o sede activa.');
            }

            // La empresa es la del contexto (o la pasada por flujos internos);
            // sin ninguna no se crea: antes caía a la empresa del vehículo y
            // un flujo interno con un uuid arbitrario podía crear la
            // solicitud en cualquier empresa.
            $empresa = $companyUuid
                ?? request()->attributes->get('current_company_uuid');

            if (! $empresa) {
                throw new \InvalidArgumentException('No hay una empresa activa para crear la solicitud.');
            }

            if ($vehiculo->company_uuid !== $empresa) {
                throw new \InvalidArgumentException('El vehículo no pertenece a la empresa activa.');
            }

            // El conductor solo puede solicitar para vehículos asignados a él.
            // Las solicitudes creadas por usuarios de empresa pueden no llevar
            // conductor, por lo que en ese caso se omite la validación.
            if ($driverUuid) {
                $asignados = $this->vehicleService->getVehicleUuidsForConductor($driverUuid);
                if (! in_array($vehicleUuid, $asignados, true)) {
                    throw new \InvalidArgumentException('El vehículo no está asignado a este conductor.');
                }
            }

            $activa = FuelRequest::withoutGlobalScopes()
                ->where('vehicle_uuid', $vehicleUuid)
                ->whereIn('status', self::ESTADOS_ACTIVOS)
                ->first();

            if ($activa) {
                throw new \InvalidArgumentException(
                    'Ya existe una solicitud activa para este vehículo. Anúlala o espera su vencimiento.'
                );
            }

            $solicitud = FuelRequest::create([
                'company_uuid' => $vehiculo->company_uuid,
                'vehicle_uuid' => $vehiculo->uuid,
                'driver_uuid' => $driverUuid,
                'requested_by_user_uuid' => $usuario?->uuid,
                'code' => $this->codigoUnico(),
                'public_token' => Str::random(48),
                'status' => 'PENDIENTE',
                'requested_at' => now(),
                'notes' => $notes,
            ]);

            Logger::info('FuelRequestService: solicitud creada', [
                'uuid' => $solicitud->uuid,
                'vehicle_uuid' => $vehicleUuid,
                'driver_uuid' => $driverUuid,
            ]);

            return $solicitud->fresh()->load(['vehicle', 'driver', 'company']);
        });
    }

    // ─── Aprobación y rechazo ──────────────────────────────────────────────

    public function aprobar(string $uuid, string $approverUuid, string $approverName): FuelRequest
    {
        return $this->transaction(function () use ($uuid, $approverUuid, $approverName) {
            $solicitud = $this->bloquear($uuid);

            if ($solicitud->status !== 'PENDIENTE') {
                throw new \InvalidArgumentException('Solo se pueden aprobar solicitudes pendientes.');
            }

            $solicitud->status = 'APROBADA';
            $solicitud->approved_by_user_uuid = $approverUuid;
            $solicitud->approved_by_name = $approverName;
            $solicitud->approved_at = now();
            $solicitud->expires_at = now()->addHours(self::VIGENCIA_HORAS);
            $solicitud->snapshot = $this->snapshot($solicitud, $approverName);
            $solicitud->save();

            return $solicitud->fresh()->load(['vehicle', 'driver', 'approvedBy']);
        });
    }

    public function rechazar(
        string $uuid,
        string $approverUuid,
        string $approverName,
        ?string $motivo = null
    ): FuelRequest {
        return $this->transaction(function () use ($uuid, $approverUuid, $approverName, $motivo) {
            $solicitud = $this->bloquear($uuid);

            if ($solicitud->status !== 'PENDIENTE') {
                throw new \InvalidArgumentException('Solo se pueden rechazar solicitudes pendientes.');
            }

            $solicitud->status = 'RECHAZADA';
            $solicitud->rejected_by_user_uuid = $approverUuid;
            $solicitud->rejected_at = now();
            $solicitud->rejection_reason = $motivo;
            $solicitud->save();

            return $solicitud->fresh()->load(['vehicle', 'driver']);
        });
    }

    public function anular(string $uuid, ?string $driverUuid = null, bool $esAdmin = false): FuelRequest
    {
        return $this->transaction(function () use ($uuid, $driverUuid, $esAdmin) {
            $solicitud = $this->bloquear($uuid);

            if (! in_array($solicitud->status, self::ESTADOS_ACTIVOS, true)) {
                throw new \InvalidArgumentException('La solicitud ya no se puede anular.');
            }

            if (! $esAdmin && $driverUuid && $solicitud->driver_uuid !== $driverUuid) {
                throw new \InvalidArgumentException('No puedes anular una solicitud de otro conductor.');
            }

            $solicitud->status = 'ANULADA';
            $solicitud->save();

            return $solicitud->fresh();
        });
    }

    // ─── Uso y vencimiento ─────────────────────────────────────────────────

    /**
     * Marca como USADA la solicitud aprobada y vigente del vehículo cuando se
     * registra un tanqueo. Devuelve la solicitud enlazada o null.
     */
    public function marcarUsada(string $vehicleUuid, string $fuelLogUuid): ?FuelRequest
    {
        $solicitud = FuelRequest::withoutGlobalScopes()
            ->where('vehicle_uuid', $vehicleUuid)
            ->where('status', 'APROBADA')
            ->where('expires_at', '>=', now())
            ->orderByDesc('approved_at')
            ->first();

        if (! $solicitud) {
            return null;
        }

        $solicitud->status = 'USADA';
        $solicitud->used_at = now();
        $solicitud->fuel_log_uuid = $fuelLogUuid;
        $solicitud->save();

        return $solicitud;
    }

    public function expirarVencidas(?string $companyUuid = null): int
    {
        // Sin empresa explícita se usa la del contexto: el traspaso
        // APROBADA→VENCIDA es una escritura y no debe tocar empresas ajenas
        // (antes, listar o mostrar sin empresa vencía TODAS las empresas).
        // Sin empresa resolvable no opera (devuelve 0).
        $companyUuid ??= request()->attributes->get('current_company_uuid');

        if (! $companyUuid) {
            return 0;
        }

        return FuelRequest::withoutGlobalScopes()
            ->where('company_uuid', $companyUuid)
            ->where('status', 'APROBADA')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'VENCIDA', 'updated_at' => now()]);
    }

    // ─── Consultas ─────────────────────────────────────────────────────────

    public function listar(
        int $perPage = 15,
        int $page = 1,
        ?string $status = null,
        string $search = '',
        ?string $companyUuid = null,
        ?string $driverUuid = null
    ): LengthAwarePaginator {
        $this->expirarVencidas($companyUuid);

        $query = $this->query();

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }
        if ($driverUuid) {
            $query->where('driver_uuid', $driverUuid);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', fn ($v) => $v
                        ->where('vehicle_license_plate', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($d) => $d
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        return $query->orderByDesc('requested_at')->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function porUuid(string $uuid): ?FuelRequest
    {
        $this->expirarVencidas();

        return $this->query()->where('uuid', $uuid)->first();
    }

    public function porToken(string $token): ?FuelRequest
    {
        $solicitud = FuelRequest::withoutGlobalScopes()
            ->with(['vehicle', 'driver', 'company'])
            ->where('public_token', $token)
            ->first();

        if ($solicitud && $solicitud->status === 'APROBADA'
            && $solicitud->expires_at && $solicitud->expires_at->isPast()) {
            $solicitud->status = 'VENCIDA';
            $solicitud->save();
        }

        return $solicitud;
    }

    // ─── QR y datos públicos ───────────────────────────────────────────────

    /**
     * @return array{data: string, url: string, qr_data: string, qr_url: string}
     */
    public function qr(FuelRequest $solicitud): array
    {
        if (! in_array($solicitud->status, ['APROBADA', 'USADA', 'VENCIDA'], true)) {
            throw new \InvalidArgumentException('El QR solo está disponible para solicitudes aprobadas.');
        }

        $url = $this->urlValidacion($solicitud);
        $texto = $this->textoQr($solicitud, $url);

        return [
            'data' => $texto,
            'url' => $url,
            'qr_data' => $this->svgDataUri($texto),
            'qr_url' => $this->svgDataUri($url),
        ];
    }

    public function textoQr(FuelRequest $solicitud, ?string $url = null): string
    {
        $datos = $solicitud->snapshot ?: $this->snapshot($solicitud, $solicitud->approved_by_name);
        $url ??= $this->urlValidacion($solicitud);

        return implode("\n", [
            'SOLICITUD DE TANQUEO',
            'Empresa: '.($datos['empresa']['nombre'] ?? '—'),
            'NIT: '.($datos['empresa']['nit'] ?? '—'),
            'Placa: '.($datos['vehiculo']['placa'] ?? '—'),
            'Marca: '.($datos['vehiculo']['marca'] ?? '—'),
            'Modelo: '.($datos['vehiculo']['modelo'] ?? '—'),
            'Clase: '.($datos['vehiculo']['clase'] ?? '—'),
            'Solicita: '.($datos['conductor'] ?? '—'),
            'Aprueba: '.($datos['aprobador'] ?? '—'),
            'Código: '.($datos['codigo'] ?? $solicitud->code),
            'Vigencia: '.($datos['vigencia'] ?? '—'),
            'Estado: '.$solicitud->status,
            'Validar: '.$url,
        ]);
    }

    public function urlValidacion(FuelRequest $solicitud): string
    {
        return rtrim((string) config('app.url'), '/')
            .'/api/v1/public/fuel-requests/'.$solicitud->public_token;
    }

    /**
     * Snapshot de los datos que se muestran en el QR y en la página pública.
     *
     * @return array<string, mixed>
     */
    public function snapshot(FuelRequest $solicitud, ?string $approverName = null): array
    {
        $solicitud->loadMissing([
            'company',
            'vehicle.brand',
            'vehicle.vehicleClass',
            'driver',
            'requestedBy',
        ]);

        $empresa = $solicitud->company;
        $vehiculo = $solicitud->vehicle;
        $nit = trim(($empresa?->document_number ?? '').'-'.($empresa?->verification_digit ?? ''), '-');

        return [
            'empresa' => [
                'nombre' => $empresa?->business_name ?? '—',
                'nit' => $nit !== '' ? $nit : '—',
            ],
            'vehiculo' => [
                'placa' => $vehiculo?->vehicle_license_plate ?? '—',
                'marca' => $vehiculo?->brand?->description ?? '—',
                'modelo' => trim(($vehiculo?->line ?? '').' '.($vehiculo?->model ?? '')) ?: '—',
                'clase' => $vehiculo?->vehicleClass?->description ?? '—',
            ],
            'conductor' => $solicitud->driver?->full_name
                ?? $solicitud->requestedBy?->name
                ?? '—',
            'aprobador' => $approverName ?? $solicitud->approved_by_name ?? '—',
            'codigo' => $solicitud->code,
            'aprobado_at' => $solicitud->approved_at?->toDateTimeString(),
            'vigencia' => $solicitud->expires_at?->toDateTimeString(),
        ];
    }

    // ─── Utilidades ────────────────────────────────────────────────────────

    private function bloquear(string $uuid): FuelRequest
    {
        $companyUuid = request()->attributes->get('current_company_uuid');

        // Falla cerrado: sin empresa activa nadie gestiona solicitudes, salvo
        // un SUPERADMIN que opera en cualquier empresa. El `when()` anterior
        // dejaba que un flujo interno sin contexto aprobara una solicitud
        // ajena.
        if (! $companyUuid && ! Auth::user()?->hasRole('SUPERADMIN')) {
            throw new \InvalidArgumentException('Sin empresa activa no se pueden gestionar solicitudes de tanqueo.');
        }

        $solicitud = FuelRequest::withoutGlobalScopes()
            ->where('uuid', $uuid)
            ->when($companyUuid, fn ($q) => $q->where('company_uuid', $companyUuid))
            ->lockForUpdate()
            ->first();

        if (! $solicitud) {
            throw new \InvalidArgumentException('La solicitud de tanqueo no existe.');
        }

        return $solicitud;
    }

    private function codigoUnico(): string
    {
        do {
            $codigo = 'TQ-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (FuelRequest::withoutGlobalScopes()->where('code', $codigo)->exists());

        return $codigo;
    }

    private function svgDataUri(string $texto, int $size = 280): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($texto);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
