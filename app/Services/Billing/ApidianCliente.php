<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP para interactuar con la API de ApiDian (FACTW).
 *
 * Dos formas de autenticación (según la doc): el token maestro `whs_…` (sin
 * expiración) o el JWT de `/api/auth/login` (email + contraseña), que expira y
 * se renueva con `/api/auth/refresh` pasando el JWT vigente como Bearer.
 */
final class ApidianCliente
{
    public function __construct(
        private readonly BillingProvider $proveedor
    ) {}

    public function baseUrl(): string
    {
        return rtrim((string) ($this->proveedor->server_url ?? config('billing.apidian.base_url', 'https://apidian.systemsfact.com.co')), '/');
    }

    /**
     * Envía una petición autenticada. Ante un 401 refresca el JWT una vez y reintenta.
     *
     * @param  'get'|'post'|'put'|'delete'  $metodo
     */
    public function enviar(string $metodo, string $url, array $payload = []): Response
    {
        $token = $this->asegurarToken();
        if ($token === null || $token === '') {
            throw new \RuntimeException('No se pudo autenticar con ApiDian. Verifica las credenciales del proveedor.');
        }

        $respuesta = $this->peticion($metodo, $url, $payload, $token);

        if ($respuesta->status() === 401 && ! str_starts_with((string) $this->proveedor->client_password, 'whs_')) {
            $nuevo = $this->refrescarToken($token);
            if ($nuevo !== null && $nuevo !== $token) {
                $respuesta = $this->peticion($metodo, $url, $payload, $nuevo);
            }
        }

        if ($respuesta->failed()) {
            Log::error('ApiDian API Error', [
                'method' => $metodo,
                'url' => $url,
                'status' => $respuesta->status(),
                'body' => mb_strcut($respuesta->body(), 0, 500),
            ]);
        }

        return $respuesta;
    }

    /**
     * Login con email + contraseña. No persiste: el guardado lo hace BillingProviderService.
     *
     * @return array{access_token: string, expires_in: int}|null
     */
    public function obtenerTokensDeLogin(): ?array
    {
        try {
            $login = Http::asJson()
                ->timeout(config('billing.timeout', 30))
                ->post($this->baseUrl().'/api/auth/login', [
                    'email' => (string) $this->proveedor->email,
                    'password' => (string) $this->proveedor->client_password,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($login->failed()) {
            return null;
        }

        $token = $login->json('access_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        return [
            'access_token' => $token,
            'expires_in' => max(60, (int) ($login->json('expires_in') ?? 3600)),
        ];
    }

    public function asegurarToken(): ?string
    {
        // Token maestro `whs_…`: no expira, se usa directo.
        $clave = (string) $this->proveedor->client_password;
        if (str_starts_with($clave, 'whs_')) {
            return $clave;
        }

        // JWT vigente.
        if ((string) $this->proveedor->access_token !== '' && ! $this->tokenVencido()) {
            return (string) $this->proveedor->access_token;
        }

        // Refrescar con el JWT actual.
        $nuevo = $this->refrescarToken((string) $this->proveedor->access_token);
        if ($nuevo !== null) {
            return $nuevo;
        }

        // Login completo.
        $resultado = $this->obtenerTokensDeLogin();
        if ($resultado === null) {
            return null;
        }

        $this->guardarToken($resultado);

        return $resultado['access_token'];
    }

    /** Renueva el JWT pasándolo como Bearer a /api/auth/refresh. */
    private function refrescarToken(string $actual): ?string
    {
        if ($actual === '') {
            return null;
        }

        try {
            $respuesta = Http::acceptJson()
                ->withToken($actual)
                ->timeout(config('billing.timeout', 30))
                ->post($this->baseUrl().'/api/auth/refresh');
        } catch (ConnectionException) {
            return null;
        }

        if ($respuesta->failed()) {
            return null;
        }

        $token = $respuesta->json('access_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        $this->guardarToken([
            'access_token' => $token,
            'expires_in' => max(60, (int) ($respuesta->json('expires_in') ?? 3600)),
        ]);

        return $token;
    }

    private function peticion(string $metodo, string $url, array $payload, string $token): Response
    {
        return Http::acceptJson()
            ->withToken($token)
            ->timeout(config('billing.timeout', 30))
            ->{$metodo}($url, $payload);
    }

    /** @param array{access_token: string, expires_in: int} $tokens */
    private function guardarToken(array $tokens): void
    {
        $this->proveedor->withoutLogging(fn () => $this->proveedor->update([
            'access_token' => $tokens['access_token'],
            'access_token_expires_at' => now()->addSeconds($tokens['expires_in']),
        ]));
        $this->proveedor->refresh();
    }

    private function tokenVencido(): bool
    {
        $expira = $this->proveedor->access_token_expires_at;

        return $expira !== null && $expira->getTimestamp() <= time() + 30;
    }
}
