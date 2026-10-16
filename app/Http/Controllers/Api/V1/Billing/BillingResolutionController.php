<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreBillingResolutionRequest;
use App\Http\Requests\Billing\UpdateBillingResolutionRequest;
use App\Services\Billing\BillingResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Resoluciones de facturación (rango interno por ahora). */
class BillingResolutionController extends Controller
{
    public function __construct(
        private readonly BillingResolutionService $resoluciones
    ) {}

    #[OA\Get(path: '/api/v1/billing/billing-resolutions', summary: 'Listar resoluciones', operationId: 'listBillingResolutions', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = array_filter(['company_uuid' => $request->query('company_uuid')]);

            $pagina = $this->resoluciones->search($filtros, ['*'], (int) $request->query('per_page', 15), ['type']);

            return $this->successResponse($pagina, 'Resoluciones recuperadas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function list(Request $request): JsonResponse
    {
        try {
            $filtros = array_filter(['company_uuid' => $request->query('company_uuid'), 'is_active' => 1]);

            $datos = $this->resoluciones->search($filtros, [
                'uuid', 'company_uuid', 'invoice_prefix', 'resolution_number',
                'invoice_start_number', 'invoice_end_number', 'last_consecutive',
                'start_date', 'end_date',
            ]);

            return $this->successResponse($datos, 'Catálogo de resoluciones recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/billing/billing-resolutions/{uuid}', summary: 'Detalle de resolución', operationId: 'showBillingResolution', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function show(string $uuid): JsonResponse
    {
        try {
            $registro = $this->resoluciones->findByUuid($uuid, ['*'], ['type']);
            if (! $registro) {
                return $this->errorResponse('La resolución solicitada no existe.', 404);
            }

            return $this->successResponse($registro, 'Detalle recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/billing-resolutions', summary: 'Crear resolución', operationId: 'storeBillingResolution', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function store(StoreBillingResolutionRequest $request): JsonResponse
    {
        try {
            $registro = $this->resoluciones->create($request->validated());

            return $this->successResponse($registro, 'Resolución creada con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/billing/billing-resolutions/{uuid}', summary: 'Actualizar resolución', operationId: 'updateBillingResolution', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function update(UpdateBillingResolutionRequest $request, string $uuid): JsonResponse
    {
        try {
            if (! $this->resoluciones->findByUuid($uuid)) {
                return $this->errorResponse('La resolución que desea actualizar no existe.', 404);
            }

            $this->resoluciones->update($uuid, $request->validated());

            return $this->successResponse(
                $this->resoluciones->findByUuid($uuid, ['*'], ['type']),
                'Resolución actualizada con éxito.'
            );
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(path: '/api/v1/billing/billing-resolutions/{uuid}', summary: 'Eliminar resolución', operationId: 'destroyBillingResolution', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            if (! $this->resoluciones->findByUuid($uuid)) {
                return $this->errorResponse('La resolución que desea eliminar no existe.', 404);
            }

            $this->resoluciones->delete($uuid);

            return $this->successResponse(null, 'Resolución eliminada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
