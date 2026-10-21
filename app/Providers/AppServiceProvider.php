<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\UserAuthenticated;
use App\Listeners\HandlePostAuthentication;
use App\Services\Billing\ContratoFacturacionElectronica;
use App\Services\Billing\ContratoNotasElectronica;
use App\Services\Billing\FacturacionNula;
use App\Services\Billing\FacturacionPorEmpresa;
use Illuminate\Filesystem\FilesystemAdapter as IlluminateFilesystemAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalAdapter;
use League\Flysystem\MimeTypeDetection\ExtensionMimeTypeDetector;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Driver de facturación electrónica POR EMPRESA: se resuelve según el
        // proveedor de cada empresa en billing_providers (driver='apidian').
        // Sin proveedor activo, o con driver='nulo', se usa el driver nulo
        // (genera y guarda el UBL sin transmitir).
        $this->app->bind(ContratoFacturacionElectronica::class, FacturacionPorEmpresa::class);
        $this->app->bind(ContratoNotasElectronica::class, FacturacionPorEmpresa::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            UserAuthenticated::class,
            HandlePostAuthentication::class
        );

        $this->registerLocalDiskWithoutFileinfo();
    }

    /**
     * Mitigación temporal para servidores sin la extensión PHP fileinfo.
     *
     * Sin fileinfo, Flysystem no puede instanciar ningún disco local porque su
     * detector de MIME por defecto (FinfoMimeTypeDetector) usa la clase finfo.
     * Eso tumba todo lo que toque archivos: /me, detalle de empresa, PDFs con
     * logo y subidas de MediaLibrary.
     *
     * Este driver alternativo detecta el MIME por extensión de archivo
     * (ExtensionMimeTypeDetector, no requiere fileinfo) y solo se activa cuando
     * la extensión falta. Al instalar fileinfo en el servidor, Laravel vuelve
     * automáticamente al driver local estándar.
     */
    private function registerLocalDiskWithoutFileinfo(): void
    {
        if (extension_loaded('fileinfo')) {
            return;
        }

        Storage::extend('local', function ($app, array $config) {
            $visibility = PortableVisibilityConverter::fromArray(
                $config['permissions'] ?? [],
                $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE
            );

            $links = ($config['links'] ?? null) === 'skip'
                ? LocalAdapter::SKIP_LINKS
                : LocalAdapter::DISALLOW_LINKS;

            $adapter = new LocalAdapter(
                $config['root'],
                $visibility,
                $config['lock'] ?? LOCK_EX,
                $links,
                new ExtensionMimeTypeDetector()
            );

            return new IlluminateFilesystemAdapter(
                new Flysystem($adapter, ['visibility' => $config['visibility'] ?? Visibility::PRIVATE]),
                $adapter,
                $config
            );
        });
    }
}
