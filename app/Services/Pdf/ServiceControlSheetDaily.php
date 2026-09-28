<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Signature;
use App\Models\Vehicle;
use App\Services\ServiceDeliveryControlSheet\PcpEvidence;
use App\Services\Tracking\LocationHistoryService;
use App\Utils\Logger;
use Carbon\Carbon;

/**
 * Composición del reporte diario: mapa del recorrido y bandas de evidencia.
 *
 * Reúne lo que solo existe en el generador diario (el mensual y el filtrado
 * no tienen mapa ni bandas) con dependencias declaradas en el constructor,
 * en vez de resolverlas con app() dentro del método.
 */
final class ServiceControlSheetDaily
{
    public function __construct(
        private readonly RouteMapService $mapas,
        private readonly LocationHistoryService $historial,
        private readonly PcpEvidence $evidencia,
    ) {}

    /**
     * Mapa del recorrido GPS del día (vehículo + proyecto + fecha).
     * Prioridad: captura pegada desde el frontend; si no hay, mapa automático.
     * Nunca bloquea el PDF: si no hay puntos o falla la red, se muestra mensaje o PNG de respaldo.
     *
     * @return array{0: array, 1: ?string, 2: bool} [$mapaRecorrido, $mapaFecha, $mapaEsCaptura]
     */
    public function generarMapa($sheet, iterable $hojasDias, ?string $placa): array
    {
        $mapaRecorrido = ['imagen_base64' => null, 'mime' => 'image/png', 'sin_datos' => true, 'stats' => ['total_puntos' => 0, 'distancia_km' => 0, 'hora_inicio' => null, 'hora_fin' => null]];
        $mapaFecha = null;
        $mapaEsCaptura = false;
        try {
            $captura = $sheet->getFirstMedia('ROUTE_MAP');
            if ($captura && $captura->getPath() && file_exists($captura->getPath())) {
                $binCaptura = file_get_contents($captura->getPath());
                $infoCaptura = @getimagesizefromstring($binCaptura);
                if ($binCaptura && $infoCaptura && in_array($infoCaptura['mime'], ['image/png', 'image/jpeg'], true)) {
                    $mapaRecorrido = [
                        'imagen_base64' => base64_encode($binCaptura),
                        'mime' => $infoCaptura['mime'],
                        'sin_datos' => false,
                        'stats' => $this->mapas->calcularStats(
                            $this->historial->getVehicleProjectDayHistory(
                                $sheet->internalControl?->vehicle_uuid,
                                $sheet->project_uuid,
                                $sheet->service_date,
                                $sheet->company_uuid
                            )
                        ),
                    ];
                    $mapaFecha = Carbon::parse($sheet->service_date)->format('d/m/Y');
                    $mapaEsCaptura = true;
                }
            }
            if (! $mapaEsCaptura) {
                foreach ($hojasDias as $hojaMapa) {
                    $vehicleUuidMapa = $hojaMapa->internalControl?->vehicle_uuid;
                    if (empty($vehicleUuidMapa) && ! empty($placa)) {
                        $vehicleUuidMapa = Vehicle::where('vehicle_license_plate', $placa)->value('uuid');
                    }
                    $puntosGps = $this->historial->getVehicleProjectDayHistory(
                        $vehicleUuidMapa,
                        $hojaMapa->project_uuid,
                        $hojaMapa->service_date,
                        $hojaMapa->company_uuid
                    );
                    if ($puntosGps->isEmpty()) {
                        continue;
                    }
                    $mapaRecorrido = $this->mapas->generar($puntosGps);
                    $mapaFecha = Carbon::parse($hojaMapa->service_date)->format('d/m/Y');
                    break;
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('No se pudo generar el mapa del recorrido GPS: '.$e->getMessage());
        }

        return [$mapaRecorrido, $mapaFecha, $mapaEsCaptura];
    }

    /**
     * Bandas de evidencia del diario: faltantes operativos por día, días que
     * solo esperan la firma tardía del coordinador y días cerrados con excepción.
     * La evidencia incompleta se AVISA, nunca se bloquea (SPEC-002 §7.4).
     *
     * @return array{0: array, 1: array, 2: array}
     */
    public function armarBandas(iterable $hojasDias): array
    {
        $firmasPendientesPdf = [];
        $certificacionesPendientesPdf = [];
        $diasConExcepcion = [];
        $pendientesPorDia = $this->evidencia->pendientesEnLote($hojasDias);
        foreach ($hojasDias as $hojaDia) {
            if ($hojaDia->esCerradaConExcepcion()) {
                $diasConExcepcion[] = [
                    'fecha' => Carbon::parse($hojaDia->service_date)->format('d/m/Y'),
                    'motivo' => $hojaDia->motivoExcepcion(),
                ];
                continue;
            }

            $pendientesDia = $pendientesPorDia[$hojaDia->id] ?? [];
            $pendientesOperativos = array_values(array_filter(
                $pendientesDia,
                fn (array $item) => ($item['rol'] ?? null) !== Signature::ROL_COORDINADOR
            ));
            $faltaCoordinador = collect($pendientesDia)->contains(
                fn (array $item) => ($item['rol'] ?? null) === Signature::ROL_COORDINADOR
            );
            if ($pendientesOperativos !== []) {
                $firmasPendientesPdf[] = [
                    'fecha' => Carbon::parse($hojaDia->service_date)->format('d/m/Y'),
                    'pendientes' => array_column($pendientesOperativos, 'etiqueta'),
                ];
            }
            if ($pendientesOperativos === []
                && $faltaCoordinador
                && ! $hojaDia->is_active
                && ! $hojaDia->esCerradaConExcepcion()) {
                $certificacionesPendientesPdf[] = Carbon::parse($hojaDia->service_date)->format('d/m/Y');
            }
        }

        return [$firmasPendientesPdf, $certificacionesPendientesPdf, $diasConExcepcion];
    }
}
