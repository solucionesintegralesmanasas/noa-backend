<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Banco de volumen reproducible (SPEC-004, capa 3). Informa, no bloquea.
 *
 *   php artisan perf:bench seed [--puntos=600000] [--alertas=60000]
 *   php artisan perf:bench run  [--etiqueta=...] [--entorno=produccion]
 *   php artisan perf:bench limpiar   (al terminar: las pruebas esperan las tablas vacías)
 *
 * Solo corre contra una base desechable cuyo nombre termine en `_test`: `seed`
 * vacía `driver_locations` y `driver_location_alerts`. Nunca usar con datos reales.
 * Las cifras son de laboratorio (volumen sintético con semilla fija), salvo que se
 * mida con `run --entorno=produccion` sobre una copia desechable de producción
 * (ver docs/metrics/README.md): cada entorno compara solo con su propio historial.
 */
class PerfBench extends Command
{
    protected $signature = 'perf:bench
        {modo : seed (siembra el volumen), run (mide las consultas críticas) o limpiar (vacía las tablas sembradas)}
        {--base=noa_test : Base desechable; debe terminar en _test}
        {--puntos=600000 : Puntos GPS a sembrar}
        {--alertas=60000 : Alertas a sembrar}
        {--corridas=5 : Corridas por consulta (se reporta la mediana)}
        {--escrituras=10000 : Inserciones para medir el coste de escritura}
        {--umbral=50 : % de empeoramiento frente a la medición anterior que se marca}
        {--etiqueta= : Texto libre para identificar la medición}
        {--entorno=local : Entorno de la medición: local o produccion (solo cambia el registro y el historial; nunca mezclar)}
        {--sin-archivo : No guardar el resultado en docs/metrics}';

    protected $description = 'Banco de volumen de tracking (solo sobre la base desechable *_test)';

    private const EMPRESA = '11111111-1111-4111-8111-111111111111';

    public function handle(): int
    {
        $base = (string) $this->option('base');
        if (! preg_match('/_test$/', $base)) {
            $this->error("Rechazado: '$base' no es una base desechable (debe terminar en _test).");

            return self::FAILURE;
        }
        $conexion = config('database.default');
        if (config("database.connections.$conexion.database") !== $base) {
            config(["database.connections.$conexion.database" => $base]);
            DB::purge($conexion);
        }
        if (DB::selectOne('select database() d')->d !== $base) {
            $this->error('La conexión no apunta a '.$base.'; no se ejecuta.');

            return self::FAILURE;
        }

        return match ($this->argument('modo')) {
            'seed' => $this->sembrar((int) $this->option('puntos'), (int) $this->option('alertas')),
            'run' => $this->medir($base, (string) $this->option('entorno')),
            'limpiar' => $this->limpiar(),
            default => $this->fallo('El modo debe ser seed o run.'),
        };
    }

    private function fallo(string $mensaje): int
    {
        $this->error($mensaje);

        return self::FAILURE;
    }

    private static function uuid(string $prefijo, int $n): string
    {
        return sprintf('%s-%s-4%s-8%s-%012d', str_repeat($prefijo, 8), str_repeat($prefijo, 4), str_repeat($prefijo, 3), str_repeat($prefijo, 3), $n);
    }

    private function vaciar(string $tabla): void
    {
        // TRUNCATE confirma la transacción en MySQL; dentro de una (pruebas) se usa DELETE.
        DB::transactionLevel() > 0 ? DB::table($tabla)->delete() : DB::table($tabla)->truncate();
    }

    /** Columnas obligatorias sin valor por defecto, para no acoplar el banco a campos irrelevantes. */
    private function obligatorias(string $tabla): array
    {
        return DB::select(
            'select column_name n, data_type t, column_type ct from information_schema.columns
             where table_schema = database() and table_name = ? and is_nullable = "NO"
             and column_default is null and extra not like "%auto_increment%"',
            [$tabla]
        );
    }

