<?php

declare(strict_types=1);

namespace App\Services\ServiceDeliveryControlSheet;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\ServiceDeliveryControlSheetRoute;
use App\Models\Signature;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Evidencia PCP: ciclo de vida administrativo de planillas y días.
 *
 * Módulo profundo de SOLO DERIVACIÓN: responde preguntas sobre la evidencia
 * (estado, pendientes, resumen), nunca muta. El congelamiento y la escritura
 * de firmas siguen en ServiceDeliveryControlSheetService y SignatureService.
 *
 * Es el único hogar de:
 * - los literales de entidad de firmas del dominio PCP,
 * - la regla de completitud por unidad operativa (SPEC-002 §5),
 * - la derivación de estados administrativos (SPEC-002 §4),
 * - el mapeo de compatibilidad a control_status legado,
 * - la carga en lote que garantiza consultas constantes (§7.5).
 */
final class PcpEvidence
{
    public const ENTITY_PLANILLA = 'App\\Models\\ServiceDeliveryControlSheet';

    public const ENTITY_RUTA = 'App\\Models\\ServiceDeliveryControlSheetRoute';

    public const ENTITY_COORDINADOR = 'App\\Models\\ServiceDeliveryControlSheetCoordinator';

    /**
     * Decora una colección de planillas padre (con hijos) con estado,
     * pendientes y agregados. Carga las firmas en lote internamente:
     * las consultas son constantes, no crecen por fila (§7.5).
     */
    public function decorarColeccion(Collection $padres): void
    {
        $todas = $padres->flatMap(function ($padre) {
            return collect([$padre])->concat($padre->relationLoaded('children') ? $padre->children : collect());
        })->values();
        $planillaIds = $todas->map(fn ($h) => $h->id)->filter()->values()->all();
        $rutaIds = $todas->flatMap(fn ($hoja) => $hoja->relationLoaded('routes')
            ? $hoja->routes->map(fn ($r) => $r->id)->all()
            : [])->all();
        $mapa = $this->mapaFirmas($planillaIds, $rutaIds);

        $decorar = function (ServiceDeliveryControlSheet $item) use ($mapa): array {
            $certificada = (bool) ($mapa['coordinador'][$item->id] ?? false);
            $rutas = $item->relationLoaded('routes')
                ? $item->routes
                : $item->routes()->orderBy('order_index')->get();
            $pendientes = $this->calcularPendientes($item, $rutas, $mapa);
            $pendientesOperativos = array_filter(
                $pendientes,
                fn (array $pendiente) => ($pendiente['rol'] ?? null) !== Signature::ROL_COORDINADOR
            );
            $item->setAttribute('operativamente_completa', $pendientesOperativos === []);
            $item->setAttribute('firmas_pendientes', $pendientes);

            return $pendientes;
        };

        foreach ($padres as $item) {
            $pendientesPadre = $decorar($item);
            $certificadaPadre = (bool) ($mapa['coordinador'][$item->id] ?? false);
            $item->setAttribute('routes_total', $item->routes ? $item->routes->count() : 0);
            $item->setAttribute('has_coordinator_signature', $certificadaPadre);

            // Los días hijos también llevan su propio estado: es lo que muestran
            // las tablas del listado y la ficha de proyecto (§8).
            if ($item->relationLoaded('children')) {
                $item->children->each(function ($hijo) use ($decorar, $mapa): void {
                    $pendientes = $decorar($hijo);
                    $pendientesOperativos = array_filter(
                        $pendientes,
                        fn (array $pendiente) => ($pendiente['rol'] ?? null) !== Signature::ROL_COORDINADOR
                    );
                    $hijo->setAttribute(
                        'estado',
                        $this->estado(
                            $hijo,
                            (bool) ($mapa['coordinador'][$hijo->id] ?? false),
                            $pendientesOperativos === []
                        )
                    );
                });
                $pendientesHijos = $item->children->flatMap(function ($hijo) {
                    $fecha = $hijo->service_date
                        ? Carbon::parse($hijo->service_date)->format('d/m/Y')
                        : 'Fecha desconocida';

                    return collect($hijo->firmas_pendientes ?? [])->map(function (array $pendiente) use ($fecha) {
                        $pendiente['clave'] = 'dia:'.$fecha.':'.$pendiente['clave'];
                        $pendiente['etiqueta'] = $fecha.' — '.$pendiente['etiqueta'];

                        return $pendiente;
                    });
                })->values()->all();
                $item->setAttribute('firmas_pendientes', $pendientesHijos);
                $operativamenteCompletaPadre = $item->children->every(
                    fn ($hijo) => ($hijo->operativamente_completa ?? false)
                        || $hijo->estado === ServiceDeliveryControlSheet::ESTADO_CERRADA_CON_EXCEPCION
                );
                $item->setAttribute('operativamente_completa', $operativamenteCompletaPadre);
                $item->setAttribute('dias_evidencia_incompleta', $item->children->filter(
                    fn ($hijo) => ! ($hijo->operativamente_completa ?? false)
                        && ! $hijo->esCerradaConExcepcion()
                )->count());
                $item->setAttribute('dias_pendientes_certificacion', $item->children->filter(
                    fn ($hijo) => $hijo->estado === ServiceDeliveryControlSheet::ESTADO_CERRADA_OPERATIVAMENTE
                )->count());
                $item->setAttribute('dias_excepcion', $item->children->filter(
                    fn ($hijo) => $hijo->estado === ServiceDeliveryControlSheet::ESTADO_CERRADA_CON_EXCEPCION
                )->count());
                $estadosHijos = $item->children->pluck('estado')->all();
                $estadoPadre = $this->estado($item, $certificadaPadre, $operativamenteCompletaPadre, $estadosHijos);
            } else {
                $item->setAttribute('dias_evidencia_incompleta', 0);
                $item->setAttribute('dias_pendientes_certificacion', 0);
                $item->setAttribute('dias_excepcion', 0);
                $estadoPadre = $this->estado(
                    $item,
                    $certificadaPadre,
                    count(array_filter($pendientesPadre, fn (array $p) => ($p['rol'] ?? null) !== Signature::ROL_COORDINADOR)) === 0
                );
            }

            $item->setAttribute('estado', $estadoPadre);
            // Compatibilidad: nadie nuevo debe leer control_status, pero se sigue
            // emitiendo con valores idénticos a los históricos para no romper
            // consumidores existentes (p. ej. la app móvil).
            $item->setAttribute('control_status', $this->controlStatus($estadoPadre));
        }
    }

