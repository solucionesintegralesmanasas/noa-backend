<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Vehicle;
use App\Services\ActorScope\AlcanceDelActor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Servicio del reporte de actividad vehicular.
 *
 * Consolida cinco módulos (inspecciones, mantenimiento, combustible,
 * solicitudes de tanqueo y hojas de control) en una fila por evento.
 * No hereda BaseService: es solo construcción de consultas sobre
 * DB::table, sin CRUD ni modelo único (decisión de diseño del reporte).
 * Integra AlcanceDelActor para aislar por empresa, sucursal, afiliado y conductor.
 */
final class VehicleActivityReportService
{
    public function __construct(
        private readonly AlcanceDelActor $alcance
    ) {}

    /**
     * Página del reporte, ordenado por fecha descendente.
     */
    public function paginateReport(array $filtros, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, min(100, $perPage));

        return $this->consultaConsolidada($filtros)
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    /**
     * Todas las filas para exportar (tope por defecto 1000).
     */
    public function allForExport(array $filtros, int $limit = 1000): Collection
    {
        return $this->consultaConsolidada($filtros)->limit($limit)->get();
    }

    /**
     * Unión de los brazos + nombre de proyecto + filtros opcionales + orden.
     */
    private function consultaConsolidada(array $filtros): Builder
    {
        $companyUuid = $filtros['company_uuid'] ?? $this->alcance->companyUuid();

        // Fail-Closed: sin empresa, respuesta vacía garantizada.
        if (! $companyUuid && ! $this->alcance->esSuperadmin()) {
            return DB::query()->fromRaw('(select 1 where 1 = 0) as actividad');
        }

        $union = $this->brazoInspeccion($filtros);
        $union->unionAll($this->brazoMantenimiento($filtros));
        $union->unionAll($this->brazoCombustible($filtros));
        $union->unionAll($this->brazoTanqueo($filtros));
        $union->unionAll($this->brazoHojaInterna($filtros));
        $union->unionAll($this->brazoHojaSubcontratada($filtros));

        $query = DB::query()->fromSub($union, 'actividad')
            ->leftJoin('projects', 'projects.uuid', '=', 'actividad.project_uuid')
            ->select('actividad.*', 'projects.project_name');

        // Aislamiento del actor (SPEC-013): si el usuario está restringido
        // (sucursal, afiliado, conductor), acotar los vehículos resultantes.
        if ($this->alcance->esSucursalRestringida() || $this->alcance->esAfiliado() || $this->alcance->esConductor()) {
            $vehiculosPermitidosSub = $this->alcance->aplicarAVehiculos(
                Vehicle::query()->select('vehicles.uuid'),
                $companyUuid
            )->toBase();

            $query->whereIn('actividad.vehicle_uuid', $vehiculosPermitidosSub);
        }

        if (! empty($filtros['vehicle_uuid'])) {
            $query->where('actividad.vehicle_uuid', $filtros['vehicle_uuid']);
        }

        if (! empty($filtros['project_uuid'])) {
            $query->where('actividad.project_uuid', $filtros['project_uuid']);
        }

        // Desempate por uuid: la paginación no repite ni omite filas con igual fecha.
        return $query->orderByDesc('actividad.event_date')->orderBy('actividad.event_uuid');
    }

    /**
     * Proyecto activo más reciente del vehículo (pivote) como subconsulta
     * escalar: evita duplicar filas en la unión al ser 1:1 por construcción.
     */
    private function proyectoDe(string $columnaVehiculo): string
    {
        return '(select pdv.project_uuid from project_driver_vehicles pdv'
            .' where pdv.vehicle_uuid = '.$columnaVehiculo
            .' and pdv.is_active = 1 order by pdv.id limit 1)';
    }

    /**
     * Exclusión de la hoja padre multía cuando tiene hijas: cada día
     * aparece una sola vez (las hijas llevan su propio service_date).
     */
    private const EXCLUIR_PADRE_MULTIDIA = '(s.parent_uuid is not null or not exists '
        .'(select 1 from service_delivery_control_sheet c where c.parent_uuid = s.uuid))';

    private function brazoInspeccion(array $f): Builder
    {
        return DB::table('vehicle_inspections as vi')
            ->join('vehicles as v', 'v.uuid', '=', 'vi.vehicle_uuid')
            ->leftJoin('third_parties as tp', 'tp.uuid', '=', 'vi.driver_uuid')
            ->where('vi.company_uuid', $f['company_uuid'])
            ->whereBetween('vi.inspection_date', [$f['fecha_desde'], $f['fecha_hasta']])
            ->selectRaw(
                "vi.inspection_date as event_date, vi.uuid as event_uuid, vi.vehicle_uuid,
                 v.vehicle_license_plate, {$this->proyectoDe('vi.vehicle_uuid')} as project_uuid,
                 'INSPECCION' as event_type,
                 trim(concat(tp.first_name, ' ', tp.last_name)) as driver_name,
                 concat('Km ', vi.mileage) as status_value"
            );
    }

