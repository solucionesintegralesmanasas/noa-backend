<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\RentalContract\StoreRentalContractRequest;
use App\Services\Fleet\RentalContractService;
use App\Services\Pdf\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador para gestión de contratos de alquiler.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-11-18
 */
class RentalContractController extends Controller
{
    /**
     * Inyección de dependencias.
     */
    public function __construct(
        private readonly RentalContractService $rentalContractService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/rental-contracts',
        summary: 'Listar contratos de alquiler paginados',
        operationId: 'listRentalContracts',
        tags: ['ContratosAlquiler'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'finished', 'cancelled'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            $page = (int) $request->query('page', 1);
            $search = (string) $request->query('search', '');
            $vehicleUuid = $request->query('vehicle_uuid');
            $status = $request->query('status');
            $sortBy = $request->query('sort_by');
            $sortOrder = $request->query('sort_order');

            $data = $this->rentalContractService->listWithPagination($perPage, $page, $search, $vehicleUuid, $status, $sortBy, $sortOrder);

            return $this->successResponse($data, 'Contratos recuperados correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/rental-contracts',
        summary: 'Crear contrato de alquiler con cuotas automáticas',
        operationId: 'createRentalContract',
        tags: ['ContratosAlquiler'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['vehicle_uuid', 'start_date', 'monthly_fee'],
                    properties: [
                        new OA\Property(property: 'vehicle_uuid', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'owner_uuid', type: 'string', format: 'uuid', nullable: true),
                        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                        new OA\Property(property: 'monthly_term', type: 'integer', default: 12),
                        new OA\Property(property: 'monthly_fee', type: 'number', format: 'float'),
                        new OA\Property(property: 'observations', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Contrato creado exitosamente.'),
        ]
    )]
    public function store(StoreRentalContractRequest $request): JsonResponse
    {
        try {
            $contract = $this->rentalContractService->create($request->validated());

            return $this->successResponse($contract, 'Contrato creado y cuotas generadas exitosamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/rental-contracts/{uuid}',
        summary: 'Obtener detalle de contrato',
        operationId: 'showRentalContract',
        tags: ['ContratosAlquiler'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
            new OA\Response(response: 404, description: 'Contrato no encontrado.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $contract = $this->rentalContractService->getDetail($uuid);

            if (!$contract) {
                return $this->errorResponse('Contrato no encontrado.', 404);
            }

            return $this->successResponse($contract, 'Detalle del contrato obtenido correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/fleet-management/rental-contracts/{uuid}/close',
        summary: 'Cerrar contrato (finalizar o cancelar)',
        operationId: 'closeRentalContract',
        tags: ['ContratosAlquiler'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['status'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['finished', 'cancelled']),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contrato cerrado exitosamente.'),
        ]
    )]
    public function close(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:finished,cancelled',
            ]);

            $contract = $this->rentalContractService->closeContract($uuid, $validated['status']);

            return $this->successResponse($contract, 'Contrato cerrado exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/rental-installments/{uuid}',
        summary: 'Registrar pago o descuento en cuota',
        operationId: 'updateInstallmentPayment',
        tags: ['ContratosAlquiler'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'payment_amount', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'paid_amount', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'payment_date', type: 'string', format: 'date'),
                        new OA\Property(property: 'discount_amount', type: 'number', format: 'float', default: 0),
                        new OA\Property(property: 'notes', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cuota actualizada exitosamente.'),
        ]
    )]
    public function updateInstallmentPayment(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_amount' => 'required_without:paid_amount|nullable|numeric|min:0',
                'paid_amount' => 'required_without:payment_amount|nullable|numeric|min:0',
                'payment_date' => 'nullable|date',
                'discount_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ]);

            $installment = $this->rentalContractService->updatePayment($uuid, $validated);

            return $this->successResponse($installment, 'Pago registrado y cuotas recalculadas exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/rental-contracts/{uuid}/pdf',
        summary: 'Descargar contrato en PDF',
        operationId: 'downloadRentalContractPdf',
        tags: ['ContratosAlquiler'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF generado exitosamente.'),
            new OA\Response(response: 404, description: 'Contrato no encontrado.'),
        ]
    )]
    public function downloadPdf(string $uuid, PdfService $pdfService): Response|JsonResponse
    {
        try {
            $contract = $this->rentalContractService->getDetail($uuid);

            if (! $contract) {
                return $this->errorResponse('Contrato no encontrado.', 404);
            }

            $resultado = $pdfService->generateRentalContractPdf($contract);

            return $resultado['pdf']->download($resultado['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/rental-contracts/{uuid}/discounts',
        summary: 'Registrar descuento en la cuota vigente del contrato',
        operationId: 'storeRentalContractDiscount',
        tags: ['ContratosAlquiler'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['discount_date', 'concept', 'amount'],
                    properties: [
                        new OA\Property(property: 'discount_date', type: 'string', format: 'date'),
                        new OA\Property(property: 'concept', type: 'string', maxLength: 255),
                        new OA\Property(property: 'amount', type: 'number', format: 'float'),
                        new OA\Property(property: 'observations', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Descuento registrado exitosamente.'),
        ]
    )]
    public function storeDiscount(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'discount_date' => 'required|date',
                'concept' => 'required|string|max:255',
                'amount' => 'required|numeric|gt:0',
                'observations' => 'nullable|string|max:1000',
            ]);

            $discount = $this->rentalContractService->addDiscount($uuid, $validated);

            return $this->successResponse($discount, 'Descuento registrado y aplicado a la cuota vigente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/rental-contracts/discounts/{discountUuid}',
        summary: 'Eliminar descuento de la cuota vigente',
        operationId: 'destroyRentalContractDiscount',
        tags: ['ContratosAlquiler'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'discountUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Descuento eliminado exitosamente.'),
        ]
    )]
    public function destroyDiscount(string $discountUuid): JsonResponse
    {
        try {
            $this->rentalContractService->deleteDiscount($discountUuid);

            return $this->successResponse(null, 'Descuento eliminado y cuota recalculada.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
