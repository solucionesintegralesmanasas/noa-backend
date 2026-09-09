<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\TaxInformation\StoreTaxInformationRequest;
use App\Http\Requests\Administrations\TaxInformation\UpdateTaxInformationRequest;
use App\Services\Administrations\TaxInformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Información Tributaria.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class TaxInformationController extends Controller
{
    public function __construct(private readonly TaxInformationService $taxInformationService) {}

    #[OA\Get(
        path: '/api/v1/administration/tax-informations',
        summary: 'Consultar listado de perfiles tributarios',
        operationId: 'listTaxInformations',
        tags: ['InformaciónTributaria'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Listado recuperado.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->taxInformationService->getAllTaxInformationsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado de información tributaria recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/tax-informations/list',
        summary: 'Obtener catálogo de InformaciónTributaria',
        operationId: 'getAllTaxInformations',
        tags: ['InformaciónTributaria'],
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
            $data = $this->taxInformationService->getAllTaxInformations();

            return $this->successResponse($data, 'Catálogo completo de información tributaria obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/tax-informations',
        summary: 'Registrar información tributaria de empresa',
        operationId: 'storeTaxInformation',
        tags: ['InformaciónTributaria'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Perfil tributario creado.'),
        ]
    )]
    public function store(StoreTaxInformationRequest $request): JsonResponse
    {
        try {
            $record = $this->taxInformationService->createTaxInformation($request->validated());

            return $this->successResponse($record, 'Perfil tributario registrado correctamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/tax-informations/{uuid}',
        summary: 'Ver perfil tributario por UUID',
        operationId: 'showTaxInformation',
        tags: ['InformaciónTributaria'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Información obtenida.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->taxInformationService->getTaxInformationByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La información tributaria solicitada no existe.', 404);
            }

            return $this->successResponse($record, 'Perfil tributario recuperado exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/tax-informations/{uuid}',
        summary: 'Actualizar perfil tributario',
        operationId: 'updateTaxInformation',
        tags: ['InformaciónTributaria'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado correctamente.'),
        ]
    )]
    public function update(UpdateTaxInformationRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->taxInformationService->getTaxInformationByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('No se puede actualizar un registro inexistente.', 404);
            }
            $updated = $this->taxInformationService->updateTaxInformation($uuid, $request->validated());

            return $this->successResponse($updated, 'Perfil tributario actualizado correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/tax-informations/{uuid}',
        summary: 'Eliminar información tributaria',
        operationId: 'destroyTaxInformation',
        tags: ['InformaciónTributaria'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Registro eliminado.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->taxInformationService->getTaxInformationByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro ya no existe.', 404);
            }
            $this->taxInformationService->deleteTaxInformation($uuid);

            return $this->successResponse(null, 'Perfil tributario eliminado satisfactoriamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
