<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\AffiliateAdminCharge\StoreAffiliateAdminChargeRequest;
use App\Http\Requests\Fleet\AffiliateAdminCharge\UpdateAffiliateAdminChargeRequest;
use App\Services\Fleet\AffiliateAdminChargeService;
use App\Services\Pdf\PdfService;
use App\Utils\Logger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral de AffiliateAdminCharge.
 *
 * @author   Darwin Montes
 *
 * @version  2.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-31
 */
class AffiliateAdminChargeController extends Controller
{
    public function __construct(
        private readonly AffiliateAdminChargeService $chargeService
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  CRUD Estándar
    // ──────────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/fleet-management/affiliate-admin-charges',
        summary: 'Consultar listado paginado de cargos de administración de afiliados',
        operationId: 'listAffiliateAdminCharges',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
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
            $data = $this->chargeService->getAllAffiliateAdminChargesWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de cargos de administración recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/affiliate-admin-charges/list',
        summary: 'Obtener catálogo completo de cargos de administración de afiliados',
        operationId: 'getAllAffiliateAdminCharges',
        tags: ['CargoAdministracionAfiliado'],
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
            $data = $this->chargeService->getAllAffiliateAdminCharges($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de cargos de administración recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/affiliate-admin-charges/{uuid}',
        summary: 'Obtener detalle de un cargo de administración por UUID',
        operationId: 'showAffiliateAdminCharge',
        tags: ['CargoAdministracionAfiliado'],
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
            $record = $this->chargeService->getAffiliateAdminChargeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El cargo de administración solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle del cargo de administración recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/affiliate-admin-charges',
        summary: 'Crear nuevo registro de cargo de administración',
        operationId: 'storeAffiliateAdminCharge',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreAffiliateAdminChargeRequest $request): JsonResponse
    {
        try {
            $record = $this->chargeService->createAffiliateAdminCharge($request->validated());

            return $this->successResponse($record, 'Cargo de administración creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/affiliate-admin-charges/{uuid}',
        summary: 'Actualizar registro de cargo de administración por UUID',
        operationId: 'updateAffiliateAdminCharge',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateAffiliateAdminChargeRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->chargeService->getAffiliateAdminChargeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El cargo de administración que desea actualizar no existe.', 404);
            }
            $updated = $this->chargeService->updateAffiliateAdminCharge($uuid, $request->validated());

            return $this->successResponse($updated, 'Cargo de administración actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/affiliate-admin-charges/{uuid}',
        summary: 'Eliminar registro de cargo de administración por UUID',
        operationId: 'destroyAffiliateAdminCharge',
        tags: ['CargoAdministracionAfiliado'],
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
            $record = $this->chargeService->getAffiliateAdminChargeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El cargo de administración que desea eliminar no existe.', 404);
            }
            $this->chargeService->deleteAffiliateAdminCharge($uuid);

            return $this->successResponse(null, 'Cargo de administración eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Acciones de Negocio
    // ──────────────────────────────────────────────────────────────

    #[OA\Post(
        path: '/api/v1/fleet-management/affiliate-admin-charges/{uuid}/apply-monthly-payment',
        summary: 'Aplicar pago de mensualidad sobre un cargo de administración',
        operationId: 'applyMonthlyPayment',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'payment_date', type: 'string', format: 'date', description: 'Fecha de pago (por defecto hoy)'),
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['EFECTIVO', 'TRANSFERENCIA', 'CHEQUE', 'TARJETA', 'CORTESIA', 'OTRO'], description: 'Método de pago'),
                    new OA\Property(property: 'bank_reference', type: 'string', description: 'Referencia bancaria del pago'),
                    new OA\Property(property: 'notes', type: 'string', description: 'Notas opcionales del recaudo'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pago aplicado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 422, description: 'Validación de datos fallida.'),
            new OA\Response(response: 500, description: 'Error de servidor.'),
        ]
    )]
    public function applyMonthlyPayment(Request $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->chargeService->getAffiliateAdminChargeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El cargo de administración solicitado no existe.', 404);
            }

            $validated = $request->validate([
                'payment_date' => 'sometimes|required|date',
                'payment_method' => 'sometimes|required|string|in:EFECTIVO,TRANSFERENCIA,CHEQUE,TARJETA,CORTESIA,OTRO',
                'bank_reference' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            $updated = $this->chargeService->applyMonthlyPayment($uuid, $validated);

            return $this->successResponse($updated, 'Pago de mensualidad aplicado con éxito.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Los datos de pago proporcionados no son válidos: '.json_encode($e->errors()), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/fleet-management/affiliate-admin-charges/{uuid}/update-status',
        summary: 'Actualizar estado de un cargo de administración',
        operationId: 'updateAffiliateAdminChargeStatus',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['PENDIENTE', 'PAGADO', 'VENCIDO', 'EN_MORA', 'ANULADO'], description: 'Nuevo estado del cargo'),
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['EFECTIVO', 'TRANSFERENCIA', 'CHEQUE', 'TARJETA', 'CORTESIA', 'OTRO'], description: 'Método de pago'),
                    new OA\Property(property: 'payment_date', type: 'string', format: 'date', description: 'Fecha de pago (requerido si PAGADO)'),
                    new OA\Property(property: 'bank_reference', type: 'string', description: 'Referencia bancaria del pago'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Estado actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 422, description: 'Validación de datos fallida.'),
        ]
    )]
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|in:PENDIENTE,PAGADO,VENCIDO,EN_MORA,ANULADO',
                'payment_method' => 'nullable|string|in:EFECTIVO,TRANSFERENCIA,CHEQUE,TARJETA,CORTESIA,OTRO',
                'payment_date' => 'nullable|date',
                'bank_reference' => 'nullable|string|max:100',
            ]);

            $updated = $this->chargeService->updateStatus(
                $uuid,
                $validated['status'],
                $validated['payment_date'] ?? null,
                $validated['bank_reference'] ?? null,
                $validated['payment_method'] ?? null
            );

            return $this->successResponse($updated, 'Estado del cargo de administración actualizado con éxito.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Los datos proporcionados no son válidos: '.json_encode($e->errors()), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Consultas Avanzadas
    // ──────────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/fleet-management/affiliate-admin-charges/vehicle/{vehicleUuid}',
        summary: 'Obtener cargos de administración por vehículo',
        operationId: 'getChargesByVehicle',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'vehicleUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cobros del vehículo obtenidos con éxito.'),
        ]
    )]
    public function getByVehicle(string $vehicleUuid): JsonResponse
    {
        try {
            $data = $this->chargeService->getByVehicle($vehicleUuid);

            return $this->successResponse($data, 'Cobros del vehículo recuperados con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/affiliate-admin-charges/vehicle/{vehicleUuid}/next-due',
        summary: 'Obtener el próximo vencimiento de un vehículo',
        operationId: 'getNextDueDate',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'vehicleUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Próximo vencimiento obtenido.'),
        ]
    )]
    public function getNextDueDate(string $vehicleUuid): JsonResponse
    {
        try {
            $data = $this->chargeService->getNextDueDate($vehicleUuid);

            return $this->successResponse($data, $data ? 'Próximo vencimiento encontrado.' : 'No hay vencimientos pendientes.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/affiliate-admin-charges/history/{paymentReference}',
        summary: 'Obtener historial de pagos por referencia de pago',
        operationId: 'getPaymentHistory',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentReference', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Historial de pagos obtenido con éxito.'),
        ]
    )]
    public function getPaymentHistory(string $paymentReference): JsonResponse
    {
        try {
            $data = $this->chargeService->getPaymentHistory($paymentReference);

            return $this->successResponse($data, 'Historial de pagos recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/affiliate-admin-charges/pending/{paymentReference}',
        summary: 'Obtener el cobro pendiente más antiguo por referencia de pago',
        operationId: 'getPendingCharge',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentReference', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cobro pendiente obtenido.'),
        ]
    )]
    public function getPendingCharge(string $paymentReference): JsonResponse
    {
        try {
            $data = $this->chargeService->getPendingCharge($paymentReference);

            return $this->successResponse($data, $data ? 'Cobro pendiente encontrado.' : 'No hay cobros pendientes para esta referencia.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/affiliate-admin-charges/summary',
        summary: 'Obtener resumen estadístico de cobros por periodo',
        operationId: 'getChargeSummary',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Resumen obtenido con éxito.'),
            new OA\Response(response: 422, description: 'Validación de datos fallida.'),
        ]
    )]
    public function getSummary(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'vehicle_uuid' => 'nullable|string|exists:vehicles,uuid',
            ]);

            $data = $this->chargeService->getSummary(
                $validated['date_from'],
                $validated['date_to'],
                $validated['vehicle_uuid'] ?? null
            );

            return $this->successResponse($data, 'Resumen de cobros recuperado con éxito.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Los datos proporcionados no son válidos: '.json_encode($e->errors()), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/affiliate-admin-charges/{uuid}/receipt-pdf',
        summary: 'Descargar o visualizar recibo en PDF de un cargo de administración',
        operationId: 'generateReceiptPdf',
        tags: ['CargoAdministracionAfiliado'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF generado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 500, description: 'Error de servidor.'),
        ]
    )]
    public function generateReceiptPdf(string $uuid, PdfService $pdfService)
    {
        try {
            return $pdfService->generateAffiliateAdminChargePdf($uuid);
        } catch (\Exception $e) {
            Logger::error('AffiliateAdminChargeController@generateReceiptPdf: '.$e->getMessage(), $e);
            abort(500, 'Error al generar el PDF del recibo.');
        }
    }
}