    /**
     * Pendientes de un solo registro (PDF por día, tests, usos puntuales).
     *
     * @return array<int, array{clave: string, etiqueta: string, rol: ?string}>
     */
    public function pendientes(ServiceDeliveryControlSheet $record): array
    {
        return $this->pendientesEnLote([$record])[$record->id] ?? [];
    }

    /**
     * Pendientes de varias planillas con un lote de rutas y 3 consultas
     * de firmas. Lo usan el listado, proyectos y PDF multi-día para evitar N+1.
     *
     * @param  iterable<ServiceDeliveryControlSheet>  $records
     * @return array<int, array<int, array{clave: string, etiqueta: string, rol: ?string}>>
     */
    public function pendientesEnLote(iterable $records): array
    {
        $records = collect($records)->values();
        if ($records->isEmpty()) {
            return [];
        }

        $sinRelaciones = $records->filter(fn (ServiceDeliveryControlSheet $record) => ! $record->relationLoaded('routes'));
        if ($sinRelaciones->isNotEmpty()) {
            $porPlanilla = ServiceDeliveryControlSheetRoute::query()
                ->whereIn('service_delivery_control_sheet_uuid', $sinRelaciones->pluck('uuid')->all())
                ->orderBy('order_index')
                ->get()
                ->groupBy('service_delivery_control_sheet_uuid');

            foreach ($sinRelaciones as $record) {
                $record->setRelation('routes', $porPlanilla->get($record->uuid, collect()));
            }
        }

        $routeIds = $records->flatMap(fn ($record) => $record->routes->pluck('id'))->all();
        $mapa = $this->mapaFirmas($records->pluck('id')->all(), $routeIds);

        $pendientes = [];
        foreach ($records as $record) {
            $pendientes[$record->id] = $this->calcularPendientes($record, $record->routes, $mapa);
        }

        return $pendientes;
    }