    private function relleno(array $fila, array $obligatorias): array
    {
        foreach ($obligatorias as $c) {
            if (array_key_exists($c->n, $fila)) {
                continue;
            }
            $fila[$c->n] = match (true) {
                $c->t === 'enum' => explode("','", trim(substr($c->ct, 5, -1), "'"))[0],
                in_array($c->t, ['char', 'varchar', 'text'], true) => 'x',
                default => 0,
            };
        }

        return $fila;
    }

    /** Deja vacías las tablas sembradas: las pruebas normales esperan `noa_test` sin puntos. */
    private function limpiar(): int
    {
        Schema::disableForeignKeyConstraints();
        $this->vaciar('driver_location_alerts');
        $this->vaciar('driver_locations');
        Schema::enableForeignKeyConstraints();
        $this->info('Tablas de tracking vaciadas.');

        return self::SUCCESS;
    }

    private function sembrar(int $puntos, int $alertas): int
    {
        Schema::disableForeignKeyConstraints();
        $this->vaciar('driver_locations');
        $this->vaciar('driver_location_alerts');

        mt_srand(7); // semilla fija: mismo volumen en cualquier máquina
        $ahora = strtotime('2026-09-30 12:00:00');
        $tablaPuntos = $this->obligatorias('driver_locations');
        $lote = [];
        for ($n = 0; $n < $puntos; $n++) {
            $propia = mt_rand(1, 100) <= 30; // el 30 % es de la empresa medida; el resto, de otras cinco
            $d = mt_rand(0, 39);
            $ts = date('Y-m-d H:i:s', $ahora - mt_rand(0, 30 * 86400));
            $lote[] = $this->relleno([
                'uuid' => sprintf('%08x-0000-4000-8000-%012x', $n, $n),
                'company_uuid' => $propia ? self::EMPRESA : self::uuid('5', mt_rand(1, 5)),
                'third_party_uuid' => $propia ? self::uuid('2', $d + 1) : self::uuid('6', $d + 1),
                'vehicle_uuid' => self::uuid('3', ($d % 30) + 1),
                'project_uuid' => self::uuid('4', ($d % 6) + 1),
                'latitude' => 4.6 + mt_rand(0, 9999) / 100000,
                'longitude' => -74.0 - mt_rand(0, 9999) / 100000,
                'recorded_at' => $ts, 'created_at' => $ts, 'updated_at' => $ts,
            ], $tablaPuntos);
            if (count($lote) === 1000) {
                DB::table('driver_locations')->insert($lote);
                $lote = [];
            }
        }
        $lote && DB::table('driver_locations')->insert($lote);

        $tablaAlertas = $this->obligatorias('driver_location_alerts');
        $lote = [];
        for ($n = 0; $n < $alertas; $n++) {
            $ts = date('Y-m-d H:i:s', $ahora - mt_rand(0, 30 * 86400));
            $lote[] = $this->relleno([
                'uuid' => sprintf('%08x-1111-4000-8000-%012x', $n, $n),
                'company_uuid' => mt_rand(1, 100) <= 30 ? self::EMPRESA : self::uuid('5', mt_rand(1, 5)),
                'third_party_uuid' => self::uuid('2', mt_rand(1, 40)),
                'is_read' => mt_rand(0, 9) > 1 ? 1 : 0,
                'created_at' => $ts, 'updated_at' => $ts,
            ], $tablaAlertas);
            if (count($lote) === 1000) {
                DB::table('driver_location_alerts')->insert($lote);
                $lote = [];
            }
        }
        $lote && DB::table('driver_location_alerts')->insert($lote);
        Schema::enableForeignKeyConstraints();
        DB::statement('ANALYZE TABLE driver_locations, driver_location_alerts');

        $this->info('Sembrado: '.DB::table('driver_locations')->count().' puntos, '.DB::table('driver_location_alerts')->count().' alertas.');

        return self::SUCCESS;
    }

