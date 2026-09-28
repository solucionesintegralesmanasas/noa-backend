<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Exports\VehicleReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\VehicleReportRequest;
use App\Services\Pdf\PdfService;
use App\Services\Reports\VehicleReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;

/**
 * Controlador del módulo de reportes: reporte de vehículos.
 */
class VehicleReportController extends Controller
{
    public function __construct(
        private readonly VehicleReportService $reportService
    ) {}

    #[OA\Get(
        path: '/api/v1/reports/vehicles',
        summary: 'Reporte paginado de vehículos con un filtro activo',
        operationId: 'reportVehicles',
        tags: ['Reportes'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'filter_type', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['affiliate', 'operation_card', 'document', 'project', 'maintenance', 'driver', 'agreement'])),
            new OA\Parameter(name: 'third_party_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'project_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte generado con éxito.'),
            new OA\Response(response: 422, description: 'Filtros inválidos.'),
        ]
    )]
    public function index(VehicleReportRequest $request): JsonResponse
    {
        try {
            $filtros = $request->reportFilters($request->attributes->get('current_company_uuid'));
            $perPage = (int) ($request->query('per_page', 15));
            $page = (int) ($request->query('page', 1));
            $data = $this->reportService->paginateReport($filtros, $perPage, $page);

            return $this->successResponse($data, 'Reporte de vehículos recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/reports/vehicles/catalogs/drivers',
        summary: 'Conductores con asignación activa (solo personas naturales)',
        operationId: 'reportVehiclesDrivers',
        tags: ['Reportes'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Catálogo recuperado.')]
    )]
    public function drivers(Request $request): JsonResponse
    {
        try {
            $conductores = $this->reportService->distinctDrivers($request->attributes->get('current_company_uuid'));
            $data = $conductores->map(fn ($t) => [
                'label' => trim(($t->first_name ?? '').' '.($t->last_name ?? '')) ?: ($t->document_number ?? 'Sin nombre'),
                'value' => $t->uuid,
            ])->values();

            return $this->successResponse($data, 'Conductores recuperados con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/reports/vehicles/catalogs/affiliated-companies',
        summary: 'Empresas distintas con tarjeta de operación (selector del filtro)',
        operationId: 'reportVehiclesAffiliatedCompanies',
        tags: ['Reportes'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Catálogo recuperado.')]
    )]
    public function affiliatedCompanies(Request $request): JsonResponse
    {
        try {
            $nombres = $this->reportService->distinctAffiliatedCompanies($request->attributes->get('current_company_uuid'));
            $data = $nombres->map(fn ($n) => ['label' => $n, 'value' => $n])->values();

            return $this->successResponse($data, 'Empresas con tarjeta de operación recuperadas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/reports/vehicles/catalogs/agreements',
        summary: 'Convenios distintos (selector del filtro)',
        operationId: 'reportVehiclesAgreements',
        tags: ['Reportes'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Catálogo recuperado.')]
    )]
    public function agreements(Request $request): JsonResponse
    {
        try {
            $nombres = $this->reportService->distinctAgreementNames($request->attributes->get('current_company_uuid'));
            $data = $nombres->map(fn ($n) => ['label' => $n, 'value' => $n])->values();

            return $this->successResponse($data, 'Convenios recuperados con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/reports/vehicles/excel',
        summary: 'Exportar reporte de vehículos a Excel (tope 1000 filas)',
        operationId: 'reportVehiclesExcel',
        tags: ['Reportes'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel generado.'),
            new OA\Response(response: 422, description: 'Filtros inválidos.'),
        ]
    )]
    public function excel(VehicleReportRequest $request)
    {
        try {
            $filtros = $request->reportFilters($request->attributes->get('current_company_uuid'));

            return Excel::download(
                new VehicleReportExport($filtros, $this->reportService),
                'Reporte_Vehiculos_'.now()->format('Ymd_His').'.xlsx'
            );
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/reports/vehicles/pdf',
        summary: 'Exportar reporte de vehículos a PDF (tope 1000 filas)',
        operationId: 'reportVehiclesPdf',
        tags: ['Reportes'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado.'),
            new OA\Response(response: 422, description: 'Filtros inválidos.'),
        ]
    )]
    public function pdf(VehicleReportRequest $request, PdfService $pdfService)
    {
        try {
            $filtros = $request->reportFilters($request->attributes->get('current_company_uuid'));
            $filas = $this->reportService->allForExport($filtros);
            $result = $pdfService->generateVehicleReportPdf($filas, $filtros);

            return $result['pdf']->download($result['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
