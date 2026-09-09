<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\OccupationalSafetyRecord\StoreOccupationalSafetyRecordRequest;
use App\Http\Requests\Administrations\OccupationalSafetyRecord\UpdateOccupationalSafetyRecordRequest;
use App\Services\Administrations\OccupationalSafetyRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Registros SST.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class OccupationalSafetyRecordController extends Controller
{
    public function __construct(private readonly OccupationalSafetyRecordService $occupationalSafetyRecordService) {}

    #[OA\Get(
        path: '/api/v1/administration/occupational-safety-records',
        summary: 'Consultar listado paginado de SST',
        operationId: 'listOccupationalSafetyRecords',
        tags: ['SeguridadSaludTrabajo'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Operación realizada con éxito.')]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->occupationalSafetyRecordService->getAllOccupationalSafetyRecordsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado de indicadores SST recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/occupational-safety-records/list',
        summary: 'Obtener catálogo de SeguridadSaludTrabajo',
        operationId: 'listSeguridadSaludTrabajo',
        tags: ['SeguridadSaludTrabajo'],
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
            $data = $this->occupationalSafetyRecordService->getAllOccupationalSafetyRecords();

            return $this->successResponse($data, 'Catálogo completo de registro de seguridad obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/occupational-safety-records',
        summary: 'Crear nuevo registro SST',
        operationId: 'storeOccupationalSafetyRecord',
        tags: ['SeguridadSaludTrabajo'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Creado correctamente.')]
    )]
    public function store(StoreOccupationalSafetyRecordRequest $request): JsonResponse
    {
        try {
            $record = $this->occupationalSafetyRecordService->createOccupationalSafetyRecord($request->validated());

            return $this->successResponse($record, 'Registro SST creado exitosamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/occupational-safety-records/{uuid}',
        summary: 'Obtener detalle de SST por UUID',
        operationId: 'showOccupationalSafetyRecord',
        tags: ['SeguridadSaludTrabajo'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Registro encontrado.'),
            new OA\Response(response: 404, description: 'No se encontró el registro SST solicitado.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->occupationalSafetyRecordService->getOccupationalSafetyRecordByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Registro SST no localizado.', 404);
            }

            return $this->successResponse($record, 'Indicadores SST obtenidos correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/occupational-safety-records/{uuid}',
        summary: 'Actualizar indicadores SST',
        operationId: 'updateOccupationalSafetyRecord',
        tags: ['SeguridadSaludTrabajo'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Actualizado exitosamente.')]
    )]
    public function update(UpdateOccupationalSafetyRecordRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->occupationalSafetyRecordService->getOccupationalSafetyRecordByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro SST que desea modificar no existe.', 404);
            }
            $updated = $this->occupationalSafetyRecordService->updateOccupationalSafetyRecord($uuid, $request->validated());

            return $this->successResponse($updated, 'Indicadores SST actualizados correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/occupational-safety-records/{uuid}',
        summary: 'Eliminar registro SST',
        operationId: 'destroyOccupationalSafetyRecord',
        tags: ['SeguridadSaludTrabajo'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Registro retirado.')]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->occupationalSafetyRecordService->getOccupationalSafetyRecordByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro SST ya no existe.', 404);
            }
            $this->occupationalSafetyRecordService->deleteOccupationalSafetyRecord($uuid);

            return $this->successResponse(null, 'Registro SST eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