    /**
     * Resumen global por día de un proyecto, independiente de la página visible.
     * Lee en bloques para no cargar en memoria el historial completo.
     *
     * @return array{total_dias: int, cerrados: int, en_curso: int, evidencia_incompleta: int, pendiente_certificacion: int, excepciones: int, certificados: int}
     */
    public function resumenProyecto(string $projectUuid): array
    {
        $summary = [
            'total_dias' => 0,
            'cerrados' => 0,
            'en_curso' => 0,
            'evidencia_incompleta' => 0,
            'pendiente_certificacion' => 0,
            'excepciones' => 0,
            'certificados' => 0,
        ];

        $query = ServiceDeliveryControlSheet::query()
            ->whereNull('parent_uuid')
            ->where('project_uuid', $projectUuid)
            ->with(['routes', 'children.routes'])
            ->withCount(['children as children_total'])
            ->withCount(['children as children_open' => fn ($q) => $q->where('is_active', true)]);

        $query->chunkById(100, function ($parents) use (&$summary): void {
            $units = $parents->flatMap(
                fn (ServiceDeliveryControlSheet $parent) => $parent->children->isNotEmpty()
                    ? $parent->children
                    : collect([$parent])
            )->values();

            $planillaIds = $units->pluck('id')->all();
            $routeIds = $units->flatMap(fn ($unit) => $unit->routes->pluck('id'))->all();
            $map = $this->mapaFirmas($planillaIds, $routeIds);

            foreach ($units as $unit) {
                $pending = $this->calcularPendientes($unit, $unit->routes, $map);
                $operationalPending = array_filter(
                    $pending,
                    fn (array $item) => ($item['rol'] ?? null) !== Signature::ROL_COORDINADOR
                );
                $operationallyComplete = $operationalPending === [];
                $certified = (bool) ($map['coordinador'][$unit->id] ?? false);
                $state = $this->estado($unit, $certified, $operationallyComplete);

                $summary['total_dias']++;
                $unit->is_active ? $summary['en_curso']++ : $summary['cerrados']++;

                if ($state === ServiceDeliveryControlSheet::ESTADO_CERRADA_CON_EXCEPCION) {
                    $summary['excepciones']++;
                } elseif (! $operationallyComplete) {
                    $summary['evidencia_incompleta']++;
                } elseif (! $unit->is_active && ! $certified) {
                    $summary['pendiente_certificacion']++;
                } elseif ($state === ServiceDeliveryControlSheet::ESTADO_CERTIFICADA) {
                    $summary['certificados']++;
                }
            }
        }, 'id');

        return $summary;
    }

    /**
     * SPEC-002 §4 — Estado administrativo derivado.
     *
     * No reemplaza a `is_active` (que se conserva como bandera de compatibilidad):
     * lo enriquece para distinguir en qué punto del ciclo está la evidencia.
     *
     * @param  bool|null  $certificada  Pasa el resultado ya calculado para evitar
     *                                  una consulta por fila en listados (§7.5).
     */
    public function estado(
        ServiceDeliveryControlSheet $record,
        ?bool $certificada = null,
        bool $operativamenteCompleta = true,
        array $estadosHijos = []
    ): string {
        $total = (int) ($record->children_total ?? 0);
        $abiertos = (int) ($record->children_open ?? 0);

        if ($total > 0) {
            if ($abiertos > 0) {
                if ($abiertos < $total) {
                    return ServiceDeliveryControlSheet::ESTADO_PARCIAL;
                }

                return empty($record->start_time)
                    ? ServiceDeliveryControlSheet::ESTADO_BORRADOR
                    : ServiceDeliveryControlSheet::ESTADO_EN_CURSO;
            }

            // Un padre multi-día resume a sus unidades hijas. No se certifica
            // por la firma del padre si un día hijo sigue incompleto.
            if (in_array(ServiceDeliveryControlSheet::ESTADO_CERRADA_CON_EXCEPCION, $estadosHijos, true)
                || $record->esCerradaConExcepcion()) {
                return ServiceDeliveryControlSheet::ESTADO_CERRADA_CON_EXCEPCION;
            }
            if (in_array(ServiceDeliveryControlSheet::ESTADO_PARCIAL, $estadosHijos, true)) {
                return ServiceDeliveryControlSheet::ESTADO_PARCIAL;
            }
            if ($estadosHijos !== [] && count(array_filter(
                $estadosHijos,
                fn (string $estado) => $estado === ServiceDeliveryControlSheet::ESTADO_CERTIFICADA
            )) === count($estadosHijos)) {
                return ServiceDeliveryControlSheet::ESTADO_CERTIFICADA;
            }

            return ServiceDeliveryControlSheet::ESTADO_CERRADA_OPERATIVAMENTE;
        }

        if ($record->is_active) {
            if (empty($record->start_time)) {
                return ServiceDeliveryControlSheet::ESTADO_BORRADOR;
            }

            return ServiceDeliveryControlSheet::ESTADO_EN_CURSO;
        }

        if ($record->esCerradaConExcepcion()) {
            return ServiceDeliveryControlSheet::ESTADO_CERRADA_CON_EXCEPCION;
        }

        if (! $operativamenteCompleta) {
            return ServiceDeliveryControlSheet::ESTADO_PARCIAL;
        }

        $certificada ??= $record->tieneCertificacionVigente();

        return $certificada ? ServiceDeliveryControlSheet::ESTADO_CERTIFICADA : ServiceDeliveryControlSheet::ESTADO_CERRADA_OPERATIVAMENTE;
    }

