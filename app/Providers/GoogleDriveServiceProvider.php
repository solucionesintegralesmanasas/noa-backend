<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

class GoogleDriveServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Storage::extend('google', function ($app, $config) {
            // Inicializamos el cliente de Google
            $client = new Client;
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->refreshToken(config('services.google.refresh_token')); // Esto es opcional, lo sobreescribiremos abajo

            // Obtenemos el usuario autenticado actual
            /** @var User|null $user */
            $user = Auth::guard('sanctum')->user();

            // Si hay un usuario autenticado y tiene un refresh_token, lo usamos
            if ($user && $user->google_drive_refresh_token) {
                $client->refreshToken($user->google_drive_refresh_token);
            }

            // Creamos el servicio de Drive
            $service = new Drive($client);

            // Creamos el adaptador de Flysystem
            $options = [];
            if (isset($config['folder_id'])) {
                $options['folderId'] = $config['folder_id'];
            }

            $adapter = new GoogleDriveAdapter($service, config('services.google.folder_id', ''), $options);

            // Devolvemos el Filesystem configurado
            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
