<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreInvoiceRequest;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Http\Requests\Billing\StoreQuotationRequest;
use App\Http\Requests\Billing\UpdateCommercialInvoiceRequest;
use App\Services\Billing\CommercialInvoiceService;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documentos comerciales: cotizaciones (fase 1) y facturas internas (fase 2).
 */
class CommercialInvoiceController extends Controller
{
    public function __construct(
        private readonly CommercialInvoiceService $documentos
    ) {}

    #[OA\Get(path: '/api/v1/billing/commercial-invoices', summary: 'Listar documentos comerciales', operationId: 'listCommercialInvoices', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = array_filter([
                'company_uuid' => $request->query('company_uuid'),
                'invoice_type' => $request->query('invoice_type'),
                'status' => $request->query('status'),
                'third_party_uuid' => $request->query('third_party_uuid'),
            ]);

            $pagina = $this->documentos->search(
                $filtros,
                ['*'],
                (int) $request->query('per_page', 15),
                ['customer', 'project']
            );

            return $this->successResponse($pagina, 'Documentos recuperados con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/billing/commercial-invoices/{uuid}', summary: 'Detalle de documento comercial', operationId: 'showCommercialInvoice', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function show(string $uuid): JsonResponse
    {
        try {
            $registro = $this->documentos->getDetalle($uuid);
            if (! $registro) {
                return $this->errorResponse('El documento solicitado no existe.', 404);
            }

            return $this->successResponse($registro, 'Detalle recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/commercial-invoices/quotations', summary: 'Crear cotización', operationId: 'storeQuotation', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function storeQuotation(StoreQuotationRequest $request): JsonResponse
    {
        try {
            $registro = $this->documentos->crearCotizacion($request->validated());

            return $this->successResponse($registro, 'Cotización creada con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/commercial-invoices/invoices', summary: 'Crear factura interna (sin FE)', operationId: 'storeInvoice', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function storeInvoice(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            $registro = $this->documentos->crearFactura($request->validated());

            return $this->successResponse($registro, 'Factura interna creada con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/billing/commercial-invoices/{uuid}', summary: 'Actualizar documento en borrador', operationId: 'updateCommercialInvoice', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function update(UpdateCommercialInvoiceRequest $request, string $uuid): JsonResponse
    {
        try {
            $registro = $this->documentos->actualizarDocumento($uuid, $request->validated());

            return $this->successResponse($registro, 'Documento actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(path: '/api/v1/billing/commercial-invoices/{uuid}/estado', summary: 'Cambiar estado comercial', operationId: 'changeCommercialInvoiceStatus', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function cambiarEstado(Request $request, string $uuid): JsonResponse
    {
        try {
            $estado = (string) $request->input('status', '');
            if ($estado === '') {
                return $this->errorResponse('El estado es obligatorio.', 422);
            }

            $registro = $this->documentos->cambiarEstado($uuid, $estado);

            return $this->successResponse($registro, 'Estado actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/commercial-invoices/{uuid}/convertir', summary: 'Convertir cotización aprobada en factura', operationId: 'convertQuotation', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function convertir(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'billing_resolution_uuid' => ['required', 'uuid', 'exists:billing_resolutions,uuid'],
                'issue_date' => ['required', 'date'],
                'due_date' => ['nullable', 'date'],
                'project_uuid' => ['nullable', 'uuid', 'exists:projects,uuid'],
                'payment_method_uuid' => ['nullable', 'uuid', 'exists:payment_methods,uuid'],
            ]);

            $registro = $this->documentos->convertirCotizacion($uuid, $request->only([
                'billing_resolution_uuid', 'issue_date', 'due_date', 'project_uuid', 'payment_method_uuid',
            ]));

            return $this->successResponse($registro, 'Cotización convertida en factura con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/commercial-invoices/{uuid}/pagos', summary: 'Registrar abono a factura', operationId: 'storeInvoicePayment', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function pago(StorePaymentRequest $request, string $uuid): JsonResponse
    {
        try {
            $registro = $this->documentos->registrarPago($uuid, $request->validated());

            return $this->successResponse($registro, 'Pago registrado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/commercial-invoices/{uuid}/anular', summary: 'Anular factura o rechazar cotización', operationId: 'voidCommercialInvoice', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function anular(string $uuid): JsonResponse
    {
        try {
            $registro = $this->documentos->anular($uuid);

            return $this->successResponse($registro, 'Documento anulado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/billing/commercial-invoices/{uuid}/pdf', summary: 'Descargar PDF del documento', operationId: 'pdfCommercialInvoice', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function pdf(string $uuid): Response|JsonResponse
    {
        try {
            $registro = $this->documentos->getDetalle($uuid);
            if (! $registro) {
                return $this->errorResponse('El documento solicitado no existe.', 404);
            }

            $empresa = Company::where('uuid', $registro->company_uuid)->first();
            $pdf = Pdf::loadView('pdf.commercial-invoice', ['documento' => $registro, 'empresa' => $empresa]);

            return $pdf->download("documento-{$registro->invoice_number}.pdf");
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(path: '/api/v1/billing/commercial-invoices/{uuid}', summary: 'Eliminar documento en borrador', operationId: 'destroyCommercialInvoice', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            if (! $this->documentos->delete($uuid)) {
                return $this->errorResponse('Solo un documento en BORRADOR se puede eliminar.', 409);
            }

            return $this->successResponse(null, 'Documento eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
