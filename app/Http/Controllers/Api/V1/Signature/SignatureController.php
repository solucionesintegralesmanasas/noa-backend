<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Signature;

use App\Http\Controllers\Controller;
use App\Http\Requests\Signature\StoreSignatureRequest;
use App\Services\Signature\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Clase SignatureController
 *
 * Controlador REST para la gestión de firmas digitales PNG.
 */
class SignatureController extends Controller
{
    public function __construct(
        private readonly SignatureService $signatureService
    ) {}

    #[OA\Post(
        path: '/api/v1/signatures',
        summary: 'Almacenar una firma nueva',
        operationId: 'storeSignature',
        tags: ['Firma'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['entity_type', 'entity_id', 'signature'],
                properties: [
                    new OA\Property(property: 'entity_type', type: 'string', enum: ['vehicle', 'driver', 'contract', 'vehicle_inspection'], description: 'Tipo de entidad asociada.'),
                    new OA\Property(property: 'entity_id', type: 'integer', description: 'ID de la entidad.'),
                    new OA\Property(property: 'signature', type: 'string', description: 'Firma en formato base64 (data:image/png;base64,...)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Firma guardada correctamente.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 422, description: 'Datos de validación inválidos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function store(StoreSignatureRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Mapear rol específico para inspecciones vehiculares
            if (isset($data['entity_type']) && $data['entity_type'] === 'vehicle_inspection') {
                if (!empty($data['role'])) {
                    $data['entity_type'] = $data['role'] === 'inspector'
                        ? 'vehicle_inspection_inspector'
                        : 'vehicle_inspection_coordinator';
                }
            }

            // Resolver company_uuid desde el contexto inyectado por SetCompanyContext
            if (empty($data['company_uuid'])) {
                $data['company_uuid'] = $request->attributes->get('current_company_uuid');
            }

            $signature = $this->signatureService->store($data);

            return $this->createdResponse($signature, 'Firma guardada correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/signatures/latest',
        summary: 'Obtener la firma más reciente de una entidad',
        operationId: 'latestSignature',
        tags: ['Firma'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'entity_type', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['vehicle', 'driver', 'contract', 'vehicle_inspection', 'vehicle_inspection_inspector', 'vehicle_inspection_coordinator'])),
            new OA\Parameter(name: 'entity_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function latest(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'entity_type' => ['required', 'string', 'in:vehicle,driver,contract,vehicle_inspection,vehicle_inspection_inspector,vehicle_inspection_coordinator'],
                'entity_id' => ['required', 'integer', 'min:1'],
            ]);

            $signature = $this->signatureService->getLatest(
                $request->input('entity_type'),
                $request->integer('entity_id')
            );

            return $this->successResponse($signature, 'Firma más reciente recuperada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/signatures/{uuid}',
        summary: 'Reemplazar la firma activa de una entidad',
        operationId: 'replaceSignature',
        tags: ['Firma'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['signature'],
                properties: [
                    new OA\Property(property: 'signature', type: 'string', description: 'Nueva firma en formato base64 (data:image/png;base64,...)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Firma actualizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Firma no encontrada.'),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function replace(StoreSignatureRequest $request, string $uuid): JsonResponse
    {
        try {
            $data = $request->validated();

            if (isset($data['entity_type']) && $data['entity_type'] === 'vehicle_inspection') {
                if (!empty($data['role'])) {
                    $data['entity_type'] = $data['role'] === 'inspector'
                        ? 'vehicle_inspection_inspector'
                        : 'vehicle_inspection_coordinator';
                }
            }

            $signature = $this->signatureService->replace(
                $uuid,
                $data
            );

            return $this->successResponse($signature, 'Firma actualizada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/signatures/{uuid}',
        summary: 'Eliminar una firma por UUID',
        operationId: 'destroySignature',
        tags: ['Firma'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Firma eliminada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Firma no encontrada.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $deleted = $this->signatureService->delete($uuid);
            if (! $deleted) {
                return $this->errorResponse('Firma no encontrada.', 404);
            }

            return $this->successResponse(null, 'Firma eliminada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
