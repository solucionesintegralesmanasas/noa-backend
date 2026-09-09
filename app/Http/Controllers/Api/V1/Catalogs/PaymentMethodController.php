<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\PaymentMethod\StorePaymentMethodRequest;
use App\Http\Requests\Catalogs\PaymentMethod\UpdatePaymentMethodRequest;
use App\Services\Catalogs\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de MedioPago.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentMethodService $paymentMethodService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/payment-methods',
        summary: 'Consultar listado paginado de MedioPago',
        operationId: 'listPaymentMethods',
        tags: ['MedioPago'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->paymentMethodService->getAllPaymentMethodsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de MedioPago recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/payment-methods/list',
        summary: 'Obtener catálogo de MedioPago',
        operationId: 'getAllPaymentMethods',
        tags: ['MedioPago'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->paymentMethodService->getAllPaymentMethods();

            return $this->successResponse($data, 'Catálogo de MedioPago recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/payment-methods/{uuid}',
        summary: 'Obtener detalle de MedioPago por UUID',
        operationId: 'showPaymentMethod',
        tags: ['MedioPago'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Registro encontrado.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->paymentMethodService->getPaymentMethodByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de MedioPago solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de MedioPago recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/payment-methods',
        summary: 'Crear nuevo registro de MedioPago',
        operationId: 'storePaymentMethod',
        tags: ['MedioPago'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        try {
            $record = $this->paymentMethodService->createPaymentMethod($request->validated());

            return $this->successResponse($record, 'Registro de MedioPago creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/payment-methods/{uuid}',
        summary: 'Actualizar registro de MedioPago por UUID',
        operationId: 'updatePaymentMethod',
        tags: ['MedioPago'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdatePaymentMethodRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->paymentMethodService->getPaymentMethodByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de MedioPago que desea actualizar no existe.', 404);
            }
            $updated = $this->paymentMethodService->updatePaymentMethod($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de MedioPago actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/payment-methods/{uuid}',
        summary: 'Eliminar registro de MedioPago por UUID',
        operationId: 'destroyPaymentMethod',
        tags: ['MedioPago'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->paymentMethodService->getPaymentMethodByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de MedioPago que desea eliminar no existe.', 404);
            }
            $this->paymentMethodService->deletePaymentMethod($uuid);

            return $this->successResponse(null, 'Registro de MedioPago eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