    /** @return array<string, array{0: string, 1: array}> */
    private function consultas(): array
    {
        $conductor = self::uuid('2', 4);
        $vehiculo = self::uuid('3', 4);
        $proyecto = self::uuid('4', 4);
        $empresa = self::EMPRESA;
        $dia = ['2026-09-20 00:00:00', '2026-09-20 23:59:59'];

        return [
            'Q1 monitor: último punto por conductor (empresa, 1 día)' => ['select max(id) from driver_locations where company_uuid = ? and recorded_at >= ? group by third_party_uuid', [$empresa, '2026-09-29 12:00:00']],
            'Q2 último punto de un conductor' => ['select * from driver_locations where third_party_uuid = ? order by recorded_at desc limit 1', [$conductor]],
            'Q3 mapa del día por vehículo y empresa' => ['select id,latitude,longitude from driver_locations where recorded_at between ? and ? and vehicle_uuid = ? and company_uuid = ? order by recorded_at asc', [...$dia, $vehiculo, $empresa]],
            'Q4 mapa del día por proyecto y empresa' => ['select id,latitude,longitude from driver_locations where recorded_at between ? and ? and project_uuid = ? and company_uuid = ? order by recorded_at asc', [...$dia, $proyecto, $empresa]],
            'Q5 historial del conductor (rango de 1 día)' => ['select * from driver_locations where third_party_uuid = ? and company_uuid = ? and recorded_at between ? and ? order by recorded_at asc', [$conductor, $empresa, ...$dia]],
            'Q6 punto anterior (ingesta)' => ['select * from driver_locations where third_party_uuid = ? and recorded_at <= ? order by recorded_at desc, id desc limit 1', [$conductor, '2026-09-25 10:00:00']],
            'Q7 alertas no leídas, página 1' => ['select * from driver_location_alerts where company_uuid = ? and is_read = 0 order by created_at desc limit 15', [$empresa]],
            'Q8 alertas de la empresa, página 1' => ['select * from driver_location_alerts where company_uuid = ? order by created_at desc limit 15', [$empresa]],
        ];
    }

