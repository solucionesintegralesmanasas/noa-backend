<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreNoteRequest;
use App\Services\Billing\DebitNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Notas débito internas (sin transmisión DIAN por ahora). */
class DebitNoteController extends Controller
{
    public function __construct(
        private readonly DebitNoteService $notas
    ) {}

    #[OA\Get(path: '/api/v1/billing/debit-notes', summary: 'Listar notas débito', operationId: 'listDebitNotes', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = array_filter([
                'company_uuid' => $request->query('company_uuid'),
                'status' => $request->query('status'),
            ]);

            $pagina = $this->notas->search($filtros, ['*'], (int) $request->query('per_page', 15), ['invoice']);

            return $this->successResponse($pagina, 'Notas débito recuperadas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/billing/debit-notes/{uuid}', summary: 'Detalle de nota débito', operationId: 'showDebitNote', tags: ['Facturación'], security: [['bearerAuth' => []]])]
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

    #[OA\Post(path: '/api/v1/billing/debit-notes', summary: 'Crear nota débito', operationId: 'storeDebitNote', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function store(StoreNoteRequest $request): JsonResponse
    {
        try {
            $registro = $this->notas->crearNota($request->validated());

            return $this->successResponse($registro, 'Nota débito creada con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/debit-notes/{uuid}/emitir', summary: 'Emitir nota débito y ajustar factura', operationId: 'issueDebitNote', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function emitir(string $uuid): JsonResponse
    {
        try {
            $registro = $this->notas->emitirNota($uuid);

            return $this->successResponse($registro, 'Nota débito emitida con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/billing/debit-notes/{uuid}/anular', summary: 'Anular nota débito en borrador', operationId: 'voidDebitNote', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function anular(string $uuid): JsonResponse
    {
        try {
            $registro = $this->notas->anularNota($uuid);

            return $this->successResponse($registro, 'Nota débito anulada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
