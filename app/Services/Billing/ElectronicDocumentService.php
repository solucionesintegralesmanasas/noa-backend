<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Models\ElectronicDocument;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;

/**
 * Documentos electrónicos genéricos ApiDian (fase 3).
 *
 * El payload llega ya armado por el llamador (documento soporte, equivalentes
 * POS, nómina 102/103, eventos RADIAN, lote, zip). El servicio lo guarda,
 * lo transmite por el driver ApiDian de la empresa y registra la respuesta.
 * Facturas y notas usan sus módulos propios, no este servicio.
 *
 * Reglas:
 * - Solo se emite una vez (editable() exige PENDIENTE_DIAN sin clave).
 * - Solo se edita/borra mientras no esté firmado (borrable()).
 * - send_to_dian lo decide el proveedor de la empresa; el cliente no lo envía.
 */
class ElectronicDocumentService extends BaseService
{
    protected array $searchableFields = ['document_number', 'api_document_type', 'electronic_status'];

    /** Tipo API -> último segmento de la ruta ApiDian antes del test_set_id. */
    private const RUTAS_API = [
        ElectronicDocument::TIPO_SOPORTE => 'support-document',
        ElectronicDocument::TIPO_SOPORTE_AJUSTE => 'support-adjustment-note',
        ElectronicDocument::TIPO_EQUIVALENTE => 'equivalent-document',
        ElectronicDocument::TIPO_EQUIVALENTE_AJUSTE => 'equivalent-adjustment-note',
        ElectronicDocument::TIPO_NOMINA_AJUSTE => 'payroll-adjust-note',
        ElectronicDocument::TIPO_LOTE => 'batch',
        ElectronicDocument::TIPO_ZIP => 'send-zip',
    ];

    protected function getModelInstance(): Model
    {
        return new ElectronicDocument;
    }

    public function create(array $attributes): Model
    {
        $this->validarTipo($attributes['api_document_type'] ?? '', $attributes['event_code'] ?? null);

        return parent::create($attributes);
    }

    /** Registra el documento sin transmitirlo (queda PENDIENTE_DIAN). */
    public function inscribir(array $datos): Model
    {
        $datos['electronic_status'] = 'PENDIENTE_DIAN';

        return $this->create($datos);
    }

    /**
     * Firmar y (según send_to_dian del proveedor) transmitir el documento.
     *
     * @throws \RuntimeException si la empresa no tiene proveedor ApiDian activo.
     */
    public function emitir(string $uuid): Model
    {
        return $this->transaction(function () use ($uuid) {
            /** @var ElectronicDocument|null $documento */
            $documento = $this->findByUuid($uuid);
            if (! $documento) {
                throw new \RuntimeException('El documento electrónico no existe.');
            }
            if (! $documento->editable()) {
                throw new \RuntimeException('El documento ya fue firmado; no se puede volver a emitir.');
            }

            $proveedor = $this->proveedorDe($documento);
            if (! $proveedor) {
                throw new \RuntimeException('La empresa no tiene proveedor tecnológico ApiDian activo.');
            }

            $emisor = new ApiDianFacturacion($proveedor);
            $respuesta = $emisor->emitirDocumento($this->rutaEmitir($documento), $documento->payload ?? []);

            $documento->withoutLogging(fn () => $documento->update([
                'billing_provider_uuid' => $proveedor->uuid,
                'electronic_status' => $respuesta['estado'],
                'document_key' => $respuesta['cufe'],
                'zip_key' => $respuesta['zip_key'],
                'dian_response' => $respuesta['crudo'],
                'signed_xml' => $respuesta['xml_base64'],
            ]));

            return $documento->fresh();
        });
    }

