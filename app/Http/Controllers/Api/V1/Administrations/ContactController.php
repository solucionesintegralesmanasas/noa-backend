<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\Contact\StoreContactRequest;
use App\Http\Requests\Administrations\Contact\UpdateContactRequest;
use App\Services\Administrations\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador para la gestión de Contactos de Empresas.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contactService
    ) {}

    #[OA\Get(
        path: '/api/v1/administration/contacts',
        summary: 'Lista de contactos paginada',
        operationId: 'listContacts',
        tags: ['Contacto'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Listado obtenido con éxito.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->contactService->getAllContactsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado de contactos recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/contacts/list',
        summary: 'Obtener catálogo de Contacto',
        operationId: 'listContacto',
        tags: ['Contacto'],
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
            $data = $this->contactService->getAllContacts();

            return $this->successResponse($data, 'Catálogo completo de contacto obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/contacts',
        summary: 'Registrar nuevo contacto empresarial',
        operationId: 'storeContact',
        tags: ['Contacto'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Contacto registrado.'),
        ]
    )]
    public function store(StoreContactRequest $request): JsonResponse
    {
        try {
            $record = $this->contactService->createContact($request->validated());

            return $this->successResponse($record, 'Contacto creado exitosamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/contacts/{uuid}',
        summary: 'Ver detalle de un contacto',
        operationId: 'showContact',
        tags: ['Contacto'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del contacto.'),
            new OA\Response(response: 404, description: 'Contacto inexistente.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->contactService->getContactByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Contacto no encontrado.', 404);
            }

            return $this->successResponse($record, 'Información del contacto obtenida.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/contacts/{uuid}',
        summary: 'Actualizar contacto empresarial',
        operationId: 'updateContact',
        tags: ['Contacto'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Datos actualizados.'),
        ]
    )]
    public function update(UpdateContactRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->contactService->getContactByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El contacto no existe.', 404);
            }
            $updated = $this->contactService->updateContact($uuid, $request->validated());

            return $this->successResponse($updated, 'Contacto actualizado correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/contacts/{uuid}',
        summary: 'Eliminar contacto',
        operationId: 'destroyContact',
        tags: ['Contacto'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contacto eliminado.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->contactService->getContactByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El contacto ya ha sido eliminado o no existe.', 404);
            }
            $this->contactService->deleteContact($uuid);

            return $this->successResponse(null, 'Contacto removido con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
