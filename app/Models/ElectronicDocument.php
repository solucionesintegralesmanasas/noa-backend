<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento electrónico genérico ApiDian (fase 3).
 *
 * Tipos de la colección Postman: documento soporte y su nota de ajuste,
 * equivalente POS y su nota, nómina 102/103, eventos RADIAN 030-051,
 * lote y Send-Zip. Facturas y notas NC/ND usan sus módulos propios
 * (CommercialInvoice, CreditNote, DebitNote), no esta tabla.
 */
class ElectronicDocument extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, LogsActivity;

    public const TIPO_SOPORTE = 'support-document';

    public const TIPO_SOPORTE_AJUSTE = 'support-adjustment-note';

    public const TIPO_EQUIVALENTE = 'equivalent-document';

    public const TIPO_EQUIVALENTE_AJUSTE = 'equivalent-adjustment-note';

    public const TIPO_NOMINA = 'payroll';

    public const TIPO_NOMINA_AJUSTE = 'payroll-adjust-note';

    public const TIPO_EVENTO = 'event';

    public const TIPO_LOTE = 'batch';

    public const TIPO_ZIP = 'send-zip';

    /** @var array<int, string> */
    public const TIPOS = [
        self::TIPO_SOPORTE,
        self::TIPO_SOPORTE_AJUSTE,
        self::TIPO_EQUIVALENTE,
        self::TIPO_EQUIVALENTE_AJUSTE,
        self::TIPO_NOMINA,
        self::TIPO_NOMINA_AJUSTE,
        self::TIPO_EVENTO,
        self::TIPO_LOTE,
        self::TIPO_ZIP,
    ];

    protected $table = 'electronic_documents';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'billing_provider_uuid',
        'api_document_type',
        'event_code',
        'document_number',
        'payload',
        'electronic_status',
        'document_key',
        'zip_key',
        'dian_response',
        'signed_xml',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'payload' => 'array',
        'dian_response' => 'array',
    ];

    /** Eventos RADIAN válidos (códigos 030-051 de la ruta /api/ubl2.1/event/{codigo}). */
    public static function eventoValido(?string $codigo): bool
    {
        return $codigo !== null && preg_match('/^(0[345]\d)$/', (string) $codigo) === 1;
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(BillingProvider::class, 'billing_provider_uuid', 'uuid');
    }

    /** Solo se puede editar mientras no se haya firmado. */
    public function editable(): bool
    {
        return $this->electronic_status === 'PENDIENTE_DIAN' && $this->document_key === null;
    }

    /** Solo se puede borrar mientras no se haya firmado ni emitido. */
    public function borrable(): bool
    {
        return $this->editable() && ! $this->dian_response;
    }
}