    private function brazoMantenimiento(array $f): Builder
    {
        return DB::table('maintenance as m')
            ->join('vehicles as v', 'v.uuid', '=', 'm.vehicle_uuid')
            ->where('m.company_uuid', $f['company_uuid'])
            ->whereBetween('m.maintenance_date', [$f['fecha_desde'], $f['fecha_hasta']])
            ->selectRaw(
                "m.maintenance_date as event_date, m.uuid as event_uuid, m.vehicle_uuid,
                 v.vehicle_license_plate, {$this->proyectoDe('m.vehicle_uuid')} as project_uuid,
                 'MANTENIMIENTO' as event_type,
                 null as driver_name,
                 m.status as status_value"
            );
    }

    private function brazoCombustible(array $f): Builder
    {
        return DB::table('fuel_logs as fl')
            ->join('vehicles as v', 'v.uuid', '=', 'fl.vehicle_uuid')
            ->leftJoin('third_parties as tp', 'tp.uuid', '=', 'fl.driver_uuid')
            ->where('fl.company_uuid', $f['company_uuid'])
            ->whereBetween('fl.date', [$f['fecha_desde'], $f['fecha_hasta']])
            ->selectRaw(
                "fl.date as event_date, fl.uuid as event_uuid, fl.vehicle_uuid,
                 v.vehicle_license_plate, {$this->proyectoDe('fl.vehicle_uuid')} as project_uuid,
                 'COMBUSTIBLE' as event_type,
                 trim(concat(tp.first_name, ' ', tp.last_name)) as driver_name,
                 round(fl.total_spent, 2) as status_value"
            );
    }

    private function brazoTanqueo(array $f): Builder
    {
        return DB::table('fuel_requests as fr')
            ->join('vehicles as v', 'v.uuid', '=', 'fr.vehicle_uuid')
            ->leftJoin('third_parties as tp', 'tp.uuid', '=', 'fr.driver_uuid')
            ->where('fr.company_uuid', $f['company_uuid'])
            ->whereBetween('fr.requested_at', [$f['fecha_desde'].' 00:00:00', $f['fecha_hasta'].' 23:59:59'])
            ->selectRaw(
                "DATE(fr.requested_at) as event_date, fr.uuid as event_uuid, fr.vehicle_uuid,
                 v.vehicle_license_plate, {$this->proyectoDe('fr.vehicle_uuid')} as project_uuid,
                 'TANQUEO' as event_type,
                 trim(concat(tp.first_name, ' ', tp.last_name)) as driver_name,
                 fr.status as status_value"
            );
    }

    private function brazoHojaInterna(array $f): Builder
    {
        return DB::table('service_delivery_control_sheet as s')
            ->join('service_internal_controls as ic', 'ic.service_delivery_control_sheet_uuid', '=', 's.uuid')
            ->join('vehicles as v', 'v.uuid', '=', 'ic.vehicle_uuid')
            ->leftJoin('third_parties as tp', 'tp.uuid', '=', 'ic.third_party_uuid')
            ->where('s.company_uuid', $f['company_uuid'])
            ->whereBetween('s.service_date', [$f['fecha_desde'], $f['fecha_hasta']])
            ->whereRaw(self::EXCLUIR_PADRE_MULTIDIA)
            // Una hoja solo se cuenta por un brazo: si tiene control subcontratado va en el otro.
            ->whereRaw('not exists (select 1 from service_internal_controls_subcontracted sc '
                .'where sc.service_delivery_control_sheet_uuid = s.uuid)')
            ->selectRaw(
                "s.service_date as event_date, s.uuid as event_uuid, ic.vehicle_uuid,
                 v.vehicle_license_plate, s.project_uuid as project_uuid,
                 'HOJA_CONTROL' as event_type,
                 coalesce(trim(concat(tp.first_name, ' ', tp.last_name)), s.official_name_and_surname) as driver_name,
                 null as status_value"
            );
    }

    private function brazoHojaSubcontratada(array $f): Builder
    {
        return DB::table('service_delivery_control_sheet as s')
            ->join('service_internal_controls_subcontracted as sc', 'sc.service_delivery_control_sheet_uuid', '=', 's.uuid')
            ->leftJoin('vehicles as v', function ($join) {
                $join->on('v.vehicle_license_plate', '=', 'sc.vehicle_license_plate')
                    ->whereColumn('v.company_uuid', '=', 's.company_uuid');
            })
            ->where('s.company_uuid', $f['company_uuid'])
            ->whereBetween('s.service_date', [$f['fecha_desde'], $f['fecha_hasta']])
            ->whereRaw(self::EXCLUIR_PADRE_MULTIDIA)
            ->whereRaw('not exists (select 1 from service_internal_controls ic '
                .'where ic.service_delivery_control_sheet_uuid = s.uuid)')
            ->selectRaw(
                "s.service_date as event_date, s.uuid as event_uuid, v.uuid as vehicle_uuid,
                 coalesce(v.vehicle_license_plate, sc.vehicle_license_plate) as vehicle_license_plate,
                 s.project_uuid as project_uuid,
                 'HOJA_CONTROL' as event_type,
                 sc.driver_name_and_surname as driver_name,
                 null as status_value"
            );
    }
}
