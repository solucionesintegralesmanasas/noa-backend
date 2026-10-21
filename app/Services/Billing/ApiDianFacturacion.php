<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Models\CommercialInvoice;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\ElectronicInvoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Driver ApiDian: firma, valida y (según send_to_dian) transmite
 * facturas y notas al API del proveedor tecnológico.
 *
 * Reglas heredadas de la colección Postman de referencia:
 * - send=false: el documento se valida/firma/persiste SIN contactar la DIAN.
 * - Con test_set_id configurado, la emisión va a la ruta de habilitación.
 * - El token es el client_secret del proveedor (parametría por empresa).
 *
 * Estados que escribe en los documentos: ACEPTADA_DIAN, RECHAZADA_DIAN,
 * VALIDADA_SIN_ENVIO (envío desactivado).
 */
class ApiDianFacturacion implements ContratoFacturacionElectronica, ContratoNotasElectronica
{
    public function __construct(private readonly BillingProvider $proveedor)
    {
        if (! $this->proveedor->usaApiDian()) {
            throw new \InvalidArgumentException("El proveedor {$this->proveedor->name} no usa ApiDian.");
        }
    }

    public function emitir(CommercialInvoice $factura): array
    {
        $folio = $this->mapeo()->factura($factura);

        $respuesta = $this->transmite('invoice', $folio, $factura);

        $factura->withoutLogging(fn () => $factura->update([
            'electronic_status' => $respuesta['estado'],
            'cufe' => $respuesta['cufe'],
            'provider_document_id' => $respuesta['zip_key'],
            'dian_response' => $respuesta['crudo'],
            'billing_provider_uuid' => $this->proveedor->uuid,
        ]));

        $this->sincronizarElectronicInvoice($factura->fresh(), $respuesta);

        return $respuesta;
    }

    public function consultarEstado(CommercialInvoice $factura): array
    {
        if (! $factura->cufe) {
            return ['estado' => $factura->electronic_status, 'transmitido' => $this->proveedor->send_to_dian, 'detalle' => 'Documento sin CUFE: nunca se firmó'];
        }

        $crudo = $this->estadoEnApiDian($factura->cufe);

        return [
            'estado' => $this->estadoDeConsulta($crudo),
            'transmitido' => $this->proveedor->send_to_dian,
            'cufe' => $factura->cufe,
            'crudo' => $crudo,
        ];
    }

    /** La anulación ante la DIAN se hace emitiendo una nota crédito. */
    public function anular(CommercialInvoice $factura, string $motivo): array
    {
        return [
            'estado' => $factura->electronic_status,
            'transmitido' => $this->proveedor->send_to_dian,
            'motivo' => $motivo,
            'detalle' => 'En ApiDian la anulación se materializa emitiendo una nota crédito contra esta factura.',
        ];
    }

    public function emitirNota(Model $nota): array
    {
        $esCredito = $nota instanceof CreditNote;
        if (! $esCredito && ! $nota instanceof DebitNote) {
            throw new \InvalidArgumentException('El documento no es una nota crédito ni débito.');
        }

        $folio = $this->mapeo()->nota(
            $nota,
            $esCredito ? '4' : '5', // type_document_id DIAN: 4 NC, 5 ND (string por el formato del payload)
            2,                       // discrepancyresponsecode: anulación/corrección
        );

        $respuesta = $this->transmite($esCredito ? 'credit-note' : 'debit-note', $folio, $nota);

        $nota->withoutLogging(fn () => $nota->update([
            'electronic_status' => $respuesta['estado'],
            'cude' => $respuesta['cufe'],
            'dian_response' => $respuesta['crudo'],
        ]));

        return $respuesta;
    }

    public function consultarEstadoNota(Model $nota): array
    {
        if (! $nota->cude) {
            return ['estado' => $nota->electronic_status, 'transmitido' => $this->proveedor->send_to_dian, 'detalle' => 'Nota sin CUDE: nunca se firmó'];
        }

        $crudo = $this->estadoEnApiDian($nota->cude);

        return [
            'estado' => $this->estadoDeConsulta($crudo),
            'transmitido' => $this->proveedor->send_to_dian,
            'cude' => $nota->cude,
            'crudo' => $crudo,
        ];
    }

