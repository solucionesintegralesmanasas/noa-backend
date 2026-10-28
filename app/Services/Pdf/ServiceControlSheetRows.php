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
     * @return array{dias: array, totalRecorridos: int, rutasDetalle: array, totales: array{dias: int, dias_cerrados: int, horas_minutos: int, horas: string, km: float}}
     */
    public function armarDias(iterable $hojasDias, ServiceControlSheetRowsOptions $opciones): array
    {
        $hojas = is_array($hojasDias) ? $hojasDias : iterator_to_array($hojasDias);
        $dias = [];
        $rutasDetalle = [];
        $totalRecorridos = 0;

        $proyectoDe = $opciones->proyecto;
        $rutasDe = $opciones->rutasDe;
        $firmaHoja = $opciones->firmaHoja;
        $firmasRuta = $opciones->firmasRuta;
        $firmaCoordinador = $opciones->firmaCoordinador;
        $firmaConductor = $opciones->firmaConductor;

        foreach ($hojas as $hojaDia) {
            $fechaDia = Carbon::parse($hojaDia->service_date);
            $routes = collect($rutasDe($hojaDia))->values();
            $rutasLista = $this->formatRoutesList($routes);
            $rutasTexto = $rutasLista !== []
                ? implode(' · ', $rutasLista)
                : ($hojaDia->daily_route ?? 'Sin ruta definida');

            // Se lee el valor crudo de total_hours: el cast `datetime:H:i:s` no
            // admite turnos de más de 24 h ('25:30:00') y hacía fallar el PDF.
            $horasCrudas = $hojaDia->getAttributes()['total_hours'] ?? null;
            $horasTurno = $this->duracionTurno($horasCrudas);
            $restHours = $this->calculateRestHours($hojaDia->start_time, $hojaDia->end_time, $horasCrudas);
            $horaInicio = $hojaDia->start_time ? $hojaDia->start_time->format('H:i') : '';
            $horaFinGlobal = $hojaDia->end_time ? $hojaDia->end_time->format('H:i') : '';
            $totalHoras = $horasTurno['texto'];
            $kmInicialNum = $this->kilometros($hojaDia->starting_kilometer);
            $kmFinalNum = $this->kilometros($hojaDia->ending_kilometer);
            $kmInicialFmt = $this->formatKilometer($hojaDia->starting_kilometer);
            $kmFinalGlobalFmt = $this->formatKilometer($hojaDia->ending_kilometer);
            $kmTotalGlobal = ($kmInicialNum !== null && $kmFinalNum !== null)
                ? $this->formatKilometer(max(0, $kmFinalNum - $kmInicialNum))
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
                $rutasArray = $routes->values();
                $ultimaRuta = $rutasArray->count() - 1;
                // Kilometraje por tramo: cada recorrido arranca donde terminó el
                // anterior (o donde arrancó la hoja, para el primero).
                $kmAcumulado = $hojaDia->starting_kilometer;

                foreach ($rutasArray as $indice => $route) {
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
                    // Solo el último tramo sin cierre propio hereda el final de la
                    // hoja; un tramo intermedio sin km final no debe consumir el global.
                    $kmFinTramoRaw = $kmFinRec;
                    if ($kmFinTramoRaw === null && $indice === $ultimaRuta) {
                        $kmFinTramoRaw = $hojaDia->ending_kilometer;
                    }
                    $kmInicialTramoNum = $this->kilometros($kmAcumulado);
                    $kmFinalTramoNum = $this->kilometros($kmFinTramoRaw);
                    $kmTotalTramo = ($kmInicialTramoNum !== null && $kmFinalTramoNum !== null)
                        ? $this->formatKilometer(max(0, $kmFinalTramoNum - $kmInicialTramoNum))
                        : '';
                    $kmFinalFila = $this->formatKilometer($kmFinTramoRaw);
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
                        'km_inicial' => $this->formatKilometer($kmAcumulado),
                        'km_final' => $kmFinalFila,
                        'km_total' => $kmTotalTramo,
                        'hora_fin_recorrido' => $horaFinRec,
                        'km_final_recorrido' => $kmFinRec !== null ? $this->formatKilometer($kmFinRec) : '',
                        'detalle_cierre' => $detalleCierre,
                        'firma_funcionario' => ($firmas['funcionario'] ?? null)
                            ?: $firmaHoja($hojaDia)
                            ?: ($firmaCoordinador ? $firmaCoordinador($hojaDia) : null),
                        // Columna CONDUCTOR ASIGNADO: firma del conductor del recorrido;
                        // si el recorrido no la tiene, la del conductor de la hoja.
                        'firma_conductor_recorrido' => ($firmas['conductor'] ?? null)
                            ?: ($firmaConductor ? $firmaConductor($hojaDia) : null),
                    ]);

                    if ($kmFinTramoRaw !== null && $kmFinTramoRaw !== '') {
                        $kmAcumulado = $kmFinTramoRaw;
                    }
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
                    'firma_funcionario' => $esDisponibilidad
                        ? null
                        : ($firmaHoja($hojaDia) ?: ($firmaCoordinador ? $firmaCoordinador($hojaDia) : null)),
                    'firma_conductor_recorrido' => $firmaConductor ? $firmaConductor($hojaDia) : null,
                ]);
            }
        }

        return [
            'dias' => $dias,
            'totalRecorridos' => $totalRecorridos,
            'rutasDetalle' => $rutasDetalle,
            'totales' => $this->calcularTotales($hojas),
        ];
    }

    /**
     * Consolidado del período a partir de las hojas (no de las filas).
     *
     * Se suma por hoja porque una fila por recorrido repite las horas del día
     * y acumula el kilometraje: sumar filas duplicaría ambos totales.
     *
     * @param  array<int, mixed>  $hojas
     * @return array{dias: int, dias_cerrados: int, horas_minutos: int, horas: string, km: float}
     */
    private function calcularTotales(array $hojas): array
    {
        $minutos = 0;
        $km = 0.0;
        $cerrados = 0;

        foreach ($hojas as $hoja) {
            if (! $hoja->is_active) {
                $cerrados++;
            }

            $minutos += $this->duracionTurno($hoja->getAttributes()['total_hours'] ?? null)['minutos'];

            $inicial = $this->kilometros($hoja->starting_kilometer);
            $final = $this->kilometros($hoja->ending_kilometer);
            if ($inicial !== null && $final !== null) {
                $km += max(0, $final - $inicial);
            }
        }

        return [
            'dias' => count($hojas),
            'dias_cerrados' => $cerrados,
            'horas_minutos' => $minutos,
            'horas' => sprintf('%d:%02d', intdiv($minutos, 60), $minutos % 60),
            'km' => round($km, 2),
        ];
    }

    /**
     * Formatea kilómetros sin ceros innecesarios: 12300.00 → 12300, 12300.50 → 12300.5.
     * Admite el separador de miles con que se captura a veces ('12.345' = 12345).
     */
    public function formatKilometer(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        $numero = $this->kilometros($valor);
        if ($numero === null) {
            return (string) $valor;
        }
        $formateado = number_format($numero, 2, '.', '');
        $formateado = rtrim(rtrim($formateado, '0'), '.');

        return $formateado === '' ? '0' : $formateado;
    }

    /**
     * Convierte un kilometraje capturado como texto a número.
     *
     * La columna es varchar y a veces llega con separador de miles
     * ('12.345'); sin normalizarlo se leía como 12.345 km o como 12,35.
     */
    public function kilometros(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $texto)) {
            $texto = str_replace('.', '', $texto);
        }

        return is_numeric($texto) ? (float) $texto : null;
    }

    /**
     * Duración de un turno en texto y minutos.
     *
     * El cast de la columna es `datetime:H:i:s` y no admite turnos de más de
     * 24 h ('25:30:00'); aquí se lee el valor crudo y se conserva la duración
     * real en vez de desbordarla al día siguiente.
     *
     * @return array{texto: string, minutos: int}
     */
    private function duracionTurno(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return ['texto' => '', 'minutos' => 0];
        }

        if ($valor instanceof \DateTimeInterface) {
            $horas = (int) $valor->format('G');
            $minutos = (int) $valor->format('i');

            return [
                'texto' => sprintf('%02d:%02d', $horas, $minutos),
                'minutos' => ($horas * 60) + $minutos,
            ];
        }

        $texto = trim((string) $valor);
        if (preg_match('/^(\d{1,3}):([0-5]\d)(?::[0-5]\d)?$/', $texto, $partes)) {
            $horas = (int) $partes[1];
            $minutos = (int) $partes[2];

            return [
                'texto' => sprintf('%02d:%02d', $horas, $minutos),
                'minutos' => ($horas * 60) + $minutos,
            ];
        }

        return ['texto' => '', 'minutos' => 0];
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
    public function calculateRestHours(?Carbon $start, ?Carbon $end, mixed $totalHours): array
    {
        if (! $start || ! $end) {
            return ['inicio' => '', 'fin' => ''];
        }

        $totalElapsedMinutes = $start->diffInMinutes($end);
        if ($end < $start) {
            $totalElapsedMinutes = $start->diffInMinutes($end->copy()->addDay());
        }

        $breakMinutes = 0;

        if ($totalHours !== null && $totalHours !== '') {
            $totalWorkedMinutes = $this->duracionTurno($totalHours)['minutos'];
            if ($totalWorkedMinutes > 0) {
                $breakMinutes = $totalElapsedMinutes - $totalWorkedMinutes;
            }
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
