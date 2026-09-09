<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\BankDetail\StoreBankDetailRequest;
use App\Http\Requests\Administrations\BankDetail\UpdateBankDetailRequest;
use App\Services\Administrations\BankDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Información Bancaria.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class BankDetailController extends Controller
{
    public function __construct(private readonly BankDetailService $bankDetailService) {}

    #[OA\Get(
        path: '/api/v1/administration/bank-details',
        summary: 'Consultar listado paginado de Cuentas Bancarias',
        operationId: 'listBankDetails',
        tags: ['DetalleBancario'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Listado obtenido.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->bankDetailService->getAllBankDetailsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado de cuentas bancarias recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/bank-details/list',
        summary: 'Obtener catálogo de DetalleBancario',
        operationId: 'listDetalleBancario',
        tags: ['DetalleBancario'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->bankDetailService->getAllBankDetails();

            return $this->successResponse($data, 'Catálogo completo de datos bancarios obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/bank-details',
        summary: 'Registrar nueva cuenta bancaria',
        operationId: 'storeBankDetail',
        tags: ['DetalleBancario'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Cuenta registrada.'),
        ]
    )]
    public function store(StoreBankDetailRequest $request): JsonResponse
    {
        try {
            $record = $this->bankDetailService->createBankDetail($request->validated());

            return $this->successResponse($record, 'Información bancaria registrada exitosamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/bank-details/{uuid}',
        summary: 'Ver información de una cuenta bancaria',
        operationId: 'showBankDetail',
        tags: ['DetalleBancario'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle de cuenta obtenido.'),
            new OA\Response(response: 404, description: 'Cuenta no encontrada.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->bankDetailService->getBankDetailByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro bancario no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de cuenta recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/bank-details/{uuid}',
        summary: 'Actualizar datos de una cuenta bancaria',
        operationId: 'updateBankDetail',
        tags: ['DetalleBancario'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Datos actualizados.'),
        ]
    )]
    public function update(UpdateBankDetailRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->bankDetailService->getBankDetailByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('No se puede actualizar una cuenta inexistente.', 404);
            }
            $updated = $this->bankDetailService->updateBankDetail($uuid, $request->validated());

            return $this->successResponse($updated, 'Datos bancarios actualizados correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/bank-details/{uuid}',
        summary: 'Eliminar cuenta bancaria',
        operationId: 'destroyBankDetail',
        tags: ['DetalleBancario'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cuenta eliminada.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->bankDetailService->getBankDetailByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La cuenta bancaria ya no existe.', 404);
            }
            $this->bankDetailService->deleteBankDetail($uuid);

            return $this->successResponse(null, 'Registro bancario eliminado satisfactoriamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/administration/bank-details/{uuid}/toggle-status',
        summary: 'Alternar estado activo/inactivo de DetalleBancario',
        operationId: 'toggleStatusDetalleBancario',
        tags: ['DetalleBancario'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->bankDetailService->getBankDetailByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que intenta modificar no existe.', 404);
            }
            $updated = $this->bankDetailService->toggleBankDetailStatus($uuid);
            $activo = $updated->is_active ?? $updated->status ?? false;
            $estado = $activo ? 'activado' : 'desactivado';

            return $this->successResponse($updated, ucfirst('datos bancarios').' '.$estado.' correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
