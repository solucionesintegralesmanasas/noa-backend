<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rellena `affiliate_drivers` con los vínculos históricos de `owners_drivers`.
 *
 * Se conserva todo vínculo que hoy existe (aunque el conductor haya cambiado
 * de propietario con el tiempo) para que ningún afiliado pierda visibilidad
 * sobre su historial GPS. El afiliado de la licencia más reciente queda como
 * primario.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('affiliate_drivers')) {
            return;
        }

        $pares = DB::table('owners_drivers as od')
            ->join('driver_licenses as dl', 'dl.uuid', '=', 'od.driver_license_uuid')
            ->select([
                'od.company_uuid',
                'dl.third_party_uuid as driver_uuid',
                'od.third_party_uuid as affiliate_uuid',
                'dl.expiration_date',
                'dl.id as license_id',
            ])
            ->whereNotNull('dl.third_party_uuid')
            ->whereNotNull('od.third_party_uuid')
            ->orderByDesc('dl.expiration_date')
            ->orderByDesc('dl.id')
            ->get();

        $vistos = [];
        $primarios = [];

        foreach ($pares as $par) {
            $llave = $par->company_uuid.'|'.$par->driver_uuid.'|'.$par->affiliate_uuid;

            if (! isset($vistos[$llave])) {
                $vistos[$llave] = true;

                DB::table('affiliate_drivers')->insertOrIgnore([
                    'uuid' => (string) Str::uuid(),
                    'company_uuid' => $par->company_uuid,
                    'driver_uuid' => $par->driver_uuid,
                    'affiliate_uuid' => $par->affiliate_uuid,
                    'is_primary' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // El primer par de cada conductor (licencia más reciente) es el primario.
            if (! isset($primarios[$par->driver_uuid])) {
                $primarios[$par->driver_uuid] = true;

                DB::table('affiliate_drivers')
                    ->where('company_uuid', $par->company_uuid)
                    ->where('driver_uuid', $par->driver_uuid)
                    ->where('affiliate_uuid', $par->affiliate_uuid)
                    ->update(['is_primary' => true, 'updated_at' => now()]);
            }
        }
    }

    /**
     * No se puede reconstruir `owners_drivers` desde la pivote: se conserva la
     * tabla histórica y su `down()` no hace nada.
     */
    public function down(): void
    {
        //
    }
};
