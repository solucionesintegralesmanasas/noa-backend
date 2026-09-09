<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\RupRecord\StoreRupRecordRequest;
use App\Http\Requests\Administrations\RupRecord\UpdateRupRecordRequest;
use App\Services\Administrations\RupRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión del RUP.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class RupRecordController extends Controller
{
    public function __construct(private readonly RupRecordService $rupRecordService) {}

    #[OA\Get(
        path: '/api/v1/administration/rup',
        summary: 'Listar registros RUP',
        operationId: 'listRupRecords',
        tags: ['RegistroRUP'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado obtenido.')]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->rupRecordService->getAllRupRecordsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado de registros RUP recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/rup/list',
        summary: 'Obtener catálogo de RegistroRUP',
        operationId: 'listRegistroRUP',
        tags: ['RegistroRUP'],
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
            $data = $this->rupRecordService->getAllRupRecords();

            return $this->successResponse($data, 'Catálogo completo de registro RUP obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/rup',
        summary: 'Crear nueva inscripción RUP',
        operationId: 'storeRupRecord',
        tags: ['RegistroRUP'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Creado correctamente.')]
    )]
    public function store(StoreRupRecordRequest $request): JsonResponse
    {
        try {
            $record = $this->rupRecordService->createRupRecord($request->validated());

            return $this->successResponse($record, 'Registro RUP creado exitosamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/rup/{uuid}',
        summary: 'Ver detalle de un RUP',
        operationId: 'showRupRecord',
        tags: ['RegistroRUP'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Registro RUP encontrado.'),
            new OA\Response(response: 404, description: 'No se encontró el RUP solicitado.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->rupRecordService->getRupRecordByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Registro RUP no localizado.', 404);
            }

            return $this->successResponse($record, 'Detalles del RUP obtenidos.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/rup/{uuid}',
        summary: 'Actualizar calificaciones RUP',
        operationId: 'updateRupRecord',
        tags: ['RegistroRUP'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Actualizado exitosamente.')]
    )]
    public function update(UpdateRupRecordRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->rupRecordService->getRupRecordByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El RUP que desea modificar no existe.', 404);
            }
            $updated = $this->rupRecordService->updateRupRecord($uuid, $request->validated());

            return $this->successResponse($updated, 'Datos del RUP actualizados satisfactoriamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/rup/{uuid}',
        summary: 'Eliminar RUP',
        operationId: 'destroyRupRecord',
        tags: ['RegistroRUP'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Registro retirado.')]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->rupRecordService->getRupRecordByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro RUP ya no existe.', 404);
            }
            $this->rupRecordService->deleteRupRecord($uuid);

            return $this->successResponse(null, 'Inscripción RUP eliminada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/administration/rup/{uuid}/toggle-status',
        summary: 'Alternar estado activo/inactivo de RegistroRUP',
        operationId: 'toggleStatusRegistroRUP',
        tags: ['RegistroRUP'],
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
            $record = $this->rupRecordService->getRupRecordByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que intenta modificar no existe.', 404);
            }
            $updated = $this->rupRecordService->toggleRupRecordStatus($uuid);
            $activo = $updated->is_active ?? $updated->status ?? false;
            $estado = $activo ? 'activado' : 'desactivado';

            return $this->successResponse($updated, ucfirst('registro RUP').' '.$estado.' correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
