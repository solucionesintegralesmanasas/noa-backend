<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\FinancialStatement\StoreFinancialStatementRequest;
use App\Http\Requests\Administrations\FinancialStatement\UpdateFinancialStatementRequest;
use App\Services\Administrations\FinancialStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Estados Financieros.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class FinancialStatementController extends Controller
{
    public function __construct(private readonly FinancialStatementService $financialStatementService) {}

    #[OA\Get(
        path: '/api/v1/administration/financial-statements',
        summary: 'Listar estados financieros',
        operationId: 'listFinancialStatements',
        tags: ['EstadoFinanciero'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Éxito.')]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->financialStatementService->getAllFinancialStatementsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Historial financiero recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/financial-statements/list',
        summary: 'Obtener catálogo de EstadoFinanciero',
        operationId: 'listEstadoFinanciero',
        tags: ['EstadoFinanciero'],
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
            $data = $this->financialStatementService->getAllFinancialStatements();

            return $this->successResponse($data, 'Catálogo completo de estado financiero obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/financial-statements',
        summary: 'Registrar nuevo estado financiero',
        operationId: 'storeFinancialStatement',
        tags: ['EstadoFinanciero'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Registrado.')]
    )]
    public function store(StoreFinancialStatementRequest $request): JsonResponse
    {
        try {
            $record = $this->financialStatementService->createFinancialStatement($request->validated());

            return $this->successResponse($record, 'Estados financieros registrados correctamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/financial-statements/{uuid}',
        summary: 'Consultar detalle de un estado financiero',
        operationId: 'showFinancialStatement',
        tags: ['EstadoFinanciero'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Encontrado.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->financialStatementService->getFinancialStatementByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Reporte financiero no encontrado.', 404);
            }

            return $this->successResponse($record, 'Detalle financiero obtenido con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/financial-statements/{uuid}',
        summary: 'Actualizar reporte financiero',
        operationId: 'updateFinancialStatement',
        tags: ['EstadoFinanciero'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Actualizado.')]
    )]
    public function update(UpdateFinancialStatementRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->financialStatementService->getFinancialStatementByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('No se puede actualizar un reporte inexistente.', 404);
            }
            $updated = $this->financialStatementService->updateFinancialStatement($uuid, $request->validated());

            return $this->successResponse($updated, 'Cifras financieras actualizadas correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/financial-statements/{uuid}',
        summary: 'Eliminar reporte financiero',
        operationId: 'destroyFinancialStatement',
        tags: ['EstadoFinanciero'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Eliminado exitosamente.')]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->financialStatementService->getFinancialStatementByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El reporte solicitado ya no existe.', 404);
            }
            $this->financialStatementService->deleteFinancialStatement($uuid);

            return $this->successResponse(null, 'Estado financiero removido del sistema.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
