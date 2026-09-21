<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Exportación Excel de Control de Servicio, solo días cerrados.
 */
class ServiceControlSheetExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly array $filtros) {}

    public function headings(): array
    {
        return [
            'Fecha', 'Ruta diaria', 'Hora inicio', 'Descanso inicio', 'Descanso fin',
            'Hora final', 'Total horas', 'Km inicial', 'Km final', 'Km total',
            'Conductor', 'Placa', 'Proyecto', 'Estado', 'Detalle cierre',
        ];
    }

    public function collection(): Collection
    {
        $f = $this->filtros;
        $query = ServiceDeliveryControlSheet::query()
            ->where('is_active', false)
            ->with(['project', 'routes', 'internalControl.vehicle'])
            ->orderBy('service_date', 'asc');

        if (! empty($f['company_uuid'])) {
            $query->where('company_uuid', $f['company_uuid']);
        }
        if (! empty($f['fecha_desde'])) {
            $query->whereDate('service_date', '>=', $f['fecha_desde']);
        }
        if (! empty($f['fecha_hasta'])) {
            $query->whereDate('service_date', '<=', $f['fecha_hasta']);
        }
        if (! empty($f['fecha'])) {
            $query->whereDate('service_date', $f['fecha']);
        }
        if (! empty($f['year'])) {
            $query->whereYear('service_date', (int) $f['year']);
        }
        if (! empty($f['month'])) {
            $query->whereMonth('service_date', (int) $f['month']);
        }
        if (! empty($f['vehicle_uuid'])) {
            $placa = Vehicle::where('uuid', $f['vehicle_uuid'])->value('vehicle_license_plate');
            $query->where(function ($q) use ($f, $placa) {
                $q->whereHas('internalControl', fn ($qq) => $qq->where('vehicle_uuid', $f['vehicle_uuid']));
                if ($placa) {
                    $q->orWhereHas('subcontractedControl', fn ($qq) => $qq->where('vehicle_license_plate', $placa));
                }
            });
        }
        if (! empty($f['third_party_uuid'])) {
            $query->whereHas('internalControl', fn ($q) => $q->where('third_party_uuid', $f['third_party_uuid']));
        }

        $filas = [];
        foreach ($query->limit(1000)->get() as $sheet) {
            $fecha = Carbon::parse($sheet->service_date)->format('d/m/Y');
            $rutas = $sheet->routes->where('is_active', true);
            if ($rutas->isEmpty()) {
                $filas[] = [
                    $fecha, 'VEHÍCULO EN DISPONIBILIDAD',
                    $sheet->start_time?->format('H:i'), '', '',
                    $sheet->end_time?->format('H:i'), $sheet->total_hours?->format('H:i'),
                    $sheet->starting_kilometer, $sheet->ending_kilometer, '',
                    $sheet->driver_name, $sheet->vehicle_license_plate,
                    $sheet->project?->project_name, 'FINALIZADA', '',
                ];
                continue;
            }
            foreach ($rutas as $route) {
                $filas[] = [
                    $fecha, trim(($route->origin ?? '').' - '.($route->destination ?? ''), ' -'),
                    $sheet->start_time?->format('H:i'), '', '',
                    $route->end_time ? Carbon::parse($route->end_time)->format('H:i') : $sheet->end_time?->format('H:i'),
                    $sheet->total_hours?->format('H:i'),
                    $sheet->starting_kilometer, $route->ending_kilometer ?? $sheet->ending_kilometer, '',
                    $sheet->driver_name, $sheet->vehicle_license_plate,
                    $sheet->project?->project_name, 'FINALIZADA',
                    trim(($route->funcionario_cc ?? '').' '.($route->funcionario_nombre ?? '')),
                ];
            }
        }

        return new Collection($filas);
    }
}
