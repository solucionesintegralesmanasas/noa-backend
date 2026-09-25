<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Agrega a own_company_names las variantes del nombre operativo que ya
     * usan las tarjetas de la flota (p. ej. singular en la tarjeta frente a
     * la razón social en plural). Solo se aceptan candidatas que compartan
     * al menos 3 tokens significativos con el nombre legal, ignorando el
     * sufijo societario (S.A.S. y similares), para no absorber terceros.
     */
    public function up(): void
    {
        $companies = DB::table('companies')->select('uuid', 'business_name', 'trade_name')->get();

        foreach ($companies as $company) {
            $config = DB::table('system_configuration')->where('company_uuid', $company->uuid)->first();
            if (! $config) {
                continue;
            }

            $actuales = is_string($config->own_company_names)
                ? (json_decode($config->own_company_names, true) ?: [])
                : (is_array($config->own_company_names) ? $config->own_company_names : []);
            if (! is_array($actuales)) {
                $actuales = [];
            }

            $normalizados = [];
            foreach ($actuales as $nombre) {
                if (is_string($nombre) && trim($nombre) !== '') {
                    $normalizados[] = $this->normalizar($nombre);
                }
            }

            $legales = array_values(array_unique(array_filter([
                $this->normalizar((string) $company->business_name),
                $this->normalizar((string) $company->trade_name),
            ])));

            $candidatas = DB::table('operation_cards')
                ->where('company_uuid', $company->uuid)
                ->distinct()
                ->pluck('affiliated_company');

            $agregadas = false;
            foreach ($candidatas as $candidata) {
                if (! is_string($candidata) || trim($candidata) === '') {
                    continue;
                }
                $norm = $this->normalizar($candidata);
                if (in_array($norm, $normalizados, true)) {
                    continue;
                }
                if (! $this->coincideConLegal($norm, $legales)) {
                    continue;
                }
                $actuales[] = trim($candidata);
                $normalizados[] = $norm;
                $agregadas = true;
            }

            if ($agregadas) {
                DB::table('system_configuration')
                    ->where('company_uuid', $company->uuid)
                    ->update([
                        'own_company_names' => json_encode(array_values($actuales)),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Sin reversa: carga de datos observados, no cambio de esquema.
     */
    public function down(): void
    {
        // Sin operación.
    }

    private function normalizar(string $nombre): string
    {
        $normalizado = Str::ascii(mb_strtoupper(trim($nombre), 'UTF-8'));
        $normalizado = (string) preg_replace('/[^A-Z0-9]+/', ' ', $normalizado);

        return trim((string) preg_replace('/\s+/', ' ', $normalizado));
    }

    /**
     * @param array<int, string> $legales
     */
    private function coincideConLegal(string $norm, array $legales): bool
    {
        $tokens = $this->tokensSignificativos($norm);
        if (count($tokens) < 3) {
            return false;
        }
        foreach ($legales as $legal) {
            if ($legal === '') {
                continue;
            }
            $comunes = array_intersect($tokens, $this->tokensSignificativos($legal));
            if (count($comunes) >= 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tokens normalizados sin el sufijo societario (S.A.S., LTDA., S.A., ...),
     * que de otro modo haría coincidir a cualquier empresa colombiana.
     *
     * @return array<int, string>
     */
    private function tokensSignificativos(string $normalizado): array
    {
        $excluidos = ['S', 'A', 'SAS', 'SA', 'LTDA', 'LIMITADA', 'CIA', 'CO', 'EU', 'SAS'];
        $tokens = array_values(array_filter(
            explode(' ', $normalizado),
            fn (string $t) => $t !== '' && ! in_array($t, $excluidos, true)
        ));

        return array_values(array_unique($tokens));
    }
};