    private function medir(string $base, string $entorno): int
    {
        if (! in_array($entorno, ['local', 'produccion'], true)) {
            return $this->fallo("Entorno inválido: '$entorno' (usar local o produccion).");
        }
        $puntos = DB::table('driver_locations')->count();
        if ($puntos === 0) {
            return $this->fallo('No hay datos: ejecuta primero `perf:bench seed`.');
        }
        $corridas = max(1, (int) $this->option('corridas'));
        $resultado = [
            'fecha' => now()->toIso8601String(),
            'entorno' => $entorno,
            'base' => $base,
            'motor' => DB::selectOne('select version() v')->v,
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'etiqueta' => (string) $this->option('etiqueta'),
            'volumen' => ['puntos' => $puntos, 'alertas' => DB::table('driver_location_alerts')->count()],
            'corridas' => $corridas,
            'consultas' => [],
        ];

        foreach ($this->consultas() as $nombre => [$sql, $bindings]) {
            $plan = (array) DB::select('explain '.$sql, $bindings)[0];
            $tiempos = [];
            for ($i = 0; $i < $corridas; $i++) {
                $ini = hrtime(true);
                DB::select($sql, $bindings);
                $tiempos[] = (hrtime(true) - $ini) / 1e6;
            }
            sort($tiempos);
            $resultado['consultas'][$nombre] = [
                'mediana_ms' => round($tiempos[intdiv(count($tiempos), 2)], 2),
                'filas_estimadas' => $plan['rows'] ?? null,
                'indice' => $plan['key'] ?? null,
                'tipo' => $plan['type'] ?? null,
                'extra' => $plan['Extra'] ?? null,
            ];
        }
        $resultado['escritura'] = $this->medirEscritura((int) $this->option('escrituras'));

        $anterior = $this->ultimaMedicion($puntos, $entorno);
        $this->mostrar($resultado, $anterior);
        if (! $this->option('sin-archivo')) {
            File::ensureDirectoryExists(base_path('docs/metrics'));
            $ruta = base_path('docs/metrics/bench-'.$entorno.'-'.now()->format('Ymd-His').'.json');
            File::put($ruta, json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $this->line('Guardado: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $ruta));
        }

        return self::SUCCESS;
    }

    /** Coste de insertar N puntos con todos sus índices; las filas se borran al terminar. */
    private function medirEscritura(int $n): array
    {
        $tabla = $this->obligatorias('driver_locations');
        $filas = [];
        for ($i = 0; $i < $n; $i++) {
            $filas[] = $this->relleno([
                'uuid' => sprintf('%08x-eeee-4000-8000-%012x', $i, $i),
                'company_uuid' => self::EMPRESA, 'third_party_uuid' => self::uuid('2', ($i % 40) + 1),
                'vehicle_uuid' => self::uuid('3', ($i % 30) + 1), 'project_uuid' => self::uuid('4', ($i % 6) + 1),
                'latitude' => 4.6, 'longitude' => -74.0,
                'recorded_at' => '2026-09-30 12:00:00', 'created_at' => now(), 'updated_at' => now(),
            ], $tabla);
        }
        Schema::disableForeignKeyConstraints();
        $ini = hrtime(true);
        foreach (array_chunk($filas, 1000) as $lote) {
            DB::table('driver_locations')->insert($lote);
        }
        $ms = (hrtime(true) - $ini) / 1e6;
        // Se limpian las filas de la medición (uuid con marca propia); sin transacciones
        // anidadas, que no soportan el rollback a savepoint de todos los contextos.
        DB::table('driver_locations')->where('uuid', 'like', '%-eeee-4000-8000-%')->delete();
        Schema::enableForeignKeyConstraints();

        return ['inserciones' => $n, 'total_ms' => round($ms, 1), 'ms_por_mil' => round($ms / max(1, $n) * 1000, 2)];
    }

    /** Última medición guardada con el mismo volumen y entorno. */
    private function ultimaMedicion(int $puntos, string $entorno): ?array
    {
        $archivos = glob(base_path('docs/metrics/bench-*.json')) ?: [];
        rsort($archivos);
        foreach ($archivos as $archivo) {
            $datos = json_decode((string) file_get_contents($archivo), true);
            if (($datos['volumen']['puntos'] ?? null) === $puntos && ($datos['entorno'] ?? 'local') === $entorno) {
                return $datos;
            }
        }

        return null;
    }

    private function mostrar(array $r, ?array $anterior): void
    {
        $umbral = (float) $this->option('umbral');
        $this->line("== {$r['etiqueta']} [{$r['entorno']}] ({$r['volumen']['puntos']} puntos, {$r['motor']})");
        $empeoran = 0;
        foreach ($r['consultas'] as $nombre => $c) {
            $marca = '';
            $antes = $anterior['consultas'][$nombre]['mediana_ms'] ?? null;
            // Umbral amplio y mínimo absoluto: el tiempo de una máquina ocupada no es una regresión.
            if ($antes !== null && $c['mediana_ms'] > $antes * (1 + $umbral / 100) && $c['mediana_ms'] - $antes > 5) {
                $marca = "  ⚠ empeoró (antes {$antes} ms)";
                $empeoran++;
            }
            $this->line(sprintf('%-58s %8.1f ms  filas~%-8s key=%s%s', $nombre, $c['mediana_ms'], $c['filas_estimadas'] ?? '?', $c['indice'] ?? 'NINGUNO', $marca));
        }
        $this->line("Escritura: {$r['escritura']['ms_por_mil']} ms por cada 1000 puntos ({$r['escritura']['inserciones']} inserciones, borradas después).");
        if ($anterior === null) {
            $this->line('Sin medición anterior con el mismo volumen para comparar.');
        } elseif ($empeoran > 0) {
            $this->warn("$empeoran consulta(s) empeoraron más de $umbral % frente a {$anterior['fecha']}. Es informativo: no bloquea.");
        }
    }
}
