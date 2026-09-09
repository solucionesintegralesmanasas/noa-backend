<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\TaxDeclaration\StoreTaxDeclarationRequest;
use App\Http\Requests\Administrations\TaxDeclaration\UpdateTaxDeclarationRequest;
use App\Services\Administrations\TaxDeclarationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Declaraciones de Renta.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class TaxDeclarationController extends Controller
{
    public function __construct(private readonly TaxDeclarationService $taxDeclarationService) {}

    #[OA\Get(
        path: '/api/v1/administration/tax-declarations',
        summary: 'Consultar listado de declaraciones de renta',
        operationId: 'listTaxDeclarations',
        tags: ['DeclaraciónRenta'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado obtenido exitosamente.')]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');

            $data = $this->taxDeclarationService->getAllTaxDeclarationsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Declaraciones de renta recuperadas.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/tax-declarations/list',
        summary: 'Obtener catálogo de DeclaraciónRenta',
        operationId: 'getAllTaxDeclarations',
        tags: ['DeclaraciónRenta'],
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
            $data = $this->taxDeclarationService->getAllTaxDeclarations();

            return $this->successResponse($data, 'Catálogo completo de declaración de renta obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/tax-declarations',
        summary: 'Registrar nueva declaración tributaria',
        operationId: 'storeTaxDeclaration',
        tags: ['DeclaraciónRenta'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Creado.')]
    )]
    public function store(StoreTaxDeclarationRequest $request): JsonResponse
    {
        try {
            $record = $this->taxDeclarationService->createTaxDeclaration($request->validated());

            return $this->successResponse($record, 'Declaración tributaria registrada correctamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/tax-declarations/{uuid}',
        summary: 'Ver información de una declaración',
        operationId: 'showTaxDeclaration',
        tags: ['DeclaraciónRenta'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Declaración encontrada.'),
            new OA\Response(response: 404, description: 'No se localizó la declaración.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->taxDeclarationService->getTaxDeclarationByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Declaración de renta inexistente.', 404);
            }

            return $this->successResponse($record, 'Información fiscal obtenida.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/tax-declarations/{uuid}',
        summary: 'Actualizar declaración fiscal',
        operationId: 'updateTaxDeclaration',
        tags: ['DeclaraciónRenta'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Actualización exitosa.')]
    )]
    public function update(UpdateTaxDeclarationRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->taxDeclarationService->getTaxDeclarationByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('No se puede actualizar una declaración inexistente.', 404);
            }
            $updated = $this->taxDeclarationService->updateTaxDeclaration($uuid, $request->validated());

            return $this->successResponse($updated, 'Datos de la declaración actualizados correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/tax-declarations/{uuid}',
        summary: 'Eliminar declaración tributaria',
        operationId: 'destroyTaxDeclaration',
        tags: ['DeclaraciónRenta'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Eliminado exitosamente.')]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->taxDeclarationService->getTaxDeclarationByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La declaración que intenta eliminar no existe.', 404);
            }
            $this->taxDeclarationService->deleteTaxDeclaration($uuid);

            return $this->successResponse(null, 'Declaración de renta retirada exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/administration/tax-declarations/{uuid}/toggle-status',
        summary: 'Alternar estado activo/inactivo de DeclaraciónRenta',
        operationId: 'toggleStatusTaxDeclaration',
        tags: ['DeclaraciónRenta'],
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
            $record = $this->taxDeclarationService->getTaxDeclarationByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que intenta modificar no existe.', 404);
            }
            $updated = $this->taxDeclarationService->toggleTaxDeclarationStatus($uuid);
            $activo = $updated->is_active ?? $updated->status ?? false;
            $estado = $activo ? 'activado' : 'desactivado';

            return $this->successResponse($updated, ucfirst('declaración de renta').' '.$estado.' correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
