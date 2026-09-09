<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\Company\StoreCompanyRequest;
use App\Http\Requests\Administrations\Company\UpdateCompanyRequest;
use App\Services\Administrations\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral de Empresas.
 *
 * Provee los endpoints necesarios para el mantenimiento del registro maestro de empresas,
 * permitiendo operaciones de consulta, creación, actualización y eliminación.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService
    ) {}

    #[OA\Get(
        path: '/api/v1/administration/companies',
        summary: 'Consultar listado paginado de Empresas',
        operationId: 'listCompanies',
        tags: ['Empresa'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Listado recuperado exitosamente.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->attributes->get('current_company_uuid') ?? $request->query('company_uuid') ?? $request->query('companyUuid');

            $data = $this->companyService->getAllCompaniesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de empresas recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/companies/list',
        summary: 'Obtener catálogo completo de Empresas sin paginación',
        operationId: 'getAllCompanies',
        tags: ['Empresa'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Catálogo completo de empresas.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->attributes->get('current_company_uuid') ?? $request->query('company_uuid') ?? $request->query('companyUuid');
            $data = $this->companyService->getAllCompanies($companyUuid);

            return $this->successResponse($data, 'Catálogo completo de empresas obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/companies',
        summary: 'Registrar una nueva Empresa',
        operationId: 'storeCompany',
        tags: ['Empresa'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Empresa creada exitosamente.'),
        ]
    )]
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        try {
            $record = $this->companyService->createCompany($request->validated());

            return $this->successResponse($record, 'Empresa registrada correctamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/companies/{uuid}',
        summary: 'Obtener información detallada de una Empresa',
        operationId: 'showCompany',
        tags: ['Empresa'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle de la empresa encontrado.'),
            new OA\Response(response: 404, description: 'La empresa solicitada no existe.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->companyService->getCompanyByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Empresa no encontrada.', 404);
            }

            return $this->successResponse($record, 'Detalle de la empresa obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/companies/{uuid}/profile',
        summary: 'Obtener perfil integral de una Empresa con todas sus relaciones',
        operationId: 'profileCompany',
        tags: ['Empresa'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Perfil integral obtenido correctamente.'),
            new OA\Response(response: 404, description: 'Empresa no encontrada.'),
        ]
    )]
    public function profile(string $uuid): JsonResponse
    {
        try {
            $record = $this->companyService->getCompanyProfile($uuid);
            if (! $record) {
                return $this->errorResponse('Empresa no encontrada.', 404);
            }

            return $this->successResponse($record, 'Perfil corporativo integral obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/companies/{uuid}',
        summary: 'Actualizar los datos de una Empresa',
        operationId: 'updateCompany',
        tags: ['Empresa'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Empresa actualizada correctamente.'),
        ]
    )]
    public function update(UpdateCompanyRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->companyService->getCompanyByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('No se puede actualizar una empresa inexistente.', 404);
            }
            $updated = $this->companyService->updateCompany($uuid, $request->validated());

            return $this->successResponse($updated, 'Datos de la empresa actualizados.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/companies/{uuid}',
        summary: 'Eliminar una Empresa del sistema',
        operationId: 'destroyCompany',
        tags: ['Empresa'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Empresa eliminada exitosamente.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->companyService->getCompanyByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La empresa que intenta eliminar no existe.', 404);
            }
            $this->companyService->deleteCompany($uuid);

            return $this->successResponse(null, 'Empresa retirada del sistema correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/administration/companies/{uuid}/toggle-status',
        summary: 'Cambiar el estado activo/inactivo de una Empresa',
        operationId: 'toggleCompanyStatus',
        tags: ['Empresa'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado de la empresa actualizado.'),
            new OA\Response(response: 404, description: 'La empresa solicitada no existe.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->companyService->getCompanyByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La empresa que intenta modificar no existe.', 404);
            }
            $updated = $this->companyService->toggleCompanyStatus($uuid);
            $estado = $updated->is_active ? 'activada' : 'desactivada';

            return $this->successResponse($updated, "Empresa {$estado} correctamente.");
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/companies/{uuid}/logo',
        summary: 'Subir o reemplazar el logotipo de una Empresa',
        operationId: 'uploadCompanyLogo',
        tags: ['Empresa'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['logo'],
                    properties: [
                        new OA\Property(property: 'logo', type: 'string', format: 'binary', description: 'Archivo de imagen del logotipo (PNG, JPG, SVG).'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Logo actualizado correctamente.'),
            new OA\Response(response: 404, description: 'Empresa no encontrada.'),
            new OA\Response(response: 422, description: 'Archivo inválido.'),
        ]
    )]
    public function uploadLogo(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ]);

        try {
            $company = $this->companyService->getCompanyByUuid($uuid);
            if (! $company) {
                return $this->errorResponse('Empresa no encontrada.', 404);
            }

            $file = $request->file('logo');
            $logoUrl = $this->companyService->uploadCompanyLogo($uuid, $file);

            return $this->successResponse(['logo_url' => $logoUrl], 'Logotipo de la empresa actualizado correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/companies/{uuid}/signature',
        summary: 'Subir o reemplazar la firma del representante legal',
        operationId: 'uploadCompanySignature',
        tags: ['Empresa'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['signature'],
                    properties: [
                        new OA\Property(property: 'signature', type: 'string', format: 'binary', description: 'Archivo de imagen de la firma (PNG, JPG, SVG).'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Firma actualizada correctamente.'),
            new OA\Response(response: 404, description: 'Empresa no encontrada.'),
            new OA\Response(response: 422, description: 'Archivo inválido.'),
        ]
    )]
    public function uploadSignature(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ]);

        try {
            $company = $this->companyService->getCompanyByUuid($uuid);
            if (! $company) {
                return $this->errorResponse('Empresa no encontrada.', 404);
            }

            $file = $request->file('signature');
            $signatureUrl = $this->companyService->uploadLegalRepresentativeSignature($uuid, $file);

            return $this->successResponse(['signature_url' => $signatureUrl], 'Firma del representante legal actualizada correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
