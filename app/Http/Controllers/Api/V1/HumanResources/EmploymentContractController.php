<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HumanResources;

use App\Http\Controllers\Controller;
use App\Http\Requests\HumanResources\EmploymentContract\StoreEmploymentContractRequest;
use App\Http\Requests\HumanResources\EmploymentContract\UpdateEmploymentContractRequest;
use App\Services\HumanResources\EmploymentContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Contratos Laborales (EmploymentContracts).
 *
 * @author   AI Assistant
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-07-21
 */
class EmploymentContractController extends Controller
{
    /**
     * EmploymentContractController constructor.
     */
    public function __construct(
        private readonly EmploymentContractService $employmentContractService
    ) {}

    #[OA\Get(
        path: '/api/v1/human-resources/employment-contracts',
        summary: 'Consultar listado paginado de Contratos Laborales',
        operationId: 'listEmploymentContracts',
        tags: ['Contratos Laborales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', description: 'Número de página', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', description: 'Registros por página', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', description: 'Término de búsqueda', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 403, description: 'Acceso denegado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage  = (int) $request->query('per_page', '15');
            $page     = (int) $request->query('page', '1');
            $search   = (string) $request->query('search', '');
            $contracts = $this->employmentContractService->getPaginatedData($perPage, $page, $search);
            return response()->json($contracts);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error al listar los contratos: ' . $e->getMessage()], 500);
        }
    }

    #[OA\Post(
        path: '/api/v1/human-resources/employment-contracts',
        summary: 'Crear un nuevo Contrato Laboral',
        operationId: 'storeEmploymentContract',
        tags: ['Contratos Laborales'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Contrato creado exitosamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function store(StoreEmploymentContractRequest $request): JsonResponse
    {
        try {
            $contract = $this->employmentContractService->create($request->validated());
            return response()->json([
                'message' => 'Contrato creado exitosamente',
                'data' => $contract
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error al crear el contrato: ' . $e->getMessage()], 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/human-resources/employment-contracts/{uuid}',
        summary: 'Consultar los detalles de un Contrato Laboral',
        operationId: 'showEmploymentContract',
        tags: ['Contratos Laborales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', description: 'UUID del contrato', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 404, description: 'Recurso no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $contract = $this->employmentContractService->findByUuid($uuid);
            if (!$contract) {
                return response()->json(['message' => 'Contrato no encontrado'], 404);
            }
            return response()->json(['data' => $contract]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error al obtener el contrato: ' . $e->getMessage()], 500);
        }
    }

    #[OA\Put(
        path: '/api/v1/human-resources/employment-contracts/{uuid}',
        summary: 'Actualizar un Contrato Laboral existente',
        operationId: 'updateEmploymentContract',
        tags: ['Contratos Laborales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', description: 'UUID del contrato', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contrato actualizado exitosamente.'),
            new OA\Response(response: 404, description: 'Recurso no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function update(UpdateEmploymentContractRequest $request, string $uuid): JsonResponse
    {
        try {
            $contract = $this->employmentContractService->updateContract($uuid, $request->validated());
            return response()->json([
                'message' => 'Contrato actualizado exitosamente',
                'data' => $contract
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error al actualizar el contrato: ' . $e->getMessage()], 500);
        }
    }

    #[OA\Delete(
        path: '/api/v1/human-resources/employment-contracts/{uuid}',
        summary: 'Eliminar un Contrato Laboral',
        operationId: 'deleteEmploymentContract',
        tags: ['Contratos Laborales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', description: 'UUID del contrato', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contrato eliminado exitosamente.'),
            new OA\Response(response: 404, description: 'Recurso no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.')
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $this->employmentContractService->delete($uuid);
            return response()->json(['message' => 'Contrato eliminado exitosamente']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error al eliminar el contrato: ' . $e->getMessage()], 500);
        }
    }
}
