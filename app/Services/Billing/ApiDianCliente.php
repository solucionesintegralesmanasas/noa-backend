<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP autenticado de ApiDian (paso 00 de la colección).
 *
 * - Antes de enviar, si el JWT guardado venció, lo refresca.
 * - Ante 401/403 intenta Refresh JWT y, si falla, login con email + password.
 * - Guarda el token nuevo cifrado en el proveedor y reintenta una vez.
 *
 * Lo comparten el driver de emisión (ApiDianFacturacion) y el módulo de
 * configuraciones básicas (ApiDianConfiguracion).
 */
final class ApiDianCliente
{
    public function __construct(private readonly BillingProvider $proveedor) {}

    public function baseUrl(): string
    {
        $base = rtrim((string) $this->proveedor->server_url, '/');
        if ($base === '') {
            throw new \RuntimeException("El proveedor {$this->proveedor->name} no tiene URL del API configurada.");
        }

        return $base;
    }

    /**
     * Envía una petición autenticada, renovando el token si hace falta.
     *
     * @param  'get'|'post'|'put'|'delete'  $metodo
     */
    public function enviar(string $metodo, string $url, array $payload = []): Response
    {
        $this->asegurarToken();

        if ($this->jwtVencido()) {
            $this->refrescarToken() ?? $this->renovarTokenSiPosible();
        }

        $respuesta = $this->peticion($metodo, $url, $payload);

        if (in_array($respuesta->status(), [401, 403], true)) {
            $this->refrescarToken() ?? $this->renovarTokenSiPosible();
            $respuesta = $this->peticion($metodo, $url, $payload);
        }

        $this->abortarSiTokenInvalido($respuesta->status());

        return $respuesta;
    }

    /** Sin token guardado pero con login del portal: se obtiene antes de enviar. */
    public function asegurarToken(): void
    {
        if ((string) $this->proveedor->client_secret === '' && $this->puedeRenovarToken()) {
            $this->renovarToken();
        }

        if ((string) $this->proveedor->client_secret === '') {
            throw new \RuntimeException("El proveedor {$this->proveedor->name} no tiene token de ApiDian configurado.");
        }
    }

    /**
     * Login del portal (paso 00): POST /api/auth/login con email + password.
     * No persiste: lo usa el guardado del proveedor y la renovación automática.
     */
    public function obtenerTokenDeLogin(): string
    {
        $login = Http::acceptJson()
            ->timeout(config('billing.timeout', 30))
            ->post($this->baseUrl().'/api/auth/login', [
                'email' => (string) $this->proveedor->client_id,
                'password' => (string) $this->proveedor->client_password,
            ]);

        $token = $login->json('access_token');
        if ($login->failed() || ! is_string($token) || $token === '') {
            throw new \RuntimeException('No se pudo renovar el token de ApiDian: verifica el correo y la contraseña del portal en el proveedor tecnológico.');
        }

        return $token;
    }

    private function peticion(string $metodo, string $url, array $payload): Response
    {
        $http = Http::acceptJson()
            ->timeout(config('billing.timeout', 30))
            ->withToken((string) $this->proveedor->client_secret);

        return match ($metodo) {
            'get' => $http->get($url),
            'put' => $http->put($url, $payload),
            'delete' => $http->delete($url, $payload),
            default => $http->post($url, $payload),
        };
    }

    private function puedeRenovarToken(): bool
    {
        return (string) $this->proveedor->client_id !== ''
            && (string) $this->proveedor->client_password !== '';
    }

    private function renovarToken(): void
    {
        $this->guardarToken($this->obtenerTokenDeLogin());
    }

    /** Login solo cuando hay credenciales; null deja que el 401 se explique. */
    private function renovarTokenSiPosible(): ?string
    {
        if (! $this->puedeRenovarToken()) {
            return null;
        }

        $this->renovarToken();

        return (string) $this->proveedor->client_secret;
    }

    /** Refresh JWT (paso 00): POST /api/auth/refresh con el token actual. */
    private function refrescarToken(): ?string
    {
        $token = (string) $this->proveedor->client_secret;
        if ($token === '') {
            return null;
        }

        try {
            $refresh = Http::acceptJson()
                ->timeout(config('billing.timeout', 30))
                ->withToken($token)
                ->post($this->baseUrl().'/api/auth/refresh');
        } catch (ConnectionException) {
            return null;
        }

        $nuevo = $refresh->json('access_token');
        if ($refresh->failed() || ! is_string($nuevo) || $nuevo === '') {
            return null;
        }

        $this->guardarToken($nuevo);

        return $nuevo;
    }

    /** Detecta un JWT vencido (o por vencer en 30 s) sin validar la firma. */
    private function jwtVencido(): bool
    {
        $partes = explode('.', (string) $this->proveedor->client_secret);
        if (count($partes) !== 3) {
            return false;
        }

        $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);
        $exp = $payload['exp'] ?? null;

        return is_numeric($exp) && (int) $exp <= time() + 30;
    }

    private function guardarToken(string $token): void
    {
        $this->proveedor->withoutLogging(fn () => $this->proveedor->update(['client_secret' => $token]));
        $this->proveedor->refresh();
    }

    private function abortarSiTokenInvalido(int $http): void
    {
        if (in_array($http, [401, 403], true)) {
            throw new \RuntimeException('El token de ApiDian es inválido o expiró; actualízalo en el proveedor tecnológico de la empresa.');
        }
    }
}
