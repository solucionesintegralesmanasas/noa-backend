<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\TypeOfDocument\StoreTypeOfDocumentRequest;
use App\Http\Requests\Catalogs\TypeOfDocument\UpdateTypeOfDocumentRequest;
use App\Services\Catalogs\TypeOfDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de TipoDocumento.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class TypeOfDocumentController extends Controller
{
    public function __construct(
        private readonly TypeOfDocumentService $typeOfDocumentService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/type-of-documents',
        summary: 'Consultar listado paginado de TipoDocumento',
        operationId: 'listTypeOfDocuments',
        tags: ['TipoDocumento'],
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
            $data = $this->typeOfDocumentService->getAllTypeOfDocumentsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de TipoDocumento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/type-of-documents/list',
        summary: 'Obtener catálogo de TipoDocumento',
        operationId: 'getAllTypeOfDocuments',
        tags: ['TipoDocumento'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->typeOfDocumentService->getAllTypeOfDocuments();

            return $this->successResponse($data, 'Catálogo de TipoDocumento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/type-of-documents/{uuid}',
        summary: 'Obtener detalle de TipoDocumento por UUID',
        operationId: 'showTypeOfDocument',
        tags: ['TipoDocumento'],
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
            $record = $this->typeOfDocumentService->getTypeOfDocumentByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TipoDocumento solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de TipoDocumento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/type-of-documents',
        summary: 'Crear nuevo registro de TipoDocumento',
        operationId: 'storeTypeOfDocument',
        tags: ['TipoDocumento'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreTypeOfDocumentRequest $request): JsonResponse
    {
        try {
            $record = $this->typeOfDocumentService->createTypeOfDocument($request->validated());

            return $this->successResponse($record, 'Registro de TipoDocumento creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/type-of-documents/{uuid}',
        summary: 'Actualizar registro de TipoDocumento por UUID',
        operationId: 'updateTypeOfDocument',
        tags: ['TipoDocumento'],
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
    public function update(UpdateTypeOfDocumentRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->typeOfDocumentService->getTypeOfDocumentByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TipoDocumento que desea actualizar no existe.', 404);
            }
            $updated = $this->typeOfDocumentService->updateTypeOfDocument($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de TipoDocumento actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/type-of-documents/{uuid}',
        summary: 'Eliminar registro de TipoDocumento por UUID',
        operationId: 'destroyTypeOfDocument',
        tags: ['TipoDocumento'],
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
            $record = $this->typeOfDocumentService->getTypeOfDocumentByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TipoDocumento que desea eliminar no existe.', 404);
            }
            $this->typeOfDocumentService->deleteTypeOfDocument($uuid);

            return $this->successResponse(null, 'Registro de TipoDocumento eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