    /** Consulta el estado DIAN de una clave CUFE/CUDE/CUNE cualquiera (fase 3). */
    public function consultarClave(string $clave): array
    {
        $crudo = $this->estadoEnApiDian($clave);

        return [
            'estado' => $this->estadoDeConsulta($crudo),
            'transmitido' => $this->proveedor->send_to_dian,
            'clave' => $clave,
            'crudo' => $crudo,
        ];
    }

    /**
     * Emisión de un documento ya armado por el llamador (fase 3: soporte,
     * equivalentes, nómina 102/103, eventos RADIAN, lote). Solo empaca y envía.
     */
    public function emitirDocumento(string $tipo, array $payload): array
    {
        return $this->transmite($tipo, $payload, null);
    }

    // ─── transporte ────────────────────────────────────────────────────────

    private function transmite(string $tipo, array $payload, ?\Illuminate\Database\Eloquent\Model $documento): array
    {
        $base = rtrim((string) $this->proveedor->server_url, '/');
        if ($base === '') {
            throw new \RuntimeException("El proveedor {$this->proveedor->name} no tiene URL del API configurada.");
        }

        $this->asegurarToken();

        $payload['send'] = $this->proveedor->send_to_dian;

        $ruta = $this->proveedor->test_set_id
            ? "{$base}/api/ubl2.1/{$tipo}/{$this->proveedor->test_set_id}"
            : "{$base}/api/ubl2.1/{$tipo}";

        $respuestaHttp = $this->enviar($ruta, $payload);

        $crudo = $respuestaHttp->json() ?? [];
        if ($crudo === [] && $respuestaHttp->body() !== '') {
            $crudo = ['respuesta_no_json' => \Illuminate\Support\Str::limit($respuestaHttp->body(), 500)];
        }

        $datos = $crudo['data'] ?? $crudo;

        $resultado = [
            'http' => $respuestaHttp->status(),
            'cufe' => $datos['cufe'] ?? $datos['cude'] ?? $datos['cune'] ?? $datos['UUID'] ?? $datos['uuid'] ?? null,
            'zip_key' => $datos['zipkey'] ?? $datos['zipKey'] ?? $datos['ZipKey'] ?? null,
            'xml_base64' => $datos['xml'] ?? null,
            'pdf_url' => $datos['pdf_url'] ?? $datos['pdfurl'] ?? null,
            'crudo' => $crudo,
        ];

        if ($respuestaHttp->successful() && ! $this->hayErrores($datos)) {
            $resultado['estado'] = $this->proveedor->send_to_dian ? 'ACEPTADA_DIAN' : 'VALIDADA_SIN_ENVIO';
        } else {
            $resultado['estado'] = 'RECHAZADA_DIAN';
        }

        return $resultado;
    }

    private function estadoEnApiDian(string $identificador): array
    {
        $base = rtrim((string) $this->proveedor->server_url, '/');
        $criba = $this->enviar("{$base}/api/ubl2.1/status/document/{$identificador}");

        return $criba->json() ?? ['http' => $criba->status()];
    }

    /**
     * Envía con el token vigente. Si ApiDian responde 401/403 y hay
     * credenciales del portal, renueva el JWT (paso 00 de la colección:
     * POST /api/auth/login con email + password) y reintenta una vez.
     */
    private function enviar(string $url, ?array $payload = null): \Illuminate\Http\Client\Response
    {
        $respuesta = $this->peticion($url, $payload);

        if (in_array($respuesta->status(), [401, 403], true) && $this->puedeRenovarToken()) {
            $this->renovarToken();
            $respuesta = $this->peticion($url, $payload);
        }

        $this->abortarSiTokenInvalido($respuesta->status());

        return $respuesta;
    }

    private function peticion(string $url, ?array $payload): \Illuminate\Http\Client\Response
    {
        return \Illuminate\Support\Facades\Http::acceptJson()
            ->timeout(config('billing.timeout', 30))
            ->withToken((string) $this->proveedor->client_secret)
            ->post($url, $payload ?? []);
    }

