<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\SocialSecurityContribution\StoreSocialSecurityContributionRequest;
use App\Http\Requests\Fleet\SocialSecurityContribution\UpdateSocialSecurityContributionRequest;
use App\Services\Fleet\SocialSecurityContributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de AporteSeguridadSocial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class SocialSecurityContributionController extends Controller
{
    public function __construct(
        private readonly SocialSecurityContributionService $contributionService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/social-security-contributions',
        summary: 'Consultar listado paginado de AporteSeguridadSocial',
        operationId: 'listSocialSecurityContributions',
        tags: ['AporteSeguridadSocial'],
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
            $data = $this->contributionService->getAllSocialSecurityContributionsWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de AporteSeguridadSocial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/social-security-contributions/list',
        summary: 'Obtener catálogo de AporteSeguridadSocial',
        operationId: 'getAllSocialSecurityContributions',
        tags: ['AporteSeguridadSocial'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->contributionService->getAllSocialSecurityContributions($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de AporteSeguridadSocial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/social-security-contributions/{uuid}',
        summary: 'Obtener detalle de AporteSeguridadSocial por UUID',
        operationId: 'showSocialSecurityContribution',
        tags: ['AporteSeguridadSocial'],
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
            $record = $this->contributionService->getSocialSecurityContributionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de AporteSeguridadSocial solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de AporteSeguridadSocial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/social-security-contributions',
        summary: 'Crear nuevo registro de AporteSeguridadSocial',
        operationId: 'storeSocialSecurityContribution',
        tags: ['AporteSeguridadSocial'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreSocialSecurityContributionRequest $request): JsonResponse
    {
        try {
            $record = $this->contributionService->createSocialSecurityContribution($request->validated());

            return $this->successResponse($record, 'Aporte de seguridad social registrado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/social-security-contributions/{uuid}',
        summary: 'Actualizar registro de AporteSeguridadSocial por UUID',
        operationId: 'updateSocialSecurityContribution',
        tags: ['AporteSeguridadSocial'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateSocialSecurityContributionRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->contributionService->getSocialSecurityContributionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de AporteSeguridadSocial que desea actualizar no existe.', 404);
            }
            $updated = $this->contributionService->updateSocialSecurityContribution($uuid, $request->validated());

            return $this->successResponse($updated, 'Aporte de seguridad social actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/social-security-contributions/{uuid}',
        summary: 'Eliminar registro de AporteSeguridadSocial por UUID',
        operationId: 'destroySocialSecurityContribution',
        tags: ['AporteSeguridadSocial'],
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
            $record = $this->contributionService->getSocialSecurityContributionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de AporteSeguridadSocial que desea eliminar no existe.', 404);
            }
            $this->contributionService->deleteSocialSecurityContribution($uuid);

            return $this->successResponse(null, 'Aporte de seguridad social eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
