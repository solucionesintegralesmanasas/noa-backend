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
     * Nombres propios ya normalizados, en caché por petición y empresa.
     *
     * @var array<string, array<int, string>>
     */
    private static array $cache = [];

    /**
     * Normaliza un nombre para compararlo de forma tolerante.
     */
    public static function normalizarNombre(?string $nombre): string
    {
        if ($nombre === null) {
            return '';
        }

        $normalizado = Str::ascii(mb_strtoupper(trim($nombre), 'UTF-8'));
        $normalizado = (string) preg_replace('/[^A-Z0-9]+/', ' ', $normalizado);

        return trim((string) preg_replace('/\s+/', ' ', $normalizado));
    }

    /**
     * Retorna los nombres propios normalizados de una empresa.
     *
     * @return array<int, string>
     */
    public static function nombresPropios(?string $companyUuid): array
    {
        $key = (string) $companyUuid;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

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

        self::$cache[$key] = $normalizados;

        return $normalizados;
    }

    /**
     * Indica si el nombre de empresa afiliada corresponde a la empresa propia.
     */
    public static function esNombrePropio(?string $affiliatedCompany, ?string $companyUuid): bool
    {
        $normalizado = self::normalizarNombre($affiliatedCompany);
        if ($normalizado === '') {
            return false;
        }

        return in_array($normalizado, self::nombresPropios($companyUuid), true);
    }

    /**
     * Indica si un vehículo tiene tarjeta de operación de la empresa propia.
     * Se evalúa la tarjeta vigente más reciente; sin tarjeta no se puede
     * determinar y se considera que no es propio.
     */
    public static function esVehiculoPropio(?Vehicle $vehicle, ?string $companyUuid = null): bool
    {
        if (! $vehicle) {
            return false;
        }

        $companyUuid = $companyUuid ?? $vehicle->company_uuid;

        $tarjetas = $vehicle->relationLoaded('operationCards')
            ? $vehicle->operationCards
            : OperationCard::query()->where('vehicle_uuid', $vehicle->uuid)->get();

        $vigente = $tarjetas->sortByDesc(fn (OperationCard $tarjeta) => (string) $tarjeta->expiration_date)->first();

        if (! $vigente) {
            return false;
        }

        return self::esNombrePropio($vigente->affiliated_company, $companyUuid);
    }

    /**
     * Limpia la caché por petición (útil en pruebas).
     */
    public static function limpiarCache(): void
    {
        self::$cache = [];
    }
}