    /** Consulta el estado DIAN de la clave del documento (CUFE/CUDE/CUNE). */
    public function estado(string $uuid): array
    {
        /** @var ElectronicDocument|null $documento */
        $documento = $this->findByUuid($uuid);
        if (! $documento) {
            throw new \RuntimeException('El documento electrónico no existe.');
        }
        if (! $documento->document_key) {
            return ['estado' => $documento->electronic_status, 'detalle' => 'Documento sin clave: nunca se firmó'];
        }

        $proveedor = $documento->proveedor;
        if (! $proveedor?->usaApiDian()) {
            $proveedor = $this->proveedorDe($documento);
        }
        if (! $proveedor?->usaApiDian()) {
            throw new \RuntimeException('El documento no tiene proveedor ApiDian asociado.');
        }

        return (new ApiDianFacturacion($proveedor))->consultarClave($documento->document_key);
    }

    /** Actualiza solo los campos del payload mientras el documento siga sin firmar. */
    public function update(string $uuid, array $attributes): bool
    {
        $documento = $this->findByUuid($uuid);
        if (! $documento) {
            return false;
        }
        if (! $documento->editable()) {
            throw new \RuntimeException('El documento ya fue firmado; no se puede editar.');
        }
        $attributes = $this->bajoAtributos($documento, $attributes);
        if (! in_array($attributes['api_document_type'] ?? $documento->api_document_type, ElectronicDocument::TIPOS, true)) {
            throw new \RuntimeException('Tipo de documento electrónico inválido.');
        }

        return parent::update($uuid, $attributes);
    }

    /** Borrado solo mientras no esté firmado ni transmitido. */
    public function delete(string $uuid): bool
    {
        $documento = $this->findByUuid($uuid);
        if (! $documento) {
            return false;
        }
        if (! $documento->borrable()) {
            throw new \RuntimeException('El documento ya tiene respuesta del proveedor; no se puede eliminar.');
        }

        return parent::delete($uuid);
    }

    // ─── apoyo ────────────────────────────────────────────────────────────

    private function validarTipo(string $tipo, mixed $codigoEvento): void
    {
        if (! in_array($tipo, ElectronicDocument::TIPOS, true)) {
            throw new \RuntimeException("Tipo de documento electrónico inválido: {$tipo}");
        }

        if ($tipo === ElectronicDocument::TIPO_EVENTO && ! ElectronicDocument::eventoValido(is_string($codigoEvento) ? $codigoEvento : null)) {
            throw new \RuntimeException('Un evento RADIAN exige un código 030 a 051.');
        }
    }

    private function rutaEmitir(ElectronicDocument $documento): string
    {
        return match ($documento->api_document_type) {
            ElectronicDocument::TIPO_NOMINA => 'payroll/102',
            ElectronicDocument::TIPO_EVENTO => 'event/'.$documento->event_code,
            default => self::RUTAS_API[$documento->api_document_type],
        };
    }

    private function proveedorDe(ElectronicDocument $documento): ?BillingProvider
    {
        $asignado = $documento->proveedor;
        if ($asignado && $asignado->is_active) {
            return $asignado;
        }

        return BillingProvider::query()
            ->where('company_uuid', $documento->company_uuid)
            ->where('is_active', true)
            ->where('driver', BillingProvider::DRIVER_APIDIAN)
            ->orderBy('id')
            ->first();
    }

    /** El cliente no puede cambiar empresa, proveedor, estado, ni la respuesta DIAN. */
    private function bajoAtributos(ElectronicDocument $documento, array $attributes): array
    {
        unset(
            $attributes['company_uuid'],
            $attributes['electronic_status'],
            $attributes['document_key'],
            $attributes['zip_key'],
            $attributes['dian_response'],
            $attributes['signed_xml'],
        );

        // El proveedor se revalida en emitir(); si el cliente lo cambia, debe ser de su empresa.
        if (isset($attributes['billing_provider_uuid']) && $attributes['billing_provider_uuid'] !== $documento->billing_provider_uuid) {
            $tuyo = BillingProvider::query()
                ->where('uuid', $attributes['billing_provider_uuid'])
                ->where('company_uuid', $documento->company_uuid)
                ->exists();
            if (! $tuyo) {
                throw new \RuntimeException('El proveedor indicado no pertenece a la empresa del documento.');
            }
        }

        return $attributes;
    }
}
