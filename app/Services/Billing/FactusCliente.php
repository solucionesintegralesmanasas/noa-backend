<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP autenticado de Factus (OAuth2 "password").
 *
 * - Antes de enviar, si no hay access_token o está por vencer, se obtiene
 *   (login o refresh según lo que haya guardado).
 * - Ante 401 intenta primero el refresh y, si falla, el login completo con
 *   username + password; guarda los tokens nuevos y reintenta una vez.
 * - Respeta el límite de 80 peticiones/minuto reintentando tras el `Retry-After`.
 *
 * Lo comparten el driver de emisión (FactusFacturacion) y el módulo de
 * configuraciones (FactusConfiguracion).
 */
final class FactusCliente
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

        if ($this->tokenVencido()) {
            $this->refrescarToken() ?? $this->renovarTokenSiPosible();
        }

        $respuesta = $this->peticion($metodo, $url, $payload);

        if (in_array($respuesta->status(), [401, 403], true)) {
            $this->refrescarToken() ?? $this->renovarTokenSiPosible();
            $respuesta = $this->peticion($metodo, $url, $payload);
        }

        if ($respuesta->status() === 429) {
            $espera = min(10, max(1, (int) ($respuesta->header('Retry-After') ?: 1)));
            sleep($espera);
            $respuesta = $this->peticion($metodo, $url, $payload);
        }

        $this->abortarSiTokenInvalido($respuesta);

        return $respuesta;
    }

    /** Sin access token guardado pero con credenciales, se obtiene antes de enviar. */
    public function asegurarToken(): void
    {
        if ((string) $this->proveedor->access_token === '' && $this->puedeRenovarToken()) {
            $this->renovarToken();
        }

        if ((string) $this->proveedor->access_token === '') {
            throw new \RuntimeException("El proveedor {$this->proveedor->name} no tiene token de Factus configurado.");
        }
    }

    /**
     * Login OAuth2 "password" (grant_type=password). No persiste: lo usan el
     * guardado del proveedor y la renovación automática.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function obtenerTokensDeLogin(): array
    {
        try {
            $login = Http::asForm()
                ->acceptJson()
                ->timeout(config('billing.timeout', 30))
                ->post($this->baseUrl().'/oauth/token', [
                    'grant_type' => 'password',
                    'client_id' => (string) $this->proveedor->client_id,
                    'client_secret' => (string) $this->proveedor->client_secret,
                    'username' => (string) $this->proveedor->email,
                    'password' => (string) $this->proveedor->client_password,
                ]);
        } catch (ConnectionException) {
            throw new \RuntimeException('No se pudo conectar con Factus para iniciar sesión: verifica la URL y las credenciales del proveedor tecnológico.');
        }

        $tokens = $this->leerTokens($login);

        if ($login->failed() || $tokens === null) {
            throw new \RuntimeException('No se pudo iniciar sesión con Factus: verifica el client_id, el client_secret, el correo y la contraseña del proveedor tecnológico.');
        }

        return $tokens;
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int}|null */
    private function leerTokens(Response $respuesta): ?array
    {
        $access = $respuesta->json('access_token');
        $refresh = $respuesta->json('refresh_token');
        if (! is_string($access) || $access === '') {
            return null;
        }

        return [
            'access_token' => $access,
            'refresh_token' => is_string($refresh) && $refresh !== '' ? $refresh : (string) $this->proveedor->refresh_token,
            'expires_in' => (int) ($respuesta->json('expires_in') ?: 3600),
        ];
    }

    private function peticion(string $metodo, string $url, array $payload): Response
    {
        $http = Http::acceptJson()
            ->timeout(config('billing.timeout', 30))
            ->withToken((string) $this->proveedor->access_token);

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
            && (string) $this->proveedor->email !== ''
            && (string) $this->proveedor->client_password !== '';
    }

    private function renovarToken(): void
    {
        $this->guardarTokens($this->obtenerTokensDeLogin());
    }

    /** Login completo solo con credenciales; null deja que el 401 se explique. */
    private function renovarTokenSiPosible(): ?string
    {
        if (! $this->puedeRenovarToken()) {
            return null;
        }

        $this->renovarToken();

        return (string) $this->proveedor->access_token;
    }

    /**
     * Refresh OAuth2 (grant_type=refresh_token) con el refresh token guardado.
     * Si no hay refresh token o el refresh falla, se devuelve null para caer al login.
     */
    private function refrescarToken(): ?string
    {
        $refresh = (string) $this->proveedor->refresh_token;
        if ($refresh === '') {
            return null;
        }

        try {
            $respuesta = Http::asForm()
                ->acceptJson()
                ->timeout(config('billing.timeout', 30))
                ->post($this->baseUrl().'/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refresh,
                    'client_id' => (string) $this->proveedor->client_id,
                    'client_secret' => (string) $this->proveedor->client_secret,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        $tokens = $this->leerTokens($respuesta);
        if ($respuesta->failed() || $tokens === null) {
            return null;
        }

        $this->guardarTokens($tokens);

        return $tokens['access_token'];
    }

    /** Detecta un access token por vencer en 30 s a partir del vencimiento guardado. */
    private function tokenVencido(): bool
    {
        $expira = $this->proveedor->access_token_expires_at;

        return $expira !== null && $expira->getTimestamp() <= time() + 30;
    }

    /** @param array{access_token: string, refresh_token: string, expires_in: int} $tokens */
    private function guardarTokens(array $tokens): void
    {
        $this->proveedor->withoutLogging(fn () => $this->proveedor->update([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'access_token_expires_at' => now()->addSeconds(max(60, $tokens['expires_in'])),
        ]));
        $this->proveedor->refresh();
    }

    /**
     * Tras renovar el token, un 401/403 persistente se explica con el mensaje de
     * Factus: un 403 no siempre es de token (p. ej. "Version de API no
     * disponible para esta empresa" cuando la cuenta no tiene habilitada la v2).
     */
    private function abortarSiTokenInvalido(Response $respuesta): void
    {
        $http = $respuesta->status();
        if ($http === 429) {
            throw new \RuntimeException('Se superó el límite de 80 solicitudes por minuto de Factus; reintenta en un momento.');
        }

        if (! in_array($http, [401, 403], true)) {
            return;
        }

        $mensaje = trim((string) ($respuesta->json('message') ?? ''));

        if (stripos($mensaje, 'version de api') !== false || stripos($mensaje, 'versión de api') !== false) {
            throw new \RuntimeException("Factus rechazó la petición: {$mensaje}. La cuenta de Factus de la empresa no tiene habilitada esta versión del API; solicita a soporte de Factus que la active.");
        }

        if ($http === 403 && $mensaje !== '' && stripos($mensaje, 'token') === false && stripos($mensaje, 'unauthenticated') === false) {
            throw new \RuntimeException("Factus denegó el acceso (HTTP 403): {$mensaje}");
        }

        throw new \RuntimeException('El token de Factus es inválido o expiró; actualiza las credenciales en el proveedor tecnológico de la empresa.');
    }
}
