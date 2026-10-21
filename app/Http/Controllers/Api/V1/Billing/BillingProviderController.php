<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreBillingProviderRequest;
use App\Services\Billing\BillingProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/** Catálogo de proveedores tecnológicos (solo registro por ahora). */
class BillingProviderController extends Controller
{
    public function __construct(
        private readonly BillingProviderService $proveedores
    ) {}

    #[OA\Get(path: '/api/v1/billing/billing-providers', summary: 'Listar proveedores', operationId: 'listBillingProviders', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function index(Request $request): JsonResponse
    {
        try {
            $pagina = $this->proveedores->getPaginatedData(
                (int) $request->query('per_page', 15),
                (int) $request->query('page', 1),
                (string) $request->query('search', ''),
                $request->query('company_uuid')
            );

            return $this->successResponse($pagina, 'Proveedores recuperados con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/billing/billing-providers/{uuid}', summary: 'Detalle de proveedor', operationId: 'showBillingProvider', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function show(string $uuid): JsonResponse
    {
        try {
            $registro = $this->proveedores->findByUuid($uuid);
            if (! $registro) {
                return $this->errorResponse('El proveedor solicitado no existe.', 404);
            }

            return $this->successResponse($registro, 'Detalle recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/billing-providers', summary: 'Registrar proveedor', operationId: 'storeBillingProvider', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function store(StoreBillingProviderRequest $request): JsonResponse
    {
        try {
            $registro = $this->proveedores->create($request->validated());

            return $this->successResponse($registro, 'Proveedor registrado con éxito.', 201);
        } catch (ValidationException $e) {
            throw $e; // 422 con errores por campo (login fallido del proveedor)
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/billing/billing-providers/{uuid}', summary: 'Actualizar proveedor', operationId: 'updateBillingProvider', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function update(StoreBillingProviderRequest $request, string $uuid): JsonResponse
    {
        try {
            if (! $this->proveedores->findByUuid($uuid)) {
                return $this->errorResponse('El proveedor que desea actualizar no existe.', 404);
            }

            $this->proveedores->update($uuid, $request->validated());

            return $this->successResponse($this->proveedores->findByUuid($uuid), 'Proveedor actualizado con éxito.');
        } catch (ValidationException $e) {
            throw $e; // 422 con errores por campo (login fallido del proveedor)
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(path: '/api/v1/billing/billing-providers/{uuid}', summary: 'Eliminar proveedor', operationId: 'destroyBillingProvider', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            if (! $this->proveedores->findByUuid($uuid)) {
                return $this->errorResponse('El proveedor que desea eliminar no existe.', 404);
            }

            $this->proveedores->delete($uuid);

            return $this->successResponse(null, 'Proveedor eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
