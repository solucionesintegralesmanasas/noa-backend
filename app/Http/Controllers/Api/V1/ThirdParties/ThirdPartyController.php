<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ThirdParties;

use App\Http\Controllers\Controller;
use App\Http\Requests\ThirdParties\ThirdParty\StoreThirdPartyRequest;
use App\Http\Requests\ThirdParties\ThirdParty\UpdateThirdPartyRequest;
use App\Services\Pdf\PdfService;
use App\Services\ThirdParties\ThirdPartyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Tercero.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ThirdPartyController extends Controller
{
    public function __construct(
        private readonly ThirdPartyService $thirdPartyService
    ) {}

    #[OA\Get(
        path: '/api/v1/third-parties',
        summary: 'Consultar listado paginado de Tercero',
        operationId: 'listThirdParties',
        tags: ['Tercero'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['is_customer', 'is_supplier', 'is_employee', 'is_affiliate', 'is_driver', 'is_others'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
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
            $type = $request->query('type');

            $data = $this->thirdPartyService->getAllThirdPartiesWithPagination($perPage, $page, $search, $companyUuid, $type);

            return $this->successResponse($data, 'Listado paginado de Tercero recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/third-parties/list',
        summary: 'Obtener catálogo de Tercero',
        operationId: 'getAllThirdParties',
        tags: ['Tercero'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['is_customer', 'is_supplier', 'is_employee', 'is_affiliate', 'is_driver', 'is_others']), description: 'Filtrar por rol del tercero'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid');
            $type = $request->query('type');
            $data = $this->thirdPartyService->getAllThirdParties($companyUuid, $type);

            return $this->successResponse($data, 'Catálogo de Tercero recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/third-parties/{uuid}',
        summary: 'Obtener detalle de Tercero por UUID',
        operationId: 'showThirdParty',
        tags: ['Tercero'],
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
            $record = $this->thirdPartyService->getThirdPartyByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Tercero solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Tercero recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/third-parties/{uuid}/technical-sheet',
        summary: 'Obtener ficha técnica completa de Tercero (Conductor)',
        operationId: 'technicalSheetThirdParty',
        tags: ['Tercero'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ficha técnica recuperada con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno.'),
        ]
    )]
    public function technicalSheet(Request $request, string $uuid): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid', $request->attributes->get('current_company_uuid', ''));
            $vehicleUuid = $request->query('vehicle_uuid');

            $record = $this->thirdPartyService->technicalSheet($companyUuid, $uuid, $vehicleUuid);

            return $this->successResponse($record, 'Ficha técnica del conductor recuperada con éxito.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('El registro de Tercero solicitado no existe.', 404);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/third-parties/{uuid}/technical-sheet/pdf',
        summary: 'Exportar ficha técnica de Tercero en formato PDF',
        operationId: 'technicalSheetThirdPartyPdf',
        tags: ['Tercero'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno.'),
        ]
    )]
    public function technicalSheetPdf(Request $request, string $uuid, PdfService $pdfService)
    {
        try {
            $companyUuid = $request->query('company_uuid', $request->attributes->get('current_company_uuid', ''));
            $vehicleUuid = $request->query('vehicle_uuid');

            return $pdfService->generateTechnicalSheetPdf($this->thirdPartyService, $companyUuid, $uuid, $vehicleUuid);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('El registro de Tercero solicitado no existe.', 404);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/third-parties',
        summary: 'Crear nuevo registro de Tercero',
        operationId: 'storeThirdParty',
        tags: ['Tercero'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['company_uuid', 'person_type', 'document_type_uuid', 'document_number', 'email', 'municipality_uuid', 'tax_regime', 'tax_responsibility_uuid'],
                properties: [
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'person_type', type: 'string', enum: ['NATURAL', 'JURIDICA']),
                    new OA\Property(property: 'document_type_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'document_number', type: 'string'),
                    new OA\Property(property: 'nit_check_digit', type: 'string', nullable: true),
                    new OA\Property(property: 'trade_name', type: 'string', nullable: true),
                    new OA\Property(property: 'company_name', type: 'string', nullable: true),
                    new OA\Property(property: 'first_name', type: 'string', nullable: true),
                    new OA\Property(property: 'last_name', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'municipality_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'tax_regime', type: 'string'),
                    new OA\Property(property: 'tax_responsibility_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'bank_account_number', type: 'string', nullable: true),
                    new OA\Property(property: 'bank_account_type', type: 'string', nullable: true),
                    new OA\Property(property: 'bank_name', type: 'string', nullable: true),
                    new OA\Property(property: 'cost_center_uuid', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'is_customer', type: 'boolean', default: false),
                    new OA\Property(property: 'is_supplier', type: 'boolean', default: false),
                    new OA\Property(property: 'is_employee', type: 'boolean', default: false),
                    new OA\Property(property: 'is_affiliate', type: 'boolean', default: false),
                    new OA\Property(property: 'is_driver', type: 'boolean', default: false),
                    new OA\Property(property: 'is_others', type: 'boolean', default: false),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true),
                    new OA\Property(property: 'affiliate_uuid', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'license_number', type: 'string', nullable: true),
                    new OA\Property(property: 'license_category', type: 'string', enum: ['C1', 'C2', 'C3'], nullable: true),
                    new OA\Property(property: 'license_issue_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'license_expiration_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'license_restrictions', type: 'string', nullable: true),
                    new OA\Property(property: 'license_status', type: 'string', enum: ['ACTIVA', 'SUSPENDIDA', 'VENCIDA', 'CANCELADA'], nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreThirdPartyRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $record = $this->thirdPartyService->createThirdParty($data);

            $message = 'Registro de Tercero creado con éxito.';

            if (! empty($data['is_driver']) && $data['is_driver']) {
                $message = 'Conductor registrado con éxito.';
            } elseif (! empty($data['is_customer']) && $data['is_customer']) {
                $message = 'Cliente registrado con éxito.';
            } elseif (! empty($data['is_supplier']) && $data['is_supplier']) {
                $message = 'Proveedor registrado con éxito.';
            }

            return $this->successResponse($record, $message, 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/third-parties/{uuid}',
        summary: 'Actualizar registro de Tercero por UUID',
        operationId: 'updateThirdParty',
        tags: ['Tercero'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'person_type', type: 'string', enum: ['NATURAL', 'JURIDICA']),
                    new OA\Property(property: 'document_type_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'document_number', type: 'string'),
                    new OA\Property(property: 'nit_check_digit', type: 'string', nullable: true),
                    new OA\Property(property: 'trade_name', type: 'string', nullable: true),
                    new OA\Property(property: 'company_name', type: 'string', nullable: true),
                    new OA\Property(property: 'first_name', type: 'string', nullable: true),
                    new OA\Property(property: 'last_name', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'municipality_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'tax_regime', type: 'string'),
                    new OA\Property(property: 'tax_responsibility_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'bank_account_number', type: 'string', nullable: true),
                    new OA\Property(property: 'bank_account_type', type: 'string', nullable: true),
                    new OA\Property(property: 'bank_name', type: 'string', nullable: true),
                    new OA\Property(property: 'cost_center_uuid', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'is_customer', type: 'boolean'),
                    new OA\Property(property: 'is_supplier', type: 'boolean'),
                    new OA\Property(property: 'is_employee', type: 'boolean'),
                    new OA\Property(property: 'is_affiliate', type: 'boolean'),
                    new OA\Property(property: 'is_driver', type: 'boolean'),
                    new OA\Property(property: 'is_others', type: 'boolean'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'affiliate_uuid', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'license_number', type: 'string', nullable: true),
                    new OA\Property(property: 'license_category', type: 'string', enum: ['C1', 'C2', 'C3'], nullable: true),
                    new OA\Property(property: 'license_issue_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'license_expiration_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'license_restrictions', type: 'string', nullable: true),
                    new OA\Property(property: 'license_status', type: 'string', enum: ['ACTIVA', 'SUSPENDIDA', 'VENCIDA', 'CANCELADA'], nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateThirdPartyRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->thirdPartyService->getThirdPartyByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Tercero que desea actualizar no existe.', 404);
            }

            $data = $request->validated();
            $updated = $this->thirdPartyService->updateThirdParty($uuid, $data);

            $message = 'Registro de Tercero actualizado con éxito.';

            if (! empty($data['is_driver']) && $data['is_driver']) {
                $message = 'Conductor actualizado con éxito.';
            } elseif (! empty($data['is_customer']) && $data['is_customer']) {
                $message = 'Cliente actualizado con éxito.';
            } elseif (! empty($data['is_supplier']) && $data['is_supplier']) {
                $message = 'Proveedor actualizado con éxito.';
            }

            return $this->successResponse($updated, $message);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/third-parties/{uuid}',
        summary: 'Eliminar registro de Tercero por UUID',
        operationId: 'destroyThirdParty',
        tags: ['Tercero'],
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
            $record = $this->thirdPartyService->getThirdPartyByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Tercero que desea eliminar no existe.', 404);
            }
            $this->thirdPartyService->deleteThirdParty($uuid);

            return $this->successResponse(null, 'Registro de Tercero eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/third-parties/{uuid}/toggle-status',
        summary: 'Alternar estado de Tercero por UUID',
        operationId: 'toggleStatusThirdParty',
        tags: ['Tercero'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->thirdPartyService->getThirdPartyByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Tercero no existe.', 404);
            }
            $updated = $this->thirdPartyService->toggleStatusThirdParty($uuid);

            return $this->successResponse($updated, 'Estado del Tercero actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/third-parties/{uuid}/photo',
        summary: 'Subir o reemplazar la foto de perfil del conductor',
        operationId: 'uploadThirdPartyPhoto',
        tags: ['Tercero'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['photo'],
                    properties: [
                        new OA\Property(property: 'photo', type: 'string', format: 'binary', description: 'Archivo de imagen (PNG, JPG, JPEG).'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Foto actualizada correctamente.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 422, description: 'Archivo inválido.'),
        ]
    )]
    public function uploadPhoto(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        try {
            $record = $this->thirdPartyService->getThirdPartyByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Tercero no existe.', 404);
            }

            $file = $request->file('photo');
            $photoUrl = $this->thirdPartyService->uploadThirdPartyPhoto($uuid, $file);

            return $this->successResponse(['photo_url' => $photoUrl], 'Foto de perfil actualizada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
