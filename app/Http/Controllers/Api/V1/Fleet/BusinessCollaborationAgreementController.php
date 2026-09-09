<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\BusinessCollaborationAgreement\StoreBusinessCollaborationAgreementRequest;
use App\Http\Requests\Fleet\BusinessCollaborationAgreement\UpdateBusinessCollaborationAgreementRequest;
use App\Services\Fleet\BusinessCollaborationAgreementService;
use App\Services\Pdf\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de ConvenioColaboracionEmpresarial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class BusinessCollaborationAgreementController extends Controller
{
    public function __construct(
        private readonly BusinessCollaborationAgreementService $agreementService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/business-collaboration-agreements',
        summary: 'Consultar listado paginado de ConvenioColaboracionEmpresarial',
        operationId: 'listBusinessCollaborationAgreements',
        tags: ['ConvenioColaboracionEmpresarial'],
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
            $data = $this->agreementService->getAllBusinessCollaborationAgreementsWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de ConvenioColaboracionEmpresarial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/business-collaboration-agreements/list',
        summary: 'Obtener catálogo de ConvenioColaboracionEmpresarial',
        operationId: 'getAllBusinessCollaborationAgreements',
        tags: ['ConvenioColaboracionEmpresarial'],
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
            $data = $this->agreementService->getAllBusinessCollaborationAgreements($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de ConvenioColaboracionEmpresarial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/business-collaboration-agreements/next-consecutive',
        summary: 'Obtener siguiente ID interno de acuerdo (4 dígitos)',
        operationId: 'getNextBusinessCollaborationAgreementConsecutive',
        tags: ['ConvenioColaboracionEmpresarial'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Consecutivo generado con éxito.'),
        ]
    )]
    public function nextConsecutive(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $consecutive = $this->agreementService->getNextAgreementInternalId($companyUuid);

            return $this->successResponse([
                'agreement_internal_id' => $consecutive,
            ], 'Siguiente ID interno recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/business-collaboration-agreements/{uuid}',
        summary: 'Obtener detalle de ConvenioColaboracionEmpresarial por UUID',
        operationId: 'showBusinessCollaborationAgreement',
        tags: ['ConvenioColaboracionEmpresarial'],
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
            $record = $this->agreementService->getBusinessCollaborationAgreementByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ConvenioColaboracionEmpresarial solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de ConvenioColaboracionEmpresarial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/business-collaboration-agreements',
        summary: 'Crear nuevo registro de ConvenioColaboracionEmpresarial',
        operationId: 'storeBusinessCollaborationAgreement',
        tags: ['ConvenioColaboracionEmpresarial'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreBusinessCollaborationAgreementRequest $request): JsonResponse
    {
        try {
            $record = $this->agreementService->createBusinessCollaborationAgreement($request->validated());

            return $this->successResponse($record, 'Registro de ConvenioColaboracionEmpresarial creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/business-collaboration-agreements/{uuid}',
        summary: 'Actualizar registro de ConvenioColaboracionEmpresarial por UUID',
        operationId: 'updateBusinessCollaborationAgreement',
        tags: ['ConvenioColaboracionEmpresarial'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateBusinessCollaborationAgreementRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->agreementService->getBusinessCollaborationAgreementByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ConvenioColaboracionEmpresarial que desea actualizar no existe.', 404);
            }
            $updated = $this->agreementService->updateBusinessCollaborationAgreement($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de ConvenioColaboracionEmpresarial actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/business-collaboration-agreements/{uuid}',
        summary: 'Eliminar registro de ConvenioColaboracionEmpresarial por UUID',
        operationId: 'destroyBusinessCollaborationAgreement',
        tags: ['ConvenioColaboracionEmpresarial'],
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
            $record = $this->agreementService->getBusinessCollaborationAgreementByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ConvenioColaboracionEmpresarial que desea eliminar no existe.', 404);
            }
            $this->agreementService->deleteBusinessCollaborationAgreement($uuid);

            return $this->successResponse(null, 'Registro de ConvenioColaboracionEmpresarial eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/business-collaboration-agreements/{uuid}/pdf',
        summary: 'Descargar PDF del ConvenioColaboracionEmpresarial por UUID',
        operationId: 'downloadBusinessCollaborationAgreementPdf',
        tags: ['ConvenioColaboracionEmpresarial'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF descargado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function downloadPdf(string $uuid, PdfService $pdfService)
    {
        try {
            $record = $this->agreementService->getBusinessCollaborationAgreementByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ConvenioColaboracionEmpresarial no existe.', 404);
            }

            return $pdfService->generateBusinessAgreementPdf($this->agreementService, $uuid);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/fleet-management/business-collaboration-agreements/{uuid}/toggle-status',
        summary: 'Alternar el estado de activación de un ConvenioColaboracionEmpresarial',
        operationId: 'toggleBusinessCollaborationAgreementStatus',
        tags: ['ConvenioColaboracionEmpresarial'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado alternado con éxito.'),
            new OA\Response(response: 404, description: 'ConvenioColaboracionEmpresarial no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->agreementService->getBusinessCollaborationAgreementByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ConvenioColaboracionEmpresarial no existe.', 404);
            }
            $updated = $this->agreementService->toggleAgreementStatus($uuid);

            return $this->successResponse($updated, 'Estado del ConvenioColaboracionEmpresarial alternado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
