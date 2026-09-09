<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\ControlSheet\StoreControlSheetRequest;
use App\Http\Requests\Fleet\ControlSheet\UpdateControlSheetRequest;
use App\Services\Fleet\ControlSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para gestión de Hojas de Control (ControlSheet).
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.0
 *
 * @since    V 1.0.0
 *
 * @created  2026-06-28
 */
class ControlSheetController extends Controller
{
    public function __construct(
        private readonly ControlSheetService $controlSheetService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/control-sheets',
        summary: 'Consultar listado paginado de Hojas de Control',
        operationId: 'listControlSheets',
        tags: ['ControlSheet'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->controlSheetService->getAllControlSheetsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/control-sheets/list',
        summary: 'Obtener catálogo de Hojas de Control',
        operationId: 'getAllControlSheets',
        tags: ['ControlSheet'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->controlSheetService->getAllControlSheets();

            return $this->successResponse($data, 'Catálogo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/control-sheets/{uuid}',
        summary: 'Obtener detalle de Hoja de Control por UUID',
        operationId: 'showControlSheet',
        tags: ['ControlSheet'],
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
            $record = $this->controlSheetService->getControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/control-sheets',
        summary: 'Crear nueva Hoja de Control',
        operationId: 'storeControlSheet',
        tags: ['ControlSheet'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreControlSheetRequest $request): JsonResponse
    {
        try {
            $record = $this->controlSheetService->createControlSheet($request->validated());

            return $this->successResponse($record, 'Registro creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/control-sheets/{uuid}',
        summary: 'Actualizar Hoja de Control por UUID',
        operationId: 'updateControlSheet',
        tags: ['ControlSheet'],
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
    public function update(UpdateControlSheetRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->controlSheetService->getControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que desea actualizar no existe.', 404);
            }
            $updated = $this->controlSheetService->updateControlSheet($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/control-sheets/{uuid}',
        summary: 'Eliminar Hoja de Control por UUID',
        operationId: 'destroyControlSheet',
        tags: ['ControlSheet'],
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
            $record = $this->controlSheetService->getControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que desea eliminar no existe.', 404);
            }
            $this->controlSheetService->deleteControlSheet($uuid);

            return $this->successResponse(null, 'Registro eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/control-sheets/{uuid}/upload-pdf',
        summary: 'Subir un archivo PDF a la Hoja de Control',
        operationId: 'uploadPdfControlSheet',
        tags: ['ControlSheet'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'PDF subido con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function uploadPdf(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        try {
            $record = $this->controlSheetService->getControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro no existe.', 404);
            }

            $file = $request->file('file');
            $url = $this->controlSheetService->uploadPdf($uuid, $file);

            return $this->successResponse(['url' => $url], 'PDF subido con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/control-sheets/{uuid}/upload-pdfs',
        summary: 'Subir múltiples archivos PDF a la Hoja de Control',
        operationId: 'uploadMultiplePdfsControlSheet',
        tags: ['ControlSheet'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'files', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'PDFs subidos con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function uploadMultiplePdfs(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        try {
            $record = $this->controlSheetService->getControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro no existe.', 404);
            }

            $files = $request->file('files');
            $result = $this->controlSheetService->uploadMultiplePdfs($uuid, $files);

            return $this->successResponse($result, 'PDFs subidos con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/control-sheets/{uuid}/pdfs',
        summary: 'Obtener listado de PDFs asociados a la Hoja de Control',
        operationId: 'getPdfsControlSheet',
        tags: ['ControlSheet'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDFs listados con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function getPdfs(string $uuid): JsonResponse
    {
        try {
            $record = $this->controlSheetService->getControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro no existe.', 404);
            }

            $pdfs = $this->controlSheetService->getPdfs($uuid);

            return $this->successResponse($pdfs, 'PDFs recuperados con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/control-sheets/{uuid}/pdfs/{mediaUuid}',
        summary: 'Eliminar un PDF específico de la Hoja de Control',
        operationId: 'deletePdfControlSheet',
        tags: ['ControlSheet'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'mediaUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF eliminado con éxito.'),
            new OA\Response(response: 404, description: 'Registro o PDF no encontrado.'),
        ]
    )]
    public function deletePdf(string $uuid, string $mediaUuid): JsonResponse
    {
        try {
            $record = $this->controlSheetService->getControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro no existe.', 404);
            }

            $deleted = $this->controlSheetService->deletePdf($uuid, $mediaUuid);
            if (! $deleted) {
                return $this->errorResponse('El archivo PDF no fue encontrado.', 404);
            }

            return $this->successResponse(null, 'PDF eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
