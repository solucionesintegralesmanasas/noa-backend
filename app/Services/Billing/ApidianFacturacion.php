<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Models\CommercialInvoice;
use App\Models\ElectronicInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;

/**
 * Driver ApiDian (FACTW): emite facturas de venta de servicios contra la API de
 * FACTW (`https://apidian.systemsfact.com.co`).
 *
 * Estados que escribe en la factura: ACEPTADA_DIAN, RECHAZADA_DIAN,
 * VALIDADA_SIN_ENVIO (send=false, sin transmisión a la DIAN) y PENDIENTE_DIAN
 * (status_code "99": la DIAN no respondió, se reintenta).
 *
 * `provider_document_id` guarda el `document_id` numérico de FACTW (las
 * descargas /documents/{id}/pdf|xml fallan con el número de consecutivo).
 */
class ApidianFacturacion implements ContratoFacturacionElectronica, ContratoNotasElectronica
{
    public function __construct(
        private readonly BillingProvider $proveedor
    ) {
        if (! $this->proveedor->usaApidian()) {
            throw new \InvalidArgumentException(
                "El proveedor {$this->proveedor->name} no usa ApiDian."
            );
        }
    }

    public function emitir(CommercialInvoice $factura): array
    {
        $payload = (new ApidianMapeo($this->proveedor->settings ?? []))->map($factura);
        // FACTW valida número Y resolución contra SU rango (FAB12): usamos su rango activo,
        // no el consecutivo ni la resolución internos de NOA.
        $rango = $this->rangoDeFacturacion();
        $payload['number'] = $rango['number'];
        $payload['resolution_number'] = $rango['resolution'];
        $payload['send'] = (bool) $this->proveedor->send_to_dian;

        $respuesta = $this->transmite('/api/ubl2.1/invoice', $payload);

        $factura->withoutLogging(fn () => $factura->update([
            'electronic_status' => $respuesta['estado'],
            'cufe' => $respuesta['cufe'],
            'provider_document_id' => $respuesta['numero'],
            'qr_dian' => $respuesta['qr'],
            'dian_response' => $respuesta['crudo'],
            'billing_provider_uuid' => $this->proveedor->uuid,
        ]));

        $this->sincronizarElectronicInvoice($factura->fresh(), $respuesta);

        return $respuesta;
    }

    public function consultarEstado(CommercialInvoice $factura): array
    {
        $numero = $factura->dian_response['number'] ?? $factura->dian_response['data']['number'] ?? null;
        if (! $numero) {
            return [
                'estado' => $factura->electronic_status,
                'transmitido' => $this->proveedor->send_to_dian,
                'detalle' => 'Documento sin número de ApiDian: nunca se validó.',
            ];
        }

        $cliente = new ApidianCliente($this->proveedor);
        $respuesta = $cliente->enviar('get', $cliente->baseUrl().'/api/ubl2.1/documents', ['number' => (string) $numero]);
        $crudo = $respuesta->json() ?? [];

        return [
            'estado' => $this->estadoDeConsulta($crudo),
            'transmitido' => $this->proveedor->send_to_dian,
            'cufe' => $factura->cufe,
            'numero' => $numero,
            'crudo' => $crudo,
        ];
    }

    /** La anulación ante la DIAN se materializa emitiendo una nota crédito. */
    public function anular(CommercialInvoice $factura, string $motivo): array
    {
        return [
            'estado' => $factura->electronic_status,
            'transmitido' => $this->proveedor->send_to_dian,
            'motivo' => $motivo,
            'detalle' => 'En ApiDian la anulación se materializa emitiendo una nota crédito (concepto 2) contra esta factura.',
        ];
    }

    public function emitirNota(Model $nota): array
    {
        throw new \RuntimeException('ApiDian aún no soporta notas crédito/débito en NOA. Usa el driver Factus o emite la nota manualmente.');
    }

    public function consultarEstadoNota(Model $nota): array
    {
        return ['estado' => $nota->electronic_status, 'detalle' => 'ApiDian no soporta notas en NOA.'];
    }

    /** Login con email + contraseña (lo usa el guardado del proveedor). */
    public function obtenerTokensDeLogin(): array
    {
        $resultado = (new ApidianCliente($this->proveedor))->obtenerTokensDeLogin();

        if ($resultado === null) {
            throw new \RuntimeException(
                'No se pudo iniciar sesión con ApiDian. Verifica el correo y la contraseña del proveedor tecnológico.'
            );
        }

        return $resultado;
    }

    // ─── transporte ────────────────────────────────────────────────────────

