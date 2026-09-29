<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        $particiones = DB::table('driver_locations')
            ->selectRaw('third_party_uuid, company_uuid, DATE(recorded_at) AS dia')
            ->distinct()
            ->get();

        foreach ($particiones as $particion) {
            $agregado = DB::table('driver_locations')
                ->selectRaw('COUNT(*) AS puntos, COALESCE(SUM(distance_meters), 0) AS distancia')
                ->where('third_party_uuid', $particion->third_party_uuid)
                ->where('company_uuid', $particion->company_uuid)
                ->whereDate('recorded_at', $particion->dia)
                ->first();

            $total = (int) $agregado->puntos;

            if ($total === 0) {
                continue;
            }

            $paso = max(1, (int) ceil($total / 300));

            $filas = DB::select(
                'SELECT latitude, longitude, recorded_at, speed FROM (' .
                'SELECT latitude, longitude, recorded_at, speed, ' .
                'ROW_NUMBER() OVER (ORDER BY recorded_at, id) AS rn ' .
                'FROM driver_locations WHERE third_party_uuid = ? AND company_uuid = ? AND DATE(recorded_at) = ?) t ' .
                'WHERE (rn - 1) % ' . $paso . ' = 0 ORDER BY recorded_at',
                [$particion->third_party_uuid, $particion->company_uuid, $particion->dia]
            );

            $muestras = array_map(fn ($f) => [
                (float) $f->latitude,
                (float) $f->longitude,
                (string) $f->recorded_at,
                (float) ($f->speed ?? 0),
            ], $filas);

            DB::table('driver_location_daily_stats')->insert([
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $particion->company_uuid,
                'third_party_uuid' => $particion->third_party_uuid,
                'service_date' => $particion->dia,
                'total_points' => $total,
                'total_distance_meters' => round((float) $agregado->distancia, 2),
                'samples' => json_encode($muestras),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_location_daily_stats');
    }
};
