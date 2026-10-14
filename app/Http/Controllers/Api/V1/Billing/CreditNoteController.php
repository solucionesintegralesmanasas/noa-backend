<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreNoteRequest;
use App\Services\Billing\CreditNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Notas crédito internas (sin transmisión DIAN por ahora). */
class CreditNoteController extends Controller
{
    public function __construct(
        private readonly CreditNoteService $notas
    ) {}

    #[OA\Get(path: '/api/v1/billing/credit-notes', summary: 'Listar notas crédito', operationId: 'listCreditNotes', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = array_filter([
                'company_uuid' => $request->query('company_uuid'),
                'status' => $request->query('status'),
            ]);

            $pagina = $this->notas->search($filtros, ['*'], (int) $request->query('per_page', 15), ['invoice']);

            return $this->successResponse($pagina, 'Notas crédito recuperadas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/billing/credit-notes/{uuid}', summary: 'Detalle de nota crédito', operationId: 'showCreditNote', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function show(string $uuid): JsonResponse
    {
        try {
            $registro = $this->notas->detalle($uuid);
            if (! $registro) {
                return $this->errorResponse('La nota solicitada no existe.', 404);
            }

            return $this->successResponse($registro, 'Detalle recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/credit-notes', summary: 'Crear nota crédito', operationId: 'storeCreditNote', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function store(StoreNoteRequest $request): JsonResponse
    {
        try {
            $registro = $this->notas->crearNota($request->validated());

            return $this->successResponse($registro, 'Nota crédito creada con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/credit-notes/{uuid}/emitir', summary: 'Emitir nota crédito y ajustar factura', operationId: 'issueCreditNote', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function emitir(string $uuid): JsonResponse
    {
        try {
            $registro = $this->notas->emitirNota($uuid);

            return $this->successResponse($registro, 'Nota crédito emitida con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/credit-notes/{uuid}/anular', summary: 'Anular nota crédito en borrador', operationId: 'voidCreditNote', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function anular(string $uuid): JsonResponse
    {
        try {
            $registro = $this->notas->anularNota($uuid);

            return $this->successResponse($registro, 'Nota crédito anulada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
