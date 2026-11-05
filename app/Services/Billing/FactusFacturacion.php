<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Models\CommercialInvoice;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\ElectronicInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Driver Factus: valida y transmite facturas y notas al API de Factus.
 *
 * Enfoque exclusivo en servicios: la factura se emite como documento "01"
 * (factura de venta) con ítems de servicio. Factus calcula los totales y
 * asigna el consecutivo del rango (`numbering_range_id`).
 *
 * Estados que escribe en los documentos: ACEPTADA_DIAN, RECHAZADA_DIAN,
 * VALIDADA_SIN_ENVIO (transmisión a sandbox sin DIAN real).
 */
class FactusFacturacion implements ContratoFacturacionElectronica, ContratoNotasElectronica
{
    private readonly FactusCliente $cliente;

    public function __construct(private readonly BillingProvider $proveedor)
    {
        if (! $this->proveedor->usaFactus()) {
            throw new \InvalidArgumentException("El proveedor {$this->proveedor->name} no usa Factus.");
        }

        $this->cliente = new FactusCliente($this->proveedor);
    }

    public function emitir(CommercialInvoice $factura): array
    {
        $folio = $this->mapeo()->factura($factura);

        $respuesta = $this->transmite('bills', $folio);

        $factura->withoutLogging(fn () => $factura->update([
            'electronic_status' => $respuesta['estado'],
            'cufe' => $respuesta['cufe'],
            'provider_document_id' => $respuesta['numero'],
            'dian_response' => $respuesta['crudo'],
            'billing_provider_uuid' => $this->proveedor->uuid,
        ]));

        $this->sincronizarElectronicInvoice($factura->fresh(), $respuesta);

        return $respuesta;
    }

    public function consultarEstado(CommercialInvoice $factura): array
    {
        $numero = $factura->provider_document_id ?: $factura->cufe;
        if (! $numero) {
            return ['estado' => $factura->electronic_status, 'transmitido' => $this->proveedor->send_to_dian, 'detalle' => 'Documento sin número ni CUFE: nunca se validó'];
        }

        $crudo = $this->consultarEnFactus('bills', (string) $numero);

        return [
            'estado' => $this->estadoDeRespuesta($crudo),
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
            'detalle' => 'En Factus la anulación se materializa emitiendo una nota crédito contra esta factura.',
        ];
    }

    public function emitirNota(Model $nota): array
    {
        $esCredito = $nota instanceof CreditNote;
        if (! $esCredito && ! $nota instanceof DebitNote) {
            throw new \InvalidArgumentException('El documento no es una nota crédito ni débito.');
        }

        $folio = $this->mapeo()->nota($nota, '20');

        $respuesta = $this->transmite($esCredito ? 'credit-notes' : 'debit-notes', $folio);

        $nota->withoutLogging(fn () => $nota->update([
            'electronic_status' => $respuesta['estado'],
            'cude' => $respuesta['cufe'],
            'dian_response' => $respuesta['crudo'],
        ]));

        return $respuesta;
    }

    public function consultarEstadoNota(Model $nota): array
    {
        $tipo = $nota instanceof CreditNote ? 'credit-notes' : 'debit-notes';
        $numero = (string) ($nota->cude ?? '');
        if ($numero === '') {
            return ['estado' => $nota->electronic_status, 'transmitido' => $this->proveedor->send_to_dian, 'detalle' => 'Nota sin CUDE: nunca se validó'];
        }

        $crudo = $this->consultarEnFactus($tipo, $numero);

        return [
            'estado' => $this->estadoDeRespuesta($crudo),
            'transmitido' => $this->proveedor->send_to_dian,
            'cude' => $numero,
            'crudo' => $crudo,
        ];
    }

    /** Login OAuth2 (delega en el cliente): lo usa el guardado del proveedor. */
    public function obtenerTokensDeLogin(): array
    {
        return $this->cliente->obtenerTokensDeLogin();
    }

    // ─── transporte ────────────────────────────────────────────────────────

