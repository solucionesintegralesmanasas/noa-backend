<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Carbon\Carbon;

/**
 * Armado de filas $dias para los PDF de planillas (diario, mensual, filtrado).
 *
 * Módulo puro: no consulta base de datos, disco ni red. Recibe hojas con
 * recorridos y las firmas ya resueltas (base64) y devuelve filas listas para
 * la vista. Es la única definición de la forma de cada fila.
 *
 * Decisiones de unificación (candidato B):
 * - Las filas por recorrido usan hora/km del recorrido con respaldo global,
 *   detalle con funcionario + peajes + novedad, y firma con respaldo a la hoja.
 * - El filtrado hereda ese tratamiento (antes mostraba solo globales): es la
 *   corrección del reporte empobrecido.
 * - `unique_key`/`clave_recorrido` no se emiten: eran claves internas del lote
 *   mensual que la vista nunca leyó.
 */
final class ServiceControlSheetRows
{
    /**
     * @param iterable $hojasDias planillas del reporte
     * @param ServiceControlSheetRowsOptions $opciones resoluciones por reporte
     * @return array{dias: array, totalRecorridos: int, rutasDetalle: array}
     */
    public function armarDias(iterable $hojasDias, ServiceControlSheetRowsOptions $opciones): array
    {
        $dias = [];
        $rutasDetalle = [];
        $totalRecorridos = 0;

        $proyectoDe = $opciones->proyecto;
        $rutasDe = $opciones->rutasDe;
        $firmaHoja = $opciones->firmaHoja;
        $firmasRuta = $opciones->firmasRuta;

        foreach ($hojasDias as $hojaDia) {
            $fechaDia = Carbon::parse($hojaDia->service_date);
            $routes = collect($rutasDe($hojaDia))->values();
            $rutasLista = $this->formatRoutesList($routes);
            $rutasTexto = $rutasLista !== []
                ? implode(' · ', $rutasLista)
                : ($hojaDia->daily_route ?? 'Sin ruta definida');

            $restHours = $this->calculateRestHours($hojaDia->start_time, $hojaDia->end_time, $hojaDia->total_hours);
            $horaInicio = $hojaDia->start_time ? $hojaDia->start_time->format('H:i') : '';
            $horaFinGlobal = $hojaDia->end_time ? $hojaDia->end_time->format('H:i') : '';
            $totalHoras = $hojaDia->total_hours ? $hojaDia->total_hours->format('H:i') : '';
            $kmInicialFmt = $this->formatKilometer($hojaDia->starting_kilometer);
            $kmFinalGlobalFmt = $this->formatKilometer($hojaDia->ending_kilometer);
            $kmTotalGlobal = ($hojaDia->starting_kilometer !== null && $hojaDia->ending_kilometer !== null
                && $hojaDia->starting_kilometer !== '' && $hojaDia->ending_kilometer !== '')
                ? $this->formatKilometer($hojaDia->ending_kilometer - $hojaDia->starting_kilometer)
                : '';

            $baseFila = [
                'numero' => $opciones->numeroEntero ? (int) $fechaDia->format('d') : $fechaDia->format('d'),
                'fecha_completa' => $fechaDia->format('d/m/Y'),
                'hora_inicio' => $horaInicio,
                'descanso_inicio' => $restHours['inicio'],
                'descanso_fin' => $restHours['fin'],
                'hora_fin' => $horaFinGlobal,
                'total_hours' => $totalHoras,
                'km_inicial' => $kmInicialFmt,
                'km_final' => $kmFinalGlobalFmt,
                'km_total' => $kmTotalGlobal,
                'conductor' => $hojaDia->driver_name,
                'proyecto' => $proyectoDe($hojaDia),
                'estado' => $hojaDia->is_active ? 'ABIERTA' : 'FINALIZADA',
                'is_closed' => ! (bool) $hojaDia->is_active,
            ];

            if ($routes->isNotEmpty()) {
                foreach ($routes as $route) {
                    $firmas = $firmasRuta($hojaDia, $route);
                    $rutaUnica = trim(($route->origin ?? '').' - '.($route->destination ?? ''), ' -');
                    if ($rutaUnica === '' || $rutaUnica === '-') {
                        $rutaUnica = $hojaDia->daily_route ?? 'Sin ruta definida';
                    }
                    // Si el recorrido tiene cierre propio se usa en la fila; si no, se conserva el global.
                    $horaFinRec = ! empty($route->end_time) ? Carbon::parse($route->end_time)->format('H:i') : '';
                    $kmFinRec = (isset($route->ending_kilometer) && $route->ending_kilometer !== null && $route->ending_kilometer !== '')
                        ? $route->ending_kilometer
                        : null;
                    $horaFinFila = $horaFinRec !== '' ? $horaFinRec : $horaFinGlobal;
                    $kmFinalFilaRaw = $kmFinRec !== null ? $kmFinRec : $hojaDia->ending_kilometer;
                    $kmFinalFila = $this->formatKilometer($kmFinalFilaRaw);
                    $kmTotalFila = ($hojaDia->starting_kilometer !== null && $hojaDia->starting_kilometer !== '' && $kmFinalFilaRaw !== null && $kmFinalFilaRaw !== '')
                        ? $this->formatKilometer($kmFinalFilaRaw - $hojaDia->starting_kilometer)
                        : $kmTotalGlobal;
                    // Detalle de cierre solo si existe (funcionario / peajes / novedad).
                    $detalleCierre = '';
                    $partesCierre = [];
                    if (! empty($route->funcionario_nombre) || ! empty($route->funcionario_cc)) {
                        $partesCierre[] = 'Funcionario CC '.trim(($route->funcionario_cc ?? '').(! empty($route->funcionario_nombre) ? ' - '.$route->funcionario_nombre : ''));
                    }
                    if (! empty($route->number_of_tolls) || ! empty($route->total_toll_value)) {
                        $valorPeaje = isset($route->total_toll_value) ? number_format((float) $route->total_toll_value, 0, ',', '.') : '0';
                        $partesCierre[] = 'Peajes: '.($route->number_of_tolls ?? 0).' ($'.$valorPeaje.')';
                    }
                    if (! empty($route->end_novelty)) {
                        $partesCierre[] = 'Novedad: '.$route->end_novelty;
                    }
                    if (! empty($partesCierre)) {
                        $detalleCierre = implode(' | ', $partesCierre);
                    }
                    if ($opciones->conRutasDetalle) {
                        $detalleRuta = $route->toArray();
                        $detalleRuta['firma_funcionario'] = $firmas['funcionario'] ?? null;
                        $detalleRuta['firma_conductor'] = $firmas['conductor'] ?? null;
                        $rutasDetalle[] = $detalleRuta;
                    }
                    // La firma de cada fila es la del funcionario del recorrido; si el recorrido
                    // no tiene firma propia se usa la del funcionario de la hoja como respaldo.
                    // La firma del coordinador NUNCA va en esta columna: solo en RECIBO Y FIRMA.
                    $dias[] = array_merge($baseFila, [
                        'ruta' => $rutaUnica,
                        'ruta_unica' => $rutaUnica,
                        'hora_fin' => $horaFinFila,
                        'km_final' => $kmFinalFila,
                        'km_total' => $kmTotalFila,
                        'hora_fin_recorrido' => $horaFinRec,
                        'km_final_recorrido' => $kmFinRec !== null ? $this->formatKilometer($kmFinRec) : '',
                        'detalle_cierre' => $detalleCierre,
                        'firma_funcionario' => ($firmas['funcionario'] ?? null) ?: $firmaHoja($hojaDia),
                        'firma_conductor_recorrido' => null,
                    ]);
                }
                $totalRecorridos += $routes->count();
            } else {
                $esDisponibilidad = $hojaDia->esDisponibilidad();
                $textoDisponibilidad = $esDisponibilidad ? $hojaDia->textoDisponibilidad() : '';
                $dias[] = array_merge($baseFila, [
                    'ruta' => $esDisponibilidad ? $textoDisponibilidad : $rutasTexto,
                    'ruta_unica' => $esDisponibilidad ? $textoDisponibilidad : $rutasTexto,
                    'rutas' => $rutasLista,
                    'hora_fin_recorrido' => '',
                    'km_final_recorrido' => '',
                    'detalle_cierre' => $esDisponibilidad
                        ? 'Vehículo en disponibilidad'.($hojaDia->motivoDisponibilidad() !== null
                            ? ' — '.$hojaDia->motivoDisponibilidad()
                            : ' — sin recorridos asignados')
                        : '',
                    // En disponibilidad no hay funcionario: solo firman conductor y coordinador (pie).
                        'firma_funcionario' => $esDisponibilidad ? null : $firmaHoja($hojaDia),
                    'firma_conductor_recorrido' => null,
                ]);
            }
        }

        return ['dias' => $dias, 'totalRecorridos' => $totalRecorridos, 'rutasDetalle' => $rutasDetalle];
    }

