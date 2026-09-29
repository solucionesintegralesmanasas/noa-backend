<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Resumen diario por conductor para el mapa del recorrido.
     *
     * El trazado decimado agrupaba por cubetas de tiempo sobre la ventana
     * completa (tabla temporal + ordenamiento). Con el resumen diario, el mapa
     * lee como máximo una fila por día y fusiona sus muestras, así que el
     * coste depende de los días del rango y no de los puntos.
     */
    public function up(): void
    {
        Schema::create('driver_location_daily_stats', function (Blueprint $table) {
            $table->id()->comment('ID único del resumen diario');
            $table->uuid('uuid')->unique()->comment('UUID único universal del resumen');
            $table->uuid('company_uuid')->comment('UUID único universal de la empresa');
            $table->uuid('third_party_uuid')->comment('UUID único universal del conductor');
            $table->date('service_date')->comment('Día del recorrido (zona horaria de la aplicación)');
            $table->unsignedInteger('total_points')->default(0)->comment('Puntos GPS del día');
            $table->decimal('total_distance_meters', 14, 2)->default(0)->comment('Distancia recorrida del día en metros');
            $table->json('samples')->nullable()->comment('Muestra del recorrido: [latitud, longitud, fecha, velocidad]');
            $table->timestamps();

            $table->unique(
                ['third_party_uuid', 'company_uuid', 'service_date'],
                'uk_dlds_driver_company_date'
            );
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('third_party_uuid')->references('uuid')->on('third_parties')->onDelete('cascade')->onUpdate('cascade');
        });

        $this->backfill();
    }

    /**
     * Construye el resumen de cada día con datos existentes.
     *
     * Se procesa una partición (conductor, empresa, día) por vez para no cargar
     * la tabla completa en una sola sentencia. En una instalación sin histórico
     * es un recorrido vacío.
     */
    private function backfill(): void
    {
        DB::table('driver_locations')
            ->selectRaw('third_party_uuid, company_uuid, DATE(recorded_at) AS dia')
            ->distinct()
            ->orderBy('third_party_uuid')
            ->chunk(200, function ($particiones) {
                foreach ($particiones as $particion) {
                    $this->backfillDia(
                        $particion->third_party_uuid,
                        $particion->company_uuid,
                        $particion->dia
                    );
                }
            });
    }

    /**
     * Construye el resumen de un día desde sus puntos.
     *
     * El rango de fecha usa el índice; el diezmado es el mismo de
     * DriverLocationDailyStat::indicesParaTope() y se duplica aquí para que la
     * migración no dependa del código actual.
     */
    private function backfillDia(string $driverUuid, string $companyUuid, string $dia): void
    {
        $filas = DB::table('driver_locations')
            ->select(['latitude', 'longitude', 'recorded_at', 'speed', 'distance_meters'])
            ->where('third_party_uuid', $driverUuid)
            ->where('company_uuid', $companyUuid)
            ->whereBetween('recorded_at', [
                Carbon::parse($dia)->startOfDay(),
                Carbon::parse($dia)->endOfDay(),
            ])
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        $total = count($filas);

        if ($total === 0) {
            return;
        }

        $indices = range(0, $total - 1);

        if ($total > 300) {
            $paso = ($total - 1) / 299;
            $indices = [];

            for ($i = 0; $i < 300; $i++) {
                $indices[] = (int) round($i * $paso);
            }

            $indices[0] = 0;
            $indices[299] = $total - 1;
            $indices = array_values(array_unique($indices));
        }

        $muestras = [];
        $distancia = 0.0;

        foreach ($filas as $fila) {
            $distancia += (float) $fila->distance_meters;
        }

        foreach ($indices as $i) {
            $fila = $filas[$i];
            $muestras[] = [
                (float) $fila->latitude,
                (float) $fila->longitude,
                (string) $fila->recorded_at,
                (float) ($fila->speed ?? 0),
            ];
        }

        DB::table('driver_location_daily_stats')->insert([
            'uuid' => (string) Str::uuid(),
            'company_uuid' => $companyUuid,
            'third_party_uuid' => $driverUuid,
            'service_date' => $dia,
            'total_points' => $total,
            'total_distance_meters' => round($distancia, 2),
            'samples' => json_encode($muestras),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_location_daily_stats');
    }
};