    /** @param 'bills'|'credit-notes'|'debit-notes' $recurso */
    private function transmite(string $recurso, array $payload): array
    {
        $ruta = "{$this->cliente->baseUrl()}/v2/{$recurso}/validate";

        $respuestaHttp = $this->cliente->enviar('post', $ruta, $payload);

        $crudo = $respuestaHttp->json() ?? [];
        if ($crudo === [] && $respuestaHttp->body() !== '') {
            $crudo = ['respuesta_no_json' => Str::limit($respuestaHttp->body(), 500)];
        }

        $datos = is_array($crudo) ? ($crudo['data'] ?? $crudo) : [];

        $resultado = [
            'http' => $respuestaHttp->status(),
            'cufe' => $datos['cufe'] ?? $datos['cude'] ?? null,
            'numero' => $datos['number'] ?? null,
            'pdf_url' => $datos['pdf_url'] ?? $datos['pdf'] ?? null,
            'xml_url' => $datos['xml_url'] ?? $datos['xml'] ?? null,
            'qr' => $datos['qr'] ?? $datos['qr_url'] ?? null,
            'crudo' => $crudo,
        ];

        $resultado['estado'] = $this->estadoDeEmision($respuestaHttp, $datos, $crudo);

        return $resultado;
    }

    private function consultarEnFactus(string $recurso, string $numero): array
    {
        $ruta = "{$this->cliente->baseUrl()}/v2/{$recurso}/{$numero}";
        $criba = $this->cliente->enviar('get', $ruta);

        return $criba->json() ?? ['http' => $criba->status()];
    }

    private function estadoDeEmision(mixed $respuestaHttp, array $datos, array $crudo): string
    {
        if ($respuestaHttp->status() === 409) {
            throw new \RuntimeException('Factus reporta un documento pendiente por enviar a la DIAN con la misma referencia. Elimínalo en Factus y vuelve a intentar.');
        }

        if (! $respuestaHttp->successful() || $this->hayRechazo($datos, $crudo)) {
            return 'RECHAZADA_DIAN';
        }

        return $this->proveedor->send_to_dian ? 'ACEPTADA_DIAN' : 'VALIDADA_SIN_ENVIO';
    }

    private function estadoDeRespuesta(array $crudo): string
    {
        $datos = is_array($crudo) ? ($crudo['data'] ?? $crudo) : [];

        if ($this->hayRechazo($datos, $crudo)) {
            return 'RECHAZADA_DIAN';
        }

        if (($datos['is_validated'] ?? null) === true || ($crudo['status'] ?? null) === 'created') {
            return $this->proveedor->send_to_dian ? 'ACEPTADA_DIAN' : 'VALIDADA_SIN_ENVIO';
        }

        return $this->proveedor->send_to_dian ? 'ACEPTADA_DIAN' : 'VALIDADA_SIN_ENVIO';
    }

    private function hayRechazo(array $datos, array $crudo): bool
    {
        $estado = mb_strtolower((string) ($crudo['status'] ?? ''));

        if (str_contains($estado, 'rechaz') || str_contains($estado, 'reject')) {
            return true;
        }

        if (($datos['is_validated'] ?? null) === false) {
            $errores = $datos['errors'] ?? null;
            if (is_array($errores) && $errores !== []) {
                return true;
            }
        }

        foreach (['errors', 'Errors', 'message'] as $clave) {
            $valor = $datos[$clave] ?? $crudo[$clave] ?? null;
            if (is_array($valor) && $valor !== []) {
                return true;
            }
            if (is_string($valor) && (str_contains(mb_strtolower($valor), 'rechaz') || str_contains(mb_strtolower($valor), 'reject'))) {
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
            'provider_document_id' => $respuesta['numero'],
            'pdf_url' => $respuesta['pdf_url'],
            'raw_response' => $respuesta['crudo'],
        ]);
        $fila->withoutLogging(fn () => $fila->save());
    }

    private function mapeo(): FactusMapeo
    {
        return new FactusMapeo($this->proveedor->settings ?? []);
    }
}
