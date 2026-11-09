<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\Reports\VehicleActivityReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Exportación Excel del reporte de actividad vehicular (tope 1000 filas).
 */
class VehicleActivityReportExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly array $filtros,
        private readonly ?VehicleActivityReportService $reportService = null
    ) {}

    public function headings(): array
    {
        return ['Fecha', 'Vehículo', 'Proyecto', 'Tipo', 'Conductor', 'Estado/Valor'];
    }

    public function collection(): Collection
    {
        $service = $this->reportService ?? app(VehicleActivityReportService::class);

        // DB::table entrega filas como stdClass, no como array.
        return $service->allForExport($this->filtros)->map(fn ($fila) => [
            $fila->event_date,
            $fila->vehicle_license_plate,
            $fila->project_name,
            $fila->event_type,
            $fila->driver_name,
            $fila->status_value,
        ]);
    }
}
