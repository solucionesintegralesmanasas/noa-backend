<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARQ-008: índices compuestos para las consultas críticas de tracking, validados con EXPLAIN y
 * medición sobre 600 000 puntos GPS (mediana de 5 corridas):
 *
 *   monitor "último punto por conductor" (empresa, 1 día)  948 ms -> 36 ms   (índice cubriente)
 *   mapa del día por vehículo                              973 ms -> 3,5 ms
 *   mapa del día por proyecto                              936 ms -> 117 ms
 *   alertas (listado paginado por empresa)                 2,3 ms -> 0,3 ms
 *
 * `idx_dl_company_recorded` se REEMPLAZA por una versión que lo contiene como prefijo (añade
 * third_party_uuid), así no suma un índice más de escritura. Los otros dos de driver_locations
 * son nuevos; el coste de INSERT medido es despreciable al ritmo real de ingesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Reemplazo por una versión cubriente (company, recorded_at, third_party) para el monitor.
        //    Una sola sentencia: InnoDB no permite borrar el índice que respalda la clave foránea de
        //    company_uuid sin reemplazarlo en la misma operación.
        $actual = collect(Schema::getIndexes('driver_locations'))->firstWhere('name', 'idx_dl_company_recorded');
        if ($actual && $actual['columns'] !== ['company_uuid', 'recorded_at', 'third_party_uuid']) {
            DB::statement('ALTER TABLE driver_locations DROP INDEX idx_dl_company_recorded, ADD INDEX idx_dl_company_recorded (company_uuid, recorded_at, third_party_uuid)');
        } elseif (! $actual) {
            Schema::table('driver_locations', function (Blueprint $t) {
                $t->index(['company_uuid', 'recorded_at', 'third_party_uuid'], 'idx_dl_company_recorded');
            });
        }

        // 2) Mapa del día por vehículo y por proyecto.
        Schema::table('driver_locations', function (Blueprint $t) {
            if (! Schema::hasIndex('driver_locations', 'idx_dl_company_vehicle_recorded')) {
                $t->index(['company_uuid', 'vehicle_uuid', 'recorded_at'], 'idx_dl_company_vehicle_recorded');
            }
            if (! Schema::hasIndex('driver_locations', 'idx_dl_company_project_recorded')) {
                $t->index(['company_uuid', 'project_uuid', 'recorded_at'], 'idx_dl_company_project_recorded');
            }
        });

        // 3) Alertas: listado paginado por empresa (con y sin filtro de no leídas), ordenado por fecha.
        Schema::table('driver_location_alerts', function (Blueprint $t) {
            if (! Schema::hasIndex('driver_location_alerts', 'idx_dla_company_read_created')) {
                $t->index(['company_uuid', 'is_read', 'created_at'], 'idx_dla_company_read_created');
            }
            if (! Schema::hasIndex('driver_location_alerts', 'idx_dla_company_created')) {
                $t->index(['company_uuid', 'created_at'], 'idx_dla_company_created');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_location_alerts', function (Blueprint $t) {
            foreach (['idx_dla_company_read_created', 'idx_dla_company_created'] as $indice) {
                if (Schema::hasIndex('driver_location_alerts', $indice)) {
                    $t->dropIndex($indice);
                }
            }
        });

        Schema::table('driver_locations', function (Blueprint $t) {
            foreach (['idx_dl_company_vehicle_recorded', 'idx_dl_company_project_recorded'] as $indice) {
                if (Schema::hasIndex('driver_locations', $indice)) {
                    $t->dropIndex($indice);
                }
            }
        });

        // Restaura el índice original (company_uuid, recorded_at), también en una sola sentencia.
        if (Schema::hasIndex('driver_locations', 'idx_dl_company_recorded')) {
            DB::statement('ALTER TABLE driver_locations DROP INDEX idx_dl_company_recorded, ADD INDEX idx_dl_company_recorded (company_uuid, recorded_at)');
        }
    }
};