    /** Sin token guardado pero con login del portal: se obtiene antes de enviar. */
    private function asegurarToken(): void
    {
        if ((string) $this->proveedor->client_secret === '' && $this->puedeRenovarToken()) {
            $this->renovarToken();
        }

        if ((string) $this->proveedor->client_secret === '') {
            throw new \RuntimeException("El proveedor {$this->proveedor->name} no tiene token de ApiDian configurado.");
        }
    }

    private function puedeRenovarToken(): bool
    {
        return (string) $this->proveedor->client_id !== ''
            && (string) $this->proveedor->client_password !== '';
    }

    private function renovarToken(): void
    {
        $token = $this->obtenerTokenDeLogin();

        $this->proveedor->withoutLogging(fn () => $this->proveedor->update(['client_secret' => $token]));
        $this->proveedor->refresh();
    }

    /**
     * Login del portal (paso 00 de la colección): POST /api/auth/login con
     * email + password devuelve un JWT. No persiste: lo usan el servicio al
     * guardar el proveedor y la renovación automática.
     */
    public function obtenerTokenDeLogin(): string
    {
        $base = rtrim((string) $this->proveedor->server_url, '/');
        if ($base === '') {
            throw new \RuntimeException("El proveedor {$this->proveedor->name} no tiene URL del API configurada.");
        }

        $login = \Illuminate\Support\Facades\Http::acceptJson()
            ->timeout(config('billing.timeout', 30))
            ->post("{$base}/api/auth/login", [
                'email' => (string) $this->proveedor->client_id,
                'password' => (string) $this->proveedor->client_password,
            ]);

        $token = $login->json('access_token');
        if ($login->failed() || ! is_string($token) || $token === '') {
            throw new \RuntimeException('No se pudo renovar el token de ApiDian: verifica el correo y la contraseña del portal en el proveedor tecnológico.');
        }

        return $token;
    }

    /**
     * Si el token sigue rechazado después de un intento de renovación, la
     * emisión debe decirlo con claridad en lugar de reportar un rechazo de la DIAN.
     */
    private function abortarSiTokenInvalido(int $http): void
    {
        if (in_array($http, [401, 403], true)) {
            throw new \RuntimeException('El token de ApiDian es inválido o expiró; actualízalo en el proveedor tecnológico de la empresa.');
        }
    }

    private function estadoDeConsulta(array $crudo): string
    {
        $datos = $crudo['data'] ?? $crudo;
        $estado = mb_strtoupper((string) ($datos['status'] ?? $datos['StatusCode'] ?? ''));

        if (str_contains($estado, 'ACE') || ($datos['IsValid'] ?? null) === true) {
            return 'ACEPTADA_DIAN';
        }

        if (str_contains($estado, 'RECH')) {
            return 'RECHAZADA_DIAN';
        }

        return $this->proveedor->send_to_dian ? 'ACEPTADA_DIAN' : 'VALIDADA_SIN_ENVIO';
    }

    private function hayErrores(mixed $datos): bool
    {
        foreach (['Errors', 'errors', 'statusMessage'] as $clave) {
            $valor = is_array($datos) ? ($datos[$clave] ?? null) : null;
            if (is_array($valor) && $valor !== []) {
                return true;
            }
            if (is_string($valor) && str_contains($valor, 'Rechazado')) {
                return true;
            }
        }

        return false;
    }

    private function sincronizarElectronicInvoice(CommercialInvoice $factura, array $respuesta): void
    {
        $fila = ElectronicInvoice::firstOrNew(['invoice_uuid' => $factura->uuid]);
        $fila->fill([
            'status' => $respuesta['estado'],
            'validated_at' => now(),
            'cufe' => $respuesta['cufe'],
            'provider_document_id' => $respuesta['zip_key'],
            'pdf_url' => $respuesta['pdf_url'],
            'raw_response' => $respuesta['crudo'],
        ]);
        $fila->withoutLogging(fn () => $fila->save());
    }

    private function mapeo(): ApiDianMapeo
    {
        return new ApiDianMapeo($this->proveedor->settings ?? []);
    }
}
