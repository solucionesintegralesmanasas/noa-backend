<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\Reports\VehicleReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Exportación Excel del reporte de vehículos (tope 1000 filas).
 */
class VehicleReportExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly array $filtros,
        private readonly ?VehicleReportService $reportService = null
    ) {}

    public function headings(): array
    {
        return [
            'Placa', 'Modelo', 'Clase de vehículo', 'Tipo de carrocería', 'Modalidad',
            'SOAT vence', 'RCC vence', 'RCE vence', 'RTM vence',
            'N.º tarjeta operación', 'Tarjeta operación vence',
            'Convenio', 'Afiliado', 'Conductor', 'Proyecto',
        ];
    }

    public function collection(): Collection
    {
        $service = $this->reportService ?? app(VehicleReportService::class);

        return $service->allForExport($this->filtros)->map(fn (array $row) => [
            $row['vehicle_license_plate'],
            $row['model'],
            $row['vehicle_class'],
            $row['body_type'],
            $row['modality_label'],
            $row['soat_expiry'],
            $row['rcc_expiry'],
            $row['rce_expiry'],
            $row['rtm_expiry'],
            $row['operation_card_number'],
            $row['operation_card_expiry'],
            $row['agreement_name'],
            $row['affiliate_name'],
            $row['driver_name'],
            $row['project_name'],
        ]);
    }
}
