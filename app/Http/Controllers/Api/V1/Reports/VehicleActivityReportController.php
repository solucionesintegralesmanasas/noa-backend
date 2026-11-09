<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Exports\VehicleActivityReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\VehicleActivityReportRequest;
use App\Services\Reports\VehicleActivityReportService;
use App\Services\Pdf\PdfService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;

/**
 * Controlador del módulo de reportes: reporte de actividad vehicular.
 */
class VehicleActivityReportController extends Controller
{
    public function __construct(
        private readonly VehicleActivityReportService $reportService
    ) {}

    #[OA\Get(
        path: '/api/v1/reports/vehicle-activity',
        summary: 'Reporte consolidado de actividad vehicular por rango de fechas',
        operationId: 'reportVehicleActivity',
        tags: ['Reportes'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fecha_desde', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'fecha_hasta', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'project_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte generado con éxito.'),
            new OA\Response(response: 422, description: 'Filtros inválidos.'),
        ]
    )]
    public function index(VehicleActivityReportRequest $request): JsonResponse
    {
        try {
            $filtros = $request->reportFilters($request->attributes->get('current_company_uuid'));
            $perPage = (int) ($request->query('per_page', 10));
            $page = (int) ($request->query('page', 1));
            $data = $this->reportService->paginateReport($filtros, $perPage, $page);

            return $this->successResponse($data, 'Reporte de actividad vehicular recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/reports/vehicle-activity/excel',
        summary: 'Exportar reporte de actividad vehicular a Excel (tope 1000 filas)',
        operationId: 'reportVehicleActivityExcel',
        tags: ['Reportes'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel generado.'),
            new OA\Response(response: 422, description: 'Filtros inválidos.'),
        ]
    )]
    public function excel(VehicleActivityReportRequest $request)
    {
        try {
            $filtros = $request->reportFilters($request->attributes->get('current_company_uuid'));

            return Excel::download(
                new VehicleActivityReportExport($filtros, $this->reportService),
                'Reporte_Actividad_Vehicular_'.now()->format('Ymd_His').'.xlsx'
            );
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/reports/vehicle-activity/pdf',
        summary: 'Exportar reporte de actividad vehicular a PDF (tope 1000 filas)',
        operationId: 'reportVehicleActivityPdf',
        tags: ['Reportes'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado.'),
            new OA\Response(response: 422, description: 'Filtros inválidos.'),
        ]
    )]
    public function pdf(VehicleActivityReportRequest $request, PdfService $pdfService)
    {
        try {
            $filtros = $request->reportFilters($request->attributes->get('current_company_uuid'));
            $filas = $this->reportService->allForExport($filtros);
            // Empresa precargada para la presentación del PDF (membrete/fondo/limpio):
            // el render no consulta por su cuenta.
            if (! empty($filtros['company_uuid'])) {
                $filtros['company'] = \App\Models\Company::where('uuid', $filtros['company_uuid'])->first();
            }
            $result = $pdfService->generateVehicleActivityReportPdf($filas, $filtros);

            return $result['pdf']->download($result['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
