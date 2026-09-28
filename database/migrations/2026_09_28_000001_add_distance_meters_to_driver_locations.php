<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distancia recorrida por punto e índice de lectura del historial.
     *
     * Las estadísticas calculaban la distancia con una función de ventana
     * (LAG + haversine) que obligaba a escanear la tabla completa aunque el
     * rango fuera de un solo día. Guardando la distancia de cada punto, el
     * total del periodo es un SUM acotado al rango.
     *
     * El índice encabeza por conductor, que es el filtro real de los endpoints
     * de historial y estadísticas, e incluye velocidad y distancia para que el
     * agregado de estadísticas se resuelva sin tocar la tabla.
     */
    public function up(): void
    {
        Schema::table('driver_locations', function (Blueprint $table) {
            $table->decimal('distance_meters', 12, 2)->default(0)->after('source')
                ->comment('Distancia en metros desde el punto anterior del mismo conductor');

            $table->index(
                ['third_party_uuid', 'company_uuid', 'recorded_at', 'speed', 'distance_meters'],
                'idx_dl_driver_company_recorded'
            );
        });

        $this->backfill();
    }

    /**
     * Calcula la distancia de cada punto respecto a su anterior por conductor,
     * respetando el orden en que se registraron (recorded_at, id).
     *
     * Se procesa un conductor por vez para no cargar la tabla completa en una
     * sola sentencia. En una instalación sin histórico es un recorrido vacío.
     */
    private function backfill(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS tmp_driver_location_distance');
        DB::statement('CREATE TEMPORARY TABLE tmp_driver_location_distance (id BIGINT PRIMARY KEY, distance_meters DOUBLE)');

        $conductores = DB::table('driver_locations')
            ->distinct()
            ->pluck('third_party_uuid');

        foreach ($conductores as $driverUuid) {
            DB::statement('TRUNCATE TABLE tmp_driver_location_distance');

            DB::statement(
                'INSERT INTO tmp_driver_location_distance (id, distance_meters) ' .
                'SELECT id, CASE WHEN prev_lat IS NULL THEN 0 ELSE ' .
                '6371000 * 2 * ATAN2(SQRT(LEAST(a, 1)), SQRT(GREATEST(1 - LEAST(a, 1), 0))) END FROM (' .
                'SELECT id, prev_lat, POW(SIN(RADIANS(lat - prev_lat) / 2), 2) + ' .
                'COS(RADIANS(prev_lat)) * COS(RADIANS(lat)) * POW(SIN(RADIANS(lng - prev_lng) / 2), 2) AS a FROM (' .
                'SELECT id, latitude AS lat, longitude AS lng, ' .
                'LAG(latitude) OVER (ORDER BY recorded_at, id) AS prev_lat, ' .
                'LAG(longitude) OVER (ORDER BY recorded_at, id) AS prev_lng ' .
                'FROM driver_locations WHERE third_party_uuid = ?) p) d',
                [$driverUuid]
            );

            DB::statement(
                'UPDATE driver_locations dl JOIN tmp_driver_location_distance t ON t.id = dl.id ' .
                'SET dl.distance_meters = t.distance_meters'
            );
        }

        DB::statement('DROP TEMPORARY TABLE IF EXISTS tmp_driver_location_distance');
    }

    public function down(): void
    {
        Schema::table('driver_locations', function (Blueprint $table) {
            $table->dropIndex('idx_dl_driver_company_recorded');
            $table->dropColumn('distance_meters');
        });
    }
};
