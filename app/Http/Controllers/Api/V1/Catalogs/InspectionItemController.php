<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Exports\InspectionItemTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\InspectionItem\BulkStoreInspectionItemRequest;
use App\Http\Requests\Catalogs\InspectionItem\ImportInspectionItemRequest;
use App\Http\Requests\Catalogs\InspectionItem\StoreInspectionCategoryRequest;
use App\Http\Requests\Catalogs\InspectionItem\StoreInspectionItemRequest;
use App\Http\Requests\Catalogs\InspectionItem\UpdateInspectionItemRequest;
use App\Services\Catalogs\InspectionItemService;
use App\Traits\ExcelExportable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controlador API para la gestión integral de Ítems y Categorías de Inspección.
 *
 * @author   Darwin Montes
 *
 * @version  1.1.0
 *
 * @since    1.0.0
 */
class InspectionItemController extends Controller
{
    use ExcelExportable;

    public function __construct(
        private readonly InspectionItemService $inspectionItemService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/inspection-items',
        summary: 'Consultar listado paginado de ItemInspeccion',
        operationId: 'listInspectionItems',
        tags: ['ItemInspeccion'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
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
            $sortBy = $request->query('sort_by');
            $sortOrder = $request->query('sort_order', 'asc');
            $category = $request->query('category');
            $data = $this->inspectionItemService->getAllInspectionItemsWithPagination(
                $perPage,
                $page,
                $search,
                $companyUuid,
                is_string($sortBy) ? $sortBy : null,
                is_string($sortOrder) ? $sortOrder : 'asc',
                is_string($category) ? $category : null
            );

            return $this->successResponse($data, 'Listado paginado de ItemInspeccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/inspection-items/list',
        summary: 'Obtener catálogo de ItemInspeccion',
        operationId: 'getAllInspectionItems',
        tags: ['ItemInspeccion'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->inspectionItemService->getAllInspectionItems();

            return $this->successResponse($data, 'Catálogo de ItemInspeccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/inspection-items/categories',
        summary: 'Listar categorías de ítems de inspección',
        operationId: 'listInspectionCategories',
        tags: ['ItemInspeccion'],
        responses: [
            new OA\Response(response: 200, description: 'Categorías recuperadas con éxito.'),
        ]
    )]
    public function categories(): JsonResponse
    {
        try {
            $data = $this->inspectionItemService->getCategories();

            return $this->successResponse($data, 'Categorías de inspección recuperadas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/inspection-items/categories',
        summary: 'Crear una nueva categoría de ítems de inspección',
        operationId: 'storeInspectionCategory',
        tags: ['ItemInspeccion'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Categoría creada con éxito.'),
        ]
    )]
    public function storeCategory(StoreInspectionCategoryRequest $request): JsonResponse
    {
        try {
            $record = $this->inspectionItemService->createCategory($request->validated());

            return $this->successResponse($record, 'Categoría de inspección creada con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/inspection-items/categories/{uuid}',
        summary: 'Actualizar categoría de ítems de inspección',
        operationId: 'updateInspectionCategory',
        tags: ['ItemInspeccion'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 200, description: 'Categoría actualizada con éxito.'),
        ]
    )]
    public function updateCategory(StoreInspectionCategoryRequest $request, string $uuid): JsonResponse
    {
        try {
            $updated = $this->inspectionItemService->updateCategory($uuid, $request->validated());

            return $this->successResponse($updated, 'Categoría de inspección actualizada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/inspection-items/categories/{uuid}',
        summary: 'Eliminar categoría de ítems de inspección',
        operationId: 'destroyInspectionCategory',
        tags: ['ItemInspeccion'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Categoría eliminada con éxito.'),
        ]
    )]
    public function destroyCategory(string $uuid): JsonResponse
    {
        try {
            $this->inspectionItemService->deleteCategory($uuid);

            return $this->successResponse(null, 'Categoría de inspección eliminada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/inspection-items/template',
        summary: 'Descargar plantilla Excel de ejemplo para carga masiva de ítems de inspección',
        operationId: 'downloadInspectionItemsTemplate',
        tags: ['ItemInspeccion'],
        responses: [
            new OA\Response(response: 200, description: 'Plantilla descargada con éxito.'),
        ]
    )]
    public function downloadTemplate(): BinaryFileResponse|JsonResponse
    {
        try {
            return $this->exportToExcel(InspectionItemTemplateExport::class, 'Plantilla_Items_Inspeccion.xlsx');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/inspection-items/bulk',
        summary: 'Crear múltiples ítems de inspección en una categoría',
        operationId: 'bulkStoreInspectionItems',
        tags: ['ItemInspeccion'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Ítems creados con éxito.'),
        ]
    )]
    public function bulkStore(BulkStoreInspectionItemRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->inspectionItemService->bulkCreateInspectionItems(
                (string) $validated['category'],
                (array) $validated['items']
            );

            $mensaje = $result['created_count'] > 0
                ? "Se registraron {$result['created_count']} ítem(s) correctamente."
                : 'No se registraron ítems nuevos porque ya existían en esa categoría.';

            return $this->successResponse($result, $mensaje, 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/inspection-items/import',
        summary: 'Importar ítems de inspección masivamente desde Excel',
        operationId: 'importInspectionItemsExcel',
        tags: ['ItemInspeccion'],
        requestBody: new OA\RequestBody(required: true),
        responses: [
            new OA\Response(response: 200, description: 'Importación procesada con éxito.'),
        ]
    )]
    public function importExcel(ImportInspectionItemRequest $request): JsonResponse
    {
        try {
            $result = $this->inspectionItemService->importFromExcel($request->file('file'));

            return $this->successResponse($result, 'Importación de ítems de inspección procesada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/inspection-items/{uuid}',
        summary: 'Obtener detalle de ItemInspeccion por UUID',
        operationId: 'showInspectionItem',
        tags: ['ItemInspeccion'],
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
            $record = $this->inspectionItemService->getInspectionItemByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ItemInspeccion solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de ItemInspeccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/inspection-items',
        summary: 'Crear nuevo registro de ItemInspeccion',
        operationId: 'storeInspectionItem',
        tags: ['ItemInspeccion'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreInspectionItemRequest $request): JsonResponse
    {
        try {
            $record = $this->inspectionItemService->createInspectionItem($request->validated());

            return $this->successResponse($record, 'Registro de ItemInspeccion creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/inspection-items/{uuid}',
        summary: 'Actualizar registro de ItemInspeccion por UUID',
        operationId: 'updateInspectionItem',
        tags: ['ItemInspeccion'],
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
    public function update(UpdateInspectionItemRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->inspectionItemService->getInspectionItemByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ItemInspeccion que desea actualizar no existe.', 404);
            }
            $updated = $this->inspectionItemService->updateInspectionItem($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de ItemInspeccion actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/inspection-items/{uuid}',
        summary: 'Eliminar registro de ItemInspeccion por UUID',
        operationId: 'destroyInspectionItem',
        tags: ['ItemInspeccion'],
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
            $record = $this->inspectionItemService->getInspectionItemByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ItemInspeccion que desea eliminar no existe.', 404);
            }
            $this->inspectionItemService->deleteInspectionItem($uuid);

            return $this->successResponse(null, 'Registro de ItemInspeccion eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
