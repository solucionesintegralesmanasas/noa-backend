<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Models\FuelRequest;
use App\Models\User;
use App\Services\Fleet\FuelRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Solicitudes de tanqueo con aprobación de la empresa.
 */
class FuelRequestController extends Controller
{
    public function __construct(private readonly FuelRequestService $service) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/fuel-requests',
        summary: 'Listado de solicitudes de tanqueo',
        operationId: 'listFuelRequests',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado recuperado.')]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $driverUuid = $this->esConductorPuro($user) ? $this->driverUuid($user) : null;
            $companyUuid = $request->query('company_uuid') ?? $request->attributes->get('current_company_uuid');

            $data = $this->service->listar(
                (int) $request->query('per_page', '15'),
                (int) $request->query('page', '1'),
                $request->query('status') ? (string) $request->query('status') : null,
                (string) $request->query('search', ''),
                $companyUuid ? (string) $companyUuid : null,
                $driverUuid
            );

            return $this->successResponse($data, 'Solicitudes de tanqueo recuperadas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/fuel-requests',
        summary: 'Crear una solicitud de tanqueo',
        operationId: 'storeFuelRequest',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Solicitud creada.')]
    )]
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'vehicle_uuid' => ['required', 'uuid', 'exists:vehicles,uuid'],
                'driver_uuid' => ['nullable', 'uuid', 'exists:third_parties,uuid'],
                'notes' => ['nullable', 'string', 'max:500'],
            ]);

            $user = $request->user();

            // El conductor puro siempre solicita para sí mismo. Los usuarios de
            // empresa (ADMIN_EMPRESA) pueden solicitar sin conductor asociado;
            // en ese caso el snapshot usa el nombre del usuario solicitante.
            if ($this->esConductorPuro($user)) {
                $driverUuid = $this->driverUuid($user);

                if (! $driverUuid) {
                    throw new \InvalidArgumentException(
                        'Tu usuario no está vinculado a un conductor. No puedes solicitar tanqueo.'
                    );
                }
            } else {
                $driverUuid = $data['driver_uuid'] ?? null;
            }

            $solicitud = $this->service->crear(
                $data['vehicle_uuid'],
                $driverUuid,
                $user,
                $request->attributes->get('current_company_uuid'),
                $data['notes'] ?? null
            );

            return $this->successResponse(
                $this->conQr($solicitud),
                'Solicitud de tanqueo creada. Queda pendiente de aprobación.',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/fuel-requests/{uuid}',
        summary: 'Detalle de una solicitud de tanqueo',
        operationId: 'showFuelRequest',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Solicitud encontrada.')]
    )]
    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            $solicitud = $this->service->porUuid($uuid);
            if (! $solicitud) {
                return $this->errorResponse('La solicitud de tanqueo no existe.', 404);
            }

            $this->autorizarLectura($request->user(), $solicitud);

            return $this->successResponse($this->conQr($solicitud), 'Solicitud recuperada con éxito.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/fuel-requests/{uuid}/approve',
        summary: 'Aprobar una solicitud de tanqueo',
        operationId: 'approveFuelRequest',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Solicitud aprobada.')]
    )]
    public function approve(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = $request->user();
            $solicitud = $this->service->aprobar($uuid, (string) $user->uuid, (string) $user->name);

            return $this->successResponse($this->conQr($solicitud), 'Solicitud aprobada con éxito.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/fuel-requests/{uuid}/reject',
        summary: 'Rechazar una solicitud de tanqueo',
        operationId: 'rejectFuelRequest',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Solicitud rechazada.')]
    )]
    public function reject(Request $request, string $uuid): JsonResponse
    {
        try {
            $data = $request->validate([
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $user = $request->user();
            $solicitud = $this->service->rechazar(
                $uuid,
                (string) $user->uuid,
                (string) $user->name,
                $data['reason'] ?? null
            );

            return $this->successResponse($solicitud, 'Solicitud rechazada.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/fuel-requests/{uuid}/cancel',
        summary: 'Anular una solicitud de tanqueo',
        operationId: 'cancelFuelRequest',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Solicitud anulada.')]
    )]
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = $request->user();
            $esAdmin = $user->hasAnyRole(['SUPERADMIN', 'ADMIN_EMPRESA', 'COORD_MANTENIMIENTO']);
            $driverUuid = $this->esConductorPuro($user) ? $this->driverUuid($user) : null;

            $solicitud = $this->service->anular($uuid, $driverUuid, $esAdmin);

            return $this->successResponse($solicitud, 'Solicitud anulada con éxito.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/fuel-requests/{uuid}/qr',
        summary: 'QR de datos y de validación de una solicitud aprobada',
        operationId: 'qrFuelRequest',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'QR generados.')]
    )]
    public function qr(Request $request, string $uuid): JsonResponse
    {
        try {
            $solicitud = $this->service->porUuid($uuid);
            if (! $solicitud) {
                return $this->errorResponse('La solicitud de tanqueo no existe.', 404);
            }

            $this->autorizarLectura($request->user(), $solicitud);

            return $this->successResponse($this->service->qr($solicitud), 'QR generados con éxito.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ─── Público ───────────────────────────────────────────────────────────

    public function publicShow(string $token)
    {
        $solicitud = $this->service->porToken($token);
        if (! $solicitud) {
            abort(404, 'Solicitud de tanqueo no encontrada.');
        }

        $datos = $solicitud->snapshot ?: $this->service->snapshot($solicitud, $solicitud->approved_by_name);
        $valida = $solicitud->status === 'APROBADA'
            && $solicitud->expires_at
            && $solicitud->expires_at->isFuture();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'codigo' => $solicitud->code,
                    'estado' => $solicitud->status,
                    'valida' => $valida,
                    'empresa' => $datos['empresa'] ?? [],
                    'vehiculo' => $datos['vehiculo'] ?? [],
                    'conductor' => $datos['conductor'] ?? null,
                    'aprobador' => $datos['aprobador'] ?? null,
                    'aprobado_at' => $datos['aprobado_at'] ?? null,
                    'vigencia' => $datos['vigencia'] ?? null,
                ],
            ]);
        }

        return response()->view('fuel-requests.public', [
            'solicitud' => $solicitud,
            'datos' => $datos,
            'valida' => $valida,
        ]);
    }

    // ─── Utilidades ────────────────────────────────────────────────────────

    private function conQr(FuelRequest $solicitud): array
    {
        $data = $solicitud->toArray();
        $data['qr'] = null;

        if (in_array($solicitud->status, ['APROBADA', 'USADA', 'VENCIDA'], true)) {
            try {
                $data['qr'] = $this->service->qr($solicitud);
            } catch (\Throwable $e) {
                // El detalle sigue siendo útil aunque el QR no se pueda generar.
            }
        }

        return $data;
    }

    private function autorizarLectura(User $user, FuelRequest $solicitud): void
    {
        if ($this->esConductorPuro($user)
            && $solicitud->driver_uuid !== $this->driverUuid($user)) {
            throw new \InvalidArgumentException('No puedes ver solicitudes de otro conductor.');
        }
    }

    private function esConductorPuro(?User $user): bool
    {
        return $user
            && $user->hasRole('CONDUCTOR')
            && ! $user->hasAnyRole(['SUPERADMIN', 'ADMIN_EMPRESA', 'COORD_MANTENIMIENTO']);
    }

    private function driverUuid(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $contexto = request()->attributes->get('current_third_party_uuid');
        if ($contexto) {
            return (string) $contexto;
        }

        $companyUuid = request()->attributes->get('current_company_uuid');
        $company = $companyUuid
            ? $user->companies()->where('uuid', $companyUuid)->first()
            : $user->companies()->first();

        return $company?->pivot?->third_party_uuid
            ?? ($user->third_party_uuid ?? null);
    }
}
