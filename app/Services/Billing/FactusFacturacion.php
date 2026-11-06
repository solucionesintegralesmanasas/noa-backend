<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Models\CommercialInvoice;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\ElectronicInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Driver Factus: valida y transmite facturas y notas al API de Factus (v2).
 *
 * Enfoque exclusivo en servicios: la factura se emite como documento "01"
 * (factura de venta) con ítems de servicio. Factus calcula los totales y
 * asigna el consecutivo del rango (`numbering_range_id`).
 *
 * Estados que escribe en los documentos: ACEPTADA_DIAN, RECHAZADA_DIAN,
 * VALIDADA_SIN_ENVIO (transmisión a sandbox sin DIAN real) y PENDIENTE_DIAN
 * (la DIAN aún no responde: is_validated=false sin rechazo; se reintenta).
 *
 * Referencia: https://developers.factus.com.co/manejo-errores
 */
class FactusFacturacion implements ContratoFacturacionElectronica, ContratoNotasElectronica
{
    /** Endpoint de eliminación de documentos NO validados, por recurso. */
    private const ELIMINAR = [
        'bills' => 'bills/destroy/reference',
        'credit-notes' => 'credit-notes/reference',
        'debit-notes' => 'debit-notes/reference',
    ];

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
        $folio = $this->mapeo()->factura($factura, $this->rangoNumeracion('bills'));

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
        $numero = $factura->provider_document_id;
        if (! $numero) {
            return ['estado' => $factura->electronic_status, 'transmitido' => $this->proveedor->send_to_dian, 'detalle' => 'Documento sin número de Factus: nunca se validó'];
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
            'detalle' => 'En Factus la anulación se materializa emitiendo una nota crédito (concepto 2) contra esta factura.',
        ];
    }

    public function emitirNota(Model $nota): array
    {
        $esCredito = $nota instanceof CreditNote;
        if (! $esCredito && ! $nota instanceof DebitNote) {
            throw new \InvalidArgumentException('El documento no es una nota crédito ni débito.');
        }

        $recurso = $esCredito ? 'credit-notes' : 'debit-notes';
        $folio = $this->mapeo()->nota(
            $nota,
            $esCredito ? FactusMapeo::NOTA_CREDITO : FactusMapeo::NOTA_DEBITO,
            $this->rangoNumeracion($recurso),
        );

        $respuesta = $this->transmite($recurso, $folio);

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
        // Factus consulta notas por su número (data.number), no por el CUDE.
        $numero = (string) (Arr::get((array) ($nota->dian_response ?? []), 'data.number') ?? '');
        if ($numero === '') {
            return ['estado' => $nota->electronic_status, 'transmitido' => $this->proveedor->send_to_dian, 'detalle' => 'Nota sin número de Factus: nunca se validó'];
        }

        $crudo = $this->consultarEnFactus($tipo, $numero);

        return [
            'estado' => $this->estadoDeRespuesta($crudo),
            'transmitido' => $this->proveedor->send_to_dian,
            'cude' => $nota->cude,
            'numero' => $numero,
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
        $crudo = is_array($crudo) ? $crudo : [];

        $this->abortarSiErrorDeValidacion($respuestaHttp, $crudo);

        $datos = is_array($crudo['data'] ?? null) ? $crudo['data'] : $crudo;

        $resultado = [
            'http' => $respuestaHttp->status(),
            'cufe' => $datos['cufe'] ?? $datos['cude'] ?? null,
            'numero' => $datos['number'] ?? null,
            'pdf_url' => Arr::get($datos, 'links.public_url') ?? $datos['pdf_url'] ?? null,
            'xml_url' => $datos['xml_url'] ?? null,
            'qr' => Arr::get($datos, 'links.qr') ?? $datos['qr'] ?? null,
            'crudo' => $crudo,
        ];

        $resultado['estado'] = $this->estadoDeEmision($respuestaHttp, $datos, $crudo);

        if ($resultado['estado'] === 'RECHAZADA_DIAN') {
            $this->eliminarNoValidado($recurso, (string) ($payload['reference_code'] ?? ''));
        }

        return $resultado;
    }

    /**
     * 409: hay un documento pendiente con la misma referencia.
     * 422 "Validation error": datos incompletos/incorrectos; no es un rechazo de
     * la DIAN, se informa al usuario para que corrija (la transacción revierte).
     */
    private function abortarSiErrorDeValidacion(Response $respuestaHttp, array $crudo): void
    {
        if ($respuestaHttp->status() === 409) {
            throw new \RuntimeException('Factus reporta un documento pendiente por enviar a la DIAN con la misma referencia. Elimínalo en Factus y vuelve a intentar.');
        }

        $esValidacion = $respuestaHttp->status() === 422
            && str_contains(mb_strtolower((string) ($crudo['status'] ?? '')), 'validation');

        if (! $esValidacion) {
            return;
        }

        $errores = Arr::get($crudo, 'data.errors') ?? $crudo['errors'] ?? [];
        $mensajes = collect(Arr::wrap($errores))
            ->flatten()
            ->filter(fn ($m) => is_string($m) && $m !== '')
            ->unique()
            ->take(8)
            ->implode(' ');

        throw new \RuntimeException('Factus rechazó los datos del documento: '.($mensajes !== '' ? $mensajes : ($crudo['message'] ?? 'Error de validación')));
    }

    /** Elimina en Factus un documento rechazado; si no, Factus bloquea los siguientes envíos. */
    private function eliminarNoValidado(string $recurso, string $referencia): void
    {
        if ($referencia === '' || ! isset(self::ELIMINAR[$recurso])) {
            return;
        }

        try {
            $this->cliente->enviar('delete', "{$this->cliente->baseUrl()}/v2/".self::ELIMINAR[$recurso].'/'.rawurlencode($referencia));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function consultarEnFactus(string $recurso, string $numero): array
    {
        $ruta = "{$this->cliente->baseUrl()}/v2/{$recurso}/".rawurlencode($numero);
        $criba = $this->cliente->enviar('get', $ruta);

        return $criba->json() ?? ['http' => $criba->status()];
    }

    /**
     * Rango de numeración configurado para el recurso:
     * 1. settings.numbering_ranges.{bills|credit_notes|debit_notes}
     * 2. settings.numbering_range_id (solo facturas, compatibilidad)
     * Si no está configurado, se omite: según la documentación de Factus v2,
     * `numbering_range_id` es opcional cuando la empresa tiene un único rango
     * activo para ese documento.
     */
    private function rangoNumeracion(string $recurso): ?int
    {
        $ajustes = $this->proveedor->settings ?? [];
        $clave = str_replace('-', '_', $recurso);

        $configurado = Arr::get($ajustes, "numbering_ranges.{$clave}");
        if ($configurado === null && $recurso === 'bills') {
            $configurado = Arr::get($ajustes, 'numbering_range_id');
        }
        if ($configurado !== null && $configurado !== '') {
            return (int) $configurado;
        }

        return null;
    }

    private function estadoDeEmision(Response $respuestaHttp, array $datos, array $crudo): string
    {
        if (! $respuestaHttp->successful() || $this->hayRechazo($datos, $crudo)) {
            return 'RECHAZADA_DIAN';
        }

        return $this->estadoValidado($datos);
    }

    private function estadoDeRespuesta(array $crudo): string
    {
        $datos = is_array($crudo['data'] ?? null) ? $crudo['data'] : $crudo;

        if ($this->hayRechazo($datos, $crudo)) {
            return 'RECHAZADA_DIAN';
        }

        return $this->estadoValidado($datos);
    }

    /** v2: is_validated=true → validada; false sin rechazo → la DIAN no ha respondido. */
    private function estadoValidado(array $datos): string
    {
        if (($datos['is_validated'] ?? null) === false) {
            return 'PENDIENTE_DIAN';
        }

        return $this->proveedor->send_to_dian ? 'ACEPTADA_DIAN' : 'VALIDADA_SIN_ENVIO';
    }

    /**
     * Rechazo según Factus: el documento no quedó validado y `errors` trae una
     * regla con la palabra "Rechazo". Las "Notificación" (FAJ44b, RUT01…) son
     * avisos y no invalidan el documento.
     */
    private function hayRechazo(array $datos, array $crudo): bool
    {
        $estado = mb_strtolower((string) ($crudo['status'] ?? ''));
        if (str_contains($estado, 'rechaz') || str_contains($estado, 'reject')) {
            return true;
        }

        if (($datos['is_validated'] ?? null) === true) {
            return false;
        }

        $errores = $datos['errors'] ?? $crudo['errors'] ?? null;
        foreach (Arr::flatten(Arr::wrap($errores)) as $mensaje) {
            $texto = mb_strtolower((string) $mensaje);
            if (str_contains($texto, 'rechaz') || str_contains($texto, 'reject')) {
                return true;
            }
        }

        return false;
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

    private function mapeo(): FactusMapeo
    {
        return new FactusMapeo($this->proveedor->settings ?? []);
    }
}
