<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\ListarMovimientosCapacidadRequest;
use App\Services\Fleet\CapacityMovementService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Historial del libro mayor de cupos de capacidad transportadora.
 *
 * Solo lectura: los movimientos los escribe el motor (`CapacityMovementService`)
 * desde la radicación, las tarjetas de operación y los ajustes manuales.
 */
class CapacityMovementController extends Controller
{
    public function __construct(private readonly CapacityMovementService $movimientos) {}

    #[OA\Get(
        path: '/api/v1/administration/capacity-movements',
        summary: 'Listar movimientos de cupos',
        operationId: 'listCapacityMovements',
        tags: ['CapacidadTransporte'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado recuperado.')]
    )]
    public function index(ListarMovimientosCapacidadRequest $request): JsonResponse
    {
        try {
            $datos = $request->validated();

            $data = $this->movimientos->listarMovimientos([
                'company_uuid' => $datos['company_uuid'] ?? null,
                'movement_type' => $datos['movement_type'] ?? null,
                'vehicle_uuid' => $datos['vehicle_uuid'] ?? null,
                'desde' => $datos['desde'] ?? null,
                'hasta' => $datos['hasta'] ?? null,
            ], (int) ($datos['per_page'] ?? 15), (int) ($datos['page'] ?? 1));

            return $this->successResponse($data, 'Movimientos de cupos.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/capacity-movements/conciliacion',
        summary: 'Conciliar saldo de cupos contra el libro mayor',
        operationId: 'conciliateCapacityMovements',
        tags: ['CapacidadTransporte'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Conciliación calculada.')]
    )]
    public function conciliacion(ListarMovimientosCapacidadRequest $request): JsonResponse
    {
        try {
            $companyUuid = $request->validated()['company_uuid'] ?? null;
            if (! is_string($companyUuid) || $companyUuid === '') {
                return $this->errorResponse('No se pudo determinar la empresa para conciliar.', 422);
            }

            return $this->successResponse(
                ['capacidades' => $this->movimientos->conciliacionDeEmpresa($companyUuid)],
                'Conciliación de cupos.'
            );
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