    /**
     * Rango activo de factura (type_document_id 1) de FACTW. FACTW valida número y
     * resolución contra SU rango (regla FAB12), no contra los internos de NOA.
     *
     * @return array{number:int, resolution:string}
     */
    private function rangoDeFacturacion(): array
    {
        $cliente = new ApidianCliente($this->proveedor);
        $respuesta = $cliente->enviar('get', $cliente->baseUrl().'/api/ubl2.1/numbering-ranges');
        $data = $respuesta->json() ?? [];

        $rangos = collect(is_array($data['data'] ?? null) ? $data['data'] : (is_array($data) ? $data : []));

        // Primer rango activo de factura por id, para ser determinista ante rangos solapados.
        $rango = $rangos
            ->where('type_document_id', 1)
            ->filter(fn ($r) => ($r['active'] ?? true))
            ->sortBy(fn ($r) => $r['id'] ?? 0)
            ->first();

        if ($rango === null) {
            throw new \RuntimeException('No se pudo obtener el rango de factura de ApiDian. Verifica la numeración en FACTW.');
        }

        $next = $rango['next'] ?? null;
        if ($next === null && isset($rango['next_consecutive'])) {
            $next = preg_replace('/\D/', '', (string) $rango['next_consecutive']);
        }

        if ($next === null || $next === '' || (int) $next <= 0) {
            throw new \RuntimeException('No se pudo obtener el consecutivo de ApiDian. Verifica el rango de numeración en FACTW.');
        }

        return [
            'number' => (int) $next,
            'resolution' => (string) ($rango['resolution'] ?? '0'),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function transmite(string $endpoint, array $payload): array
    {
        $cliente = new ApidianCliente($this->proveedor);
        $respuestaHttp = $cliente->enviar('post', $cliente->baseUrl().$endpoint, $payload);

        $crudo = $respuestaHttp->json();
        if (! is_array($crudo)) {
            $crudo = ['respuesta_no_json' => mb_strcut((string) $respuestaHttp->body(), 0, 500)];
        }

        $resultado = [
            'http' => $respuestaHttp->status(),
            'cufe' => $crudo['cufe'] ?? $crudo['uuid'] ?? $crudo['data']['cufe'] ?? null,
            'numero' => $crudo['document_id'] ?? $crudo['number'] ?? $crudo['data']['number'] ?? null,
            'pdf_url' => $crudo['pdf_download_link'] ?? $crudo['pdf_url'] ?? null,
            'qr' => $crudo['qr_link'] ?? $crudo['qr'] ?? null,
            'crudo' => $crudo,
        ];

        $resultado['estado'] = $this->estadoDeEmision($respuestaHttp, $crudo);

        return $resultado;
    }

    private function estadoDeEmision(Response $respuestaHttp, array $crudo): string
    {
        // No autenticado (401): las credenciales no son válidas. No es un rechazo del documento.
        if ($respuestaHttp->status() === 401) {
            throw new \RuntimeException('No se pudo autenticar con ApiDian. Verifica el correo y la contraseña del proveedor tecnológico.');
        }

        // Fault del canal DIAN (status_code "99"): la DIAN no respondió; se reintenta.
        if ((string) ($crudo['status_code'] ?? '') === '99') {
            return 'PENDIENTE_DIAN';
        }

        if ($respuestaHttp->failed() || ($crudo['is_valid'] ?? null) === false) {
            return 'RECHAZADA_DIAN';
        }

        if (! $this->proveedor->send_to_dian) {
            return 'VALIDADA_SIN_ENVIO';
        }

        return 'ACEPTADA_DIAN';
    }

    /**
     * state_document_id = 0 → la DIAN aún no procesa el documento (canal roto o
     * en cola). El resto del enum no está mapeado: se asume aceptado si hay estado.
     */
    private function estadoDeConsulta(array $crudo): string
    {
        $datos = is_array($crudo['data'] ?? null) ? $crudo['data'] : $crudo;
        $estadoId = $datos['state_document_id'] ?? null;

        if ($estadoId !== null && (int) $estadoId === 0) {
            return 'PENDIENTE_DIAN';
        }

        return $this->proveedor->send_to_dian ? 'ACEPTADA_DIAN' : 'VALIDADA_SIN_ENVIO';
    }

    private function sincronizarElectronicInvoice(CommercialInvoice $factura, array $respuesta): void
    {
        $validada = in_array($respuesta['estado'], ['ACEPTADA_DIAN', 'VALIDADA_SIN_ENVIO'], true);

        $fila = ElectronicInvoice::firstOrNew(['invoice_uuid' => $factura->uuid]);
        $fila->fill([
            'status' => $respuesta['estado'],
            'validated_at' => $validada ? now() : null,
            'cufe' => $respuesta['cufe'],
            'provider_document_id' => $respuesta['numero'],
            'pdf_url' => $respuesta['pdf_url'],
            'raw_response' => $respuesta['crudo'],
        ]);
        $fila->withoutLogging(fn () => $fila->save());
    }
}
