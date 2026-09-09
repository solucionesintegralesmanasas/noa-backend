<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\DriverLicense\StoreDriverLicenseRequest;
use App\Http\Requests\Fleet\DriverLicense\UpdateDriverLicenseRequest;
use App\Services\Fleet\DriverLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de LicenciaConduccion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class DriverLicenseController extends Controller
{
    public function __construct(
        private readonly DriverLicenseService $licenseService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/driver-licenses',
        summary: 'Consultar listado paginado de LicenciaConduccion',
        operationId: 'listDriverLicenses',
        tags: ['LicenciaConduccion'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->licenseService->getAllDriverLicensesWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de LicenciaConduccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/driver-licenses/list',
        summary: 'Obtener catálogo de LicenciaConduccion',
        operationId: 'getAllDriverLicenses',
        tags: ['LicenciaConduccion'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'third_party_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->licenseService->getAllDriverLicenses($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de LicenciaConduccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/driver-licenses/{uuid}',
        summary: 'Obtener detalle de LicenciaConduccion por UUID',
        operationId: 'showDriverLicense',
        tags: ['LicenciaConduccion'],
        security: [['bearerAuth' => []]],
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
            $record = $this->licenseService->getDriverLicenseByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de LicenciaConduccion solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de LicenciaConduccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/driver-licenses',
        summary: 'Crear nuevo registro de LicenciaConduccion',
        operationId: 'storeDriverLicense',
        tags: ['LicenciaConduccion'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'driver_license',
                        type: 'object',
                        required: ['company_uuid', 'third_party_uuid', 'number', 'category', 'issue_date', 'expiration_date'],
                        properties: [
                            new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid', example: '7a8e71fa-6509-41bc-a288-f1f3ad450b79'),
                            new OA\Property(property: 'third_party_uuid', type: 'string', format: 'uuid', example: '1da4d6d4-285e-48a9-8c75-0a6d13d3f337'),
                            new OA\Property(property: 'number', type: 'string', maxLength: 50, example: 'LIC-123456'),
                            new OA\Property(property: 'category', type: 'string', enum: ['C1', 'C2', 'C3'], example: 'C1'),
                            new OA\Property(property: 'issue_date', type: 'string', format: 'date', example: '2020-01-01'),
                            new OA\Property(property: 'expiration_date', type: 'string', format: 'date', example: '2030-01-01'),
                            new OA\Property(property: 'restrictions', type: 'string', maxLength: 255, nullable: true, example: 'Ninguna'),
                            new OA\Property(property: 'status', type: 'string', enum: ['ACTIVA', 'SUSPENDIDA', 'VENCIDA', 'CANCELADA'], example: 'ACTIVA'),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreDriverLicenseRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $record = $this->licenseService->createDriverLicense($validated['driver_license'] ?? $validated);

            return $this->successResponse($record, 'Licencia de conducción registrada con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/driver-licenses/{uuid}',
        summary: 'Actualizar registro de LicenciaConduccion por UUID',
        operationId: 'updateDriverLicense',
        tags: ['LicenciaConduccion'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'driver_license',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'third_party_uuid', type: 'string', format: 'uuid', example: '1da4d6d4-285e-48a9-8c75-0a6d13d3f337'),
                            new OA\Property(property: 'number', type: 'string', maxLength: 50, example: 'LIC-123456'),
                            new OA\Property(property: 'category', type: 'string', enum: ['C1', 'C2', 'C3'], example: 'C2'),
                            new OA\Property(property: 'issue_date', type: 'string', format: 'date', example: '2020-01-01'),
                            new OA\Property(property: 'expiration_date', type: 'string', format: 'date', example: '2030-01-01'),
                            new OA\Property(property: 'restrictions', type: 'string', maxLength: 255, nullable: true, example: 'Requiere anteojos'),
                            new OA\Property(property: 'status', type: 'string', enum: ['ACTIVA', 'SUSPENDIDA', 'VENCIDA', 'CANCELADA'], example: 'SUSPENDIDA'),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateDriverLicenseRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->licenseService->getDriverLicenseByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de LicenciaConduccion que desea actualizar no existe.', 404);
            }
            $validated = $request->validated();
            $updated = $this->licenseService->updateDriverLicense($uuid, $validated['driver_license'] ?? $validated);

            return $this->successResponse($updated, 'Licencia de conducción actualizada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/driver-licenses/{uuid}',
        summary: 'Eliminar registro de LicenciaConduccion por UUID',
        operationId: 'destroyDriverLicense',
        tags: ['LicenciaConduccion'],
        security: [['bearerAuth' => []]],
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
            $record = $this->licenseService->getDriverLicenseByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de LicenciaConduccion que desea eliminar no existe.', 404);
            }
            $this->licenseService->deleteDriverLicense($uuid);

            return $this->successResponse(null, 'Licencia de conducción eliminada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