    /**
     * Mapeo de compatibilidad al control_status legado.
     *
     * Valores idénticos a los históricos en todos los casos salvo uno
     * intencionado: un padre con todos los días cerrados pero evidencia
     * incompleta antes decía CERRADA y ahora dice PARCIAL, que es lo que
     * realmente es según SPEC-002 §4. El frontend no consume este campo.
     */
    public function controlStatus(string $estado): string
    {
        return match ($estado) {
            ServiceDeliveryControlSheet::ESTADO_PARCIAL => 'PARCIAL',
            ServiceDeliveryControlSheet::ESTADO_CERRADA_OPERATIVAMENTE,
            ServiceDeliveryControlSheet::ESTADO_CERTIFICADA,
            ServiceDeliveryControlSheet::ESTADO_CERRADA_CON_EXCEPCION => 'CERRADA',
            default => 'ABIERTA',
        };
    }

    /**
     * Carga en 3 consultas las firmas vigentes de varias planillas a la vez.
     * Evita el N+1 del listado (§7.5): 15 filas pasan de ~45 consultas a 3.
     *
     * @param  array<int>  $planillaIds
     * @param  array<int>  $rutaIds
     * @return array{planilla: array<int, array<string, mixed>>, coordinador: array<int, bool>, ruta: array<int, array<string, mixed>>}
     */
    private function mapaFirmas(array $planillaIds, array $rutaIds): array
    {
        $mapa = ['planilla' => [], 'coordinador' => [], 'ruta' => []];

        if (! empty($planillaIds)) {
            Signature::query()
                ->where('entity_type', self::ENTITY_PLANILLA)
                ->whereIn('entity_id', $planillaIds)
                ->where('status', Signature::STATUS_VIGENTE)
                ->whereNotNull('signer_role')
                ->get()
                ->each(function (Signature $sig) use (&$mapa): void {
                    $mapa['planilla'][$sig->entity_id][$sig->signer_role] = true;
                });

            Signature::query()
                ->where('entity_type', self::ENTITY_COORDINADOR)
                ->whereIn('entity_id', $planillaIds)
                ->where('signer_role', Signature::ROL_COORDINADOR)
                ->where('status', Signature::STATUS_VIGENTE)
                ->get(['entity_id'])
                ->each(function (Signature $sig) use (&$mapa): void {
                    $mapa['coordinador'][$sig->entity_id] = true;
                });
        }

        if (! empty($rutaIds)) {
            Signature::query()
                ->where('entity_type', self::ENTITY_RUTA)
                ->whereIn('entity_id', $rutaIds)
                ->where('status', Signature::STATUS_VIGENTE)
                ->whereNotNull('signer_role')
                ->get()
                ->each(function (Signature $sig) use (&$mapa): void {
                    $mapa['ruta'][$sig->entity_id][$sig->signer_role] = true;
                });
        }

        return $mapa;
    }

