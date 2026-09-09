<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSystemConfigurationRequest;
use App\Http\Requests\Settings\UpdateSystemConfigurationRequest;
use App\Services\Settings\SystemConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created_at 2026-06-13
 *
 * @module Settings
 *
 * @resource SystemConfiguration
 */
class SystemConfigurationController extends Controller
{
    public function __construct(
        private readonly SystemConfigurationService $systemConfigurationService
    ) {}

    #[OA\Get(
        path: '/api/v1/settings/system-configurations',
        summary: 'Listar configuraciones generales del sistema',
        operationId: 'listSystemConfigurations',
        tags: ['Configuración de Sistema'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
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
            $data = $this->systemConfigurationService->getPaginatedData($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Configuraciones del sistema obtenidas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/settings/system-configurations',
        summary: 'Crear nueva configuración de sistema por empresa',
        operationId: 'storeSystemConfiguration',
        tags: ['Configuración de Sistema'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Configuración creada correctamente.'),
            new OA\Response(response: 400, description: 'Solicitud incorrecta.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 422, description: 'Datos de validación inválidos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function store(StoreSystemConfigurationRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['uuid'] = Str::uuid()->toString();

            $record = $this->systemConfigurationService->createSystemConfiguration($data);

            return $this->successResponse($record, 'Configuración del sistema creada correctamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/settings/system-configurations/{uuid}',
        summary: 'Obtener configuración de sistema específica por UUID',
        operationId: 'showSystemConfiguration',
        tags: ['Configuración de Sistema'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configuración del sistema obtenida.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Configuración no encontrada.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->systemConfigurationService->findByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La configuración solicitada no existe.', 404);
            }

            return $this->successResponse($record->toArray(), 'Configuración del sistema recuperada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/settings/system-configurations/company/{companyUuid}',
        summary: 'Obtener configuración de sistema por UUID de Empresa',
        operationId: 'showSystemConfigurationByCompany',
        tags: ['Configuración de Sistema'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'companyUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configuración del sistema de la empresa obtenida.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Configuración no encontrada para la empresa.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function showByCompany(string $companyUuid): JsonResponse
    {
        try {
            $record = $this->systemConfigurationService->getByCompanyUuid($companyUuid);
            if (! $record) {
                return $this->successResponse(null, 'No se encontró configuración del sistema para la empresa especificada.');
            }

            return $this->successResponse($record->toArray(), 'Configuración del sistema de la empresa recuperada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/settings/system-configurations/{uuid}',
        summary: 'Actualizar configuración de sistema por UUID',
        operationId: 'updateSystemConfiguration',
        tags: ['Configuración de Sistema'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configuración del sistema actualizada.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Configuración no encontrada.'),
            new OA\Response(response: 422, description: 'Datos de validación inválidos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function update(UpdateSystemConfigurationRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->systemConfigurationService->findByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('No se puede actualizar una configuración inexistente.', 404);
            }

            $this->systemConfigurationService->updateSystemConfiguration($uuid, $request->validated());
            $updatedRecord = $record->fresh();

            return $this->successResponse($updatedRecord, 'Configuración del sistema actualizada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/settings/system-configurations/{uuid}',
        summary: 'Eliminar configuración de sistema por UUID',
        operationId: 'destroySystemConfiguration',
        tags: ['Configuración de Sistema'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configuración de sistema eliminada.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Configuración no encontrada.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->systemConfigurationService->findByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Configuración del sistema no encontrada.', 404);
            }

            $this->systemConfigurationService->deleteSystemConfiguration($uuid);

            return $this->successResponse(null, 'Configuración del sistema eliminada satisfactoriamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ============================================================================
    // MÉTODOS DE SUBIDA DE IMÁGENES
    // ============================================================================

    #[OA\Post(
        path: '/api/v1/settings/system-configurations/{uuid}/ministry-logo',
        summary: 'Subir logo del Ministerio de Transporte',
        operationId: 'uploadMinistryLogo',
        tags: ['Configuración de Sistema'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Logo subido correctamente.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Configuración no encontrada.'),
            new OA\Response(response: 422, description: 'Datos de validación inválidos.'),
        ]
    )]
    public function uploadMinistryLogo(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'ministry_logo' => ['required', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            ]);

            $record = $this->systemConfigurationService->findByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Configuración del sistema no encontrada.', 404);
            }

            $file = $request->file('ministry_logo');
            $ministryLogoUrl = $this->systemConfigurationService->uploadMinistryLogo($uuid, $file);

            return $this->successResponse(['ministry_logo_url' => $ministryLogoUrl], 'Logo del Ministerio subido correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/settings/system-configurations/{uuid}/super-logo',
        summary: 'Subir logo de la Superintendencia',
        operationId: 'uploadSuperLogo',
        tags: ['Configuración de Sistema'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Logo subido correctamente.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Configuración no encontrada.'),
            new OA\Response(response: 422, description: 'Datos de validación inválidos.'),
        ]
    )]
    public function uploadSuperLogo(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'super_logo' => ['required', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            ]);

            $record = $this->systemConfigurationService->findByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Configuración del sistema no encontrada.', 404);
            }

            $file = $request->file('super_logo');
            $superLogoUrl = $this->systemConfigurationService->uploadSuperLogo($uuid, $file);

            return $this->successResponse(['super_logo_url' => $superLogoUrl], 'Logo de la Superintendencia subido correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/settings/system-configurations/{uuid}/letterhead',
        summary: 'Subir hoja membretada',
        operationId: 'uploadLetterhead',
        tags: ['Configuración de Sistema'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Hoja membretada subida correctamente.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Configuración no encontrada.'),
            new OA\Response(response: 422, description: 'Datos de validación inválidos.'),
        ]
    )]
    public function uploadLetterhead(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'letterhead' => ['required', 'file', 'mimes:jpg,jpeg,png,svg,webp,pdf', 'max:4096'],
            ]);

            $record = $this->systemConfigurationService->findByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Configuración del sistema no encontrada.', 404);
            }

            $file = $request->file('letterhead');
            $letterheadUrl = $this->systemConfigurationService->uploadLetterhead($uuid, $file);

            return $this->successResponse(['letterhead_url' => $letterheadUrl], 'Hoja membretada subida correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
