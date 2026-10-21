<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreElectronicDocumentRequest;
use App\Models\ElectronicDocument;
use App\Services\Billing\ElectronicDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Documentos electrónicos genéricos ApiDian (fase 3): documento soporte y su
 * nota, equivalentes POS y su nota, nómina 102/103, eventos RADIAN, lote y zip.
 */
class ElectronicDocumentController extends Controller
{
    public function __construct(
        private readonly ElectronicDocumentService $documentos
    ) {}

    #[OA\Get(path: '/api/v1/billing/electronic-documents', summary: 'Listar documentos electrónicos', operationId: 'listElectronicDocuments', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function index(Request $request): JsonResponse
    {
        try {
            $pagina = $this->documentos->getPaginatedData(
                (int) $request->query('per_page', 15),
                (int) $request->query('page', 1),
                (string) $request->query('search', ''),
                $request->query('company_uuid')
            );

            return $this->successResponse($pagina, 'Documentos electrónicos recuperados con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/billing/electronic-documents/{uuid}', summary: 'Detalle de documento electrónico', operationId: 'showElectronicDocument', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function show(string $uuid): JsonResponse
    {
        try {
            $documento = $this->documentos->findByUuid($uuid);
            if (! $documento) {
                return $this->errorResponse('El documento electrónico solicitado no existe.', 404);
            }

            return $this->successResponse($documento, 'Detalle del documento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/electronic-documents', summary: 'Registrar documento electrónico (sin transmitir)', operationId: 'storeElectronicDocument', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function store(StoreElectronicDocumentRequest $request): JsonResponse
    {
        try {
            $datos = $request->validated();
            $datos['company_uuid'] = $datos['company_uuid'] ?? request()->attributes->get('current_company_uuid');
            if (! $datos['company_uuid']) {
                return $this->errorResponse('No se pudo resolver la empresa del contexto.', 422);
            }

            $documento = $this->documentos->inscribir($datos);

            return $this->successResponse($documento, 'Documento electrónico registrado; pendiente de emitir.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/billing/electronic-documents/{uuid}', summary: 'Actualizar documento no firmado', operationId: 'updateElectronicDocument', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function update(StoreElectronicDocumentRequest $request, string $uuid): JsonResponse
    {
        try {
            try {
                $actualizado = $this->documentos->update($uuid, $request->validated());
            } catch (\RuntimeException $e) {
                return $this->errorResponse($e->getMessage(), 422);
            }
            if (! $actualizado) {
                return $this->errorResponse('El documento electrónico que desea actualizar no existe.', 404);
            }

            return $this->successResponse($this->documentos->findByUuid($uuid), 'Documento electrónico actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/electronic-documents/{uuid}/emitir', summary: 'Firmar y transmitir el documento', operationId: 'emitElectronicDocument', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function emitir(string $uuid): JsonResponse
    {
        try {
            $documento = $this->documentos->emitir($uuid);

            return $this->successResponse($documento, $documento->electronic_status === 'ACEPTADA_DIAN'
                ? 'Documento electrónico aceptado por la DIAN.'
                : 'Documento electrónico procesado sin transmisión a la DIAN.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/billing/electronic-documents/{uuid}/estado', summary: 'Estado DIAN del documento', operationId: 'electronicDocumentStatus', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function estado(string $uuid): JsonResponse
    {
        try {
            return $this->successResponse($this->documentos->estado($uuid), 'Estado del documento electrónico consultado con éxito.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(path: '/api/v1/billing/electronic-documents/{uuid}', summary: 'Eliminar documento no firmado', operationId: 'destroyElectronicDocument', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            try {
                $borrado = $this->documentos->delete($uuid);
            } catch (\RuntimeException $e) {
                return $this->errorResponse($e->getMessage(), 422);
            }
            if (! $borrado) {
                return $this->errorResponse('El documento electrónico que desea eliminar no existe.', 404);
            }

            return $this->successResponse(null, 'Documento electrónico eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