    /**
     * SPEC-002 §7.1 — Reglas de completitud sobre firmas ya cargadas en lote.
     *
     * @param  array  $mapa  Salida de mapaFirmas()
     * @return array<int, array{clave: string, etiqueta: string, rol: ?string}>
     */
    private function calcularPendientes(ServiceDeliveryControlSheet $record, $routes, array $mapa): array
    {
        $pendientes = [];

        // La excepción aprobada justifica precisamente estos faltantes: se
        // muestra como estado propio, no simultáneamente como evidencia incompleta.
        if ($record->esCerradaConExcepcion()) {
            return [];
        }

        $rolesPlanilla = $mapa['planilla'][$record->id] ?? [];
        $certificada = (bool) ($mapa['coordinador'][$record->id] ?? false);

        if (! $record->esDisponibilidad()) {
            foreach ($routes as $indice => $route) {
                $n = $indice + 1;
                $roles = $mapa['ruta'][$route->id] ?? [];
                $tieneNovedad = ! empty($route->end_novelty);

                // Datos operativos: sin ellos la firma sola no certifica el recorrido.
                // La novedad tipificada los excuse (§5.1).
                if (empty($route->end_time) && ! $tieneNovedad) {
                    $pendientes[] = [
                        'clave' => 'ruta:'.$route->uuid.':hora_fin',
                        'etiqueta' => "Recorrido {$n}: falta hora de llegada (o novedad que la excuse)",
                        'rol' => null,
                    ];
                }
                if (($route->ending_kilometer === null || $route->ending_kilometer === '') && ! $tieneNovedad) {
                    $pendientes[] = [
                        'clave' => 'ruta:'.$route->uuid.':km_final',
                        'etiqueta' => "Recorrido {$n}: falta kilometraje final (o novedad que lo excuse)",
                        'rol' => null,
                    ];
                }
                if (empty($route->funcionario_nombre) || empty($route->funcionario_cc)) {
                    $pendientes[] = [
                        'clave' => 'ruta:'.$route->uuid.':funcionario_datos',
                        'etiqueta' => "Recorrido {$n}: falta identificar al funcionario",
                        'rol' => null,
                    ];
                }
                if (! isset($roles[Signature::ROL_FUNCIONARIO])) {
                    $pendientes[] = [
                        'clave' => 'ruta:'.$route->uuid.':'.Signature::ROL_FUNCIONARIO,
                        'etiqueta' => "Recorrido {$n}: falta firma del funcionario",
                        'rol' => Signature::ROL_FUNCIONARIO,
                    ];
                }
                if (! isset($roles[Signature::ROL_CONDUCTOR])) {
                    $pendientes[] = [
                        'clave' => 'ruta:'.$route->uuid.':'.Signature::ROL_CONDUCTOR,
                        'etiqueta' => "Recorrido {$n}: falta firma del conductor",
                        'rol' => Signature::ROL_CONDUCTOR,
                    ];
                }
            }
        } else {
            // Disponibilidad (§5.2): inicio operativo, responsable, motivo, conductor.
            if (empty($record->start_time)) {
                $pendientes[] = [
                    'clave' => 'disponibilidad:inicio',
                    'etiqueta' => 'Falta la hora de inicio del servicio',
                    'rol' => null,
                ];
            }
            if (trim((string) $record->driver_name) === '') {
                $pendientes[] = [
                    'clave' => 'disponibilidad:responsable',
                    'etiqueta' => 'Falta identificar al conductor responsable',
                    'rol' => null,
                ];
            }
            if ($record->day_kind === ServiceDeliveryControlSheet::DIA_DISPONIBILIDAD
                && $record->motivoDisponibilidad() === null) {
                $pendientes[] = [
                    'clave' => 'disponibilidad:motivo',
                    'etiqueta' => 'Falta el motivo de la jornada en disponibilidad',
                    'rol' => null,
                ];
            }
            if (! isset($rolesPlanilla[Signature::ROL_CONDUCTOR])) {
                $pendientes[] = [
                    'clave' => 'planilla:'.Signature::ROL_CONDUCTOR,
                    'etiqueta' => 'Falta firma del conductor',
                    'rol' => Signature::ROL_CONDUCTOR,
                ];
            }
        }

        if (! $certificada) {
            $pendientes[] = [
                'clave' => 'coordinador',
                'etiqueta' => 'Falta firma del coordinador',
                'rol' => Signature::ROL_COORDINADOR,
            ];
        }

        return $pendientes;
    }
}