    /**
     * Formatea kilómetros sin ceros innecesarios: 12300.00 → 12300, 12300.50 → 12300.5.
     */
    public function formatKilometer(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (! is_numeric($valor)) {
            return (string) $valor;
        }
        $numero = number_format((float) $valor, 2, '.', '');
        $numero = rtrim(rtrim($numero, '0'), '.');

        return $numero === '' ? '0' : $numero;
    }

    /**
     * @param  \Illuminate\Support\Collection  $routes
     * @return array<int, string>
     */
    public function formatRoutesList($routes): array
    {
        return $routes
            ->map(fn ($r) => trim(($r->origin ?? '').' - '.($r->destination ?? ''), ' -'))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Calcula las horas de descanso (inicio y fin) basado en la diferencia entre
     * el tiempo transcurrido y las horas reportadas (estándar Colombia).
     */
    public function calculateRestHours(?Carbon $start, ?Carbon $end, ?Carbon $totalHours): array
    {
        if (! $start || ! $end) {
            return ['inicio' => '', 'fin' => ''];
        }

        $totalElapsedMinutes = $start->diffInMinutes($end);
        if ($end < $start) {
            $totalElapsedMinutes = $start->diffInMinutes($end->copy()->addDay());
        }

        $breakMinutes = 0;

        if ($totalHours) {
            $totalWorkedMinutes = ($totalHours->hour * 60) + $totalHours->minute;
            $breakMinutes = $totalElapsedMinutes - $totalWorkedMinutes;
        }

        // Si no hay diferencia reportada, pero la jornada es mayor a 6 horas,
        // en Colombia por ley debe haber al menos 1 hora de descanso.
        if ($breakMinutes <= 0 && $totalElapsedMinutes > 360) {
            $breakMinutes = 60; // Forzar 1 hora de descanso
        }

        if ($breakMinutes > 0) {
            // Descanso estándar colombiano suele ser al mediodía (12:00 PM)
            $breakStart = Carbon::createFromTime(12, 0);

            // Si el turno empezó después de las 12:00 o en la noche, el descanso se pone a la mitad de la jornada
            if ($start->hour >= 12 || $start->hour < 5 || $end->hour <= 12) {
                $halfElapsed = (int) ($totalElapsedMinutes / 2);
                $halfBreak = (int) ($breakMinutes / 2);
                $breakStart = $start->copy()->addMinutes($halfElapsed - $halfBreak);
            }

            $breakEnd = $breakStart->copy()->addMinutes($breakMinutes);

            return [
                'inicio' => $breakStart->format('H:i'),
                'fin' => $breakEnd->format('H:i'),
            ];
        }

        return ['inicio' => '', 'fin' => ''];
    }
}
