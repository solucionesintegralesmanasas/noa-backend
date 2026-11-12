<?php

namespace App\Utils;

use App\Models\Company;
use App\Models\OperationCard;
use App\Models\SystemConfiguration;
use App\Models\Vehicle;
use Illuminate\Support\Str;

/**
 * Resuelve si un vehículo opera con tarjeta de la empresa propia.
 *
 * La empresa propia se define por la lista administrable
 * `system_configuration.own_company_names` (con respaldo al business_name y
 * trade_name del tenant) y se compara contra el campo de texto libre
 * `operation_cards.affiliated_company`, normalizado para tolerar variantes
 * de mayúsculas, acentos y espacios.
 */
class OwnCompany
{
    /**
     * Normaliza un nombre para compararlo de forma tolerante: mayúsculas, sin acentos ni
     * signos, espacios colapsados y siglas unificadas (S.A.S, S A S y SAS dan "SAS").
     */
    public static function normalizarNombre(?string $nombre): string
    {
        if ($nombre === null) {
            return '';
        }

        $normalizado = Str::ascii(mb_strtoupper(trim($nombre), 'UTF-8'));
        // Reemplazar signos y caracteres no alfanuméricos por espacio
        $normalizado = (string) preg_replace('/[^A-Z0-9]+/', ' ', $normalizado);
        $normalizado = trim((string) preg_replace('/\s+/', ' ', $normalizado));

        // Unificar siglas comerciales comunes antes de cualquier colapso de letras sueltas
        $siglas = [
            '/\bS A S\b/' => 'SAS',
            '/\bE S P\b/' => 'ESP',
            '/\bL T D A\b/' => 'LTDA',
            '/\bS A\b/' => 'SA',
            '/\bB I C\b/' => 'BIC',
            '/\bS C A\b/' => 'SCA',
        ];
        foreach ($siglas as $patron => $reemplazo) {
            $normalizado = (string) preg_replace($patron, $reemplazo, $normalizado);
        }

        return trim($normalizado);
    }

    /**
     * Retorna los nombres propios normalizados de una empresa (sin retención de estado estático - SPEC-011).
     *
     * @return array<int, string>
     */
    public static function nombresPropios(?string $companyUuid): array
    {
        $nombres = [];

        if ($companyUuid) {
            $configurados = SystemConfiguration::query()
                ->where('company_uuid', $companyUuid)
                ->value('own_company_names');

            if (is_string($configurados)) {
                $configurados = json_decode($configurados, true);
            }
            if (is_array($configurados)) {
                $nombres = array_merge($nombres, $configurados);
            }

            $empresa = Company::query()->where('uuid', $companyUuid)->first(['business_name', 'trade_name']);
            if ($empresa) {
                $nombres[] = $empresa->business_name;
                $nombres[] = $empresa->trade_name;
            }
        }

        $normalizados = [];
        foreach ($nombres as $nombre) {
            $normalizado = self::normalizarNombre(is_string($nombre) ? $nombre : null);
            if ($normalizado !== '' && ! in_array($normalizado, $normalizados, true)) {
                $normalizados[] = $normalizado;
            }
        }

        return $normalizados;
    }

    /**
     * Indica si el nombre de empresa afiliada corresponde a la empresa propia.
     *
     * @param array<int, string>|null $nombresPropios Nombres ya resueltos para evitar consultas N+1 en colecciones.
     */
    public static function esNombrePropio(?string $affiliatedCompany, ?string $companyUuid, ?array $nombresPropios = null): bool
    {
        $normalizado = self::normalizarNombre($affiliatedCompany);
        if ($normalizado === '') {
            return false;
        }

        $nombres = $nombresPropios ?? self::nombresPropios($companyUuid);

        return in_array($normalizado, $nombres, true);
    }

    /**
     * Indica si un vehículo tiene tarjeta de operación de la empresa propia.
     * Se evalúa la tarjeta activa más reciente; si no hay ninguna activa se
     * usa la más reciente como respaldo. Sin tarjeta no se puede determinar
     * y se considera que no es propio.
     *
     * @param array<int, string>|null $nombresPropios Nombres ya resueltos para evitar consultas N+1 en colecciones.
     */
    public static function esVehiculoPropio(?Vehicle $vehicle, ?string $companyUuid = null, ?array $nombresPropios = null): bool
    {
        if (! $vehicle) {
            return false;
        }

        $companyUuid = $companyUuid ?? $vehicle->company_uuid;

        $tarjetas = $vehicle->relationLoaded('operationCards')
            ? $vehicle->operationCards
            : OperationCard::query()->where('vehicle_uuid', $vehicle->uuid)->get();

        $ordenadas = $tarjetas
            ->filter(fn(OperationCard $tarjeta) => $tarjeta->runt_status !== 'CANCELADA')
            ->sortByDesc(fn(OperationCard $tarjeta) => (string) $tarjeta->expiration_date)->values();
        $vigente = $ordenadas->first(fn(OperationCard $tarjeta) => (bool) $tarjeta->status) ?? $ordenadas->first();

        if (! $vigente) {
            return false;
        }

        return self::esNombrePropio($vigente->affiliated_company, $companyUuid, $nombresPropios);
    }

    /**
     * Limpia la caché por petición (No-op tras SPEC-011 para compatibilidad).
     */
    public static function limpiarCache(): void
    {
        // No-op: el estado estático en memoria fue retirado en SPEC-011
    }
}
