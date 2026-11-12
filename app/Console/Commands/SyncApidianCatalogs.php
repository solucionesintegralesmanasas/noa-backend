<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Regenera config/apidian-catalogs.php desde el catálogo de ApiDian (FACTW).
 *
 * FACTW expone /ubl2.1/listings (403 KB, sin filtros). Este comando lo descarga
 * una vez y escribe el mapa de municipios (código DANE → id de FACTW) que usa
 * ApidianMapeo. Ejecutar cuando FACTW publique un catálogo nuevo.
 */
class SyncApidianCatalogs extends Command
{
    protected $signature = 'billing:sync-apidian-catalogs {--token= : Token de API de ApiDian} {--base-url= : URL base, por defecto la de config}';

    protected $description = 'Regenera el catálogo de municipios de ApiDian (config/apidian-catalogs.php)';

    public function handle(): int
    {
        $token = $this->option('token') ?: config('billing.apidian.token');
        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('billing.apidian.base_url', 'https://apidian.systemsfact.com.co')), '/');

        if ($token === null || $token === '') {
            $this->error('Falta el token: pasa --token=... o define APIDIAN_TOKEN en el .env.');

            return self::FAILURE;
        }

        $respuesta = Http::acceptJson()
            ->withToken((string) $token)
            ->timeout(60)
            ->get("{$baseUrl}/api/ubl2.1/listings");

        if ($respuesta->failed()) {
            $this->error("No se pudo descargar el catálogo (HTTP {$respuesta->status()}).");

            return self::FAILURE;
        }

        $data = $respuesta->json('data') ?? [];

        $municipios = [];
        foreach ($data['municipalities'] ?? [] as $municipio) {
            $municipios[(string) $municipio['code']] = (int) $municipio['id'];
        }

        if ($municipios === []) {
            $this->error('El catálogo no trajo municipios.');

            return self::FAILURE;
        }

        $exportado = var_export($municipios, true);
        $contenido = "<?php\n\n// Generado por billing:sync-apidian-catalogs el ".now()->toDateTimeString().".\n"
            ."// No editar a mano. Mapa: código DANE de NOA → id de FACTW.\n\n"
            ."return [\n    'municipalities' => {$exportado},\n];\n";

        file_put_contents(config_path('apidian-catalogs.php'), $contenido);

        $this->info('Catálogo actualizado: '.count($municipios).' municipios.');

        return self::SUCCESS;
    }
}
