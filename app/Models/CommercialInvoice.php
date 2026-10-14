<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Documento comercial: COTIZACION (fase 1) y FACTURA_VENTA interna (fase 2).
 *
 * La factura electrónica (CUFE, validación DIAN) queda reservada a la fase
 * del proveedor tecnológico: esos campos permanecen nulos por ahora.
 */
class CommercialInvoice extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    public const TIPO_COTIZACION = 'COTIZACION';

    public const TIPO_FACTURA = 'FACTURA_VENTA';

    public const TIPO_REMISION = 'REMISION';

    protected $table = 'commercial_invoices';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'branch_uuid',
        'invoice_type',
        'status',
        'invoice_number',
        'billing_resolution_uuid',
        'issue_date',
        'valid_until',
        'due_date',
        'third_party_uuid',
        'project_uuid',
        'contractor_uuid',
        'object_contract_uuid',
        'vehicle_uuid',
        'origin_route',
        'destination_route',
        'service_start',
        'service_end',
        'origin_uuid',
        'payment_method_uuid',
        'subtotal',
        'total_discount',
        'total_tax',
        'total_withholding',
        'invoice_total',
        'amount_paid',
        'electronic_status',
        'cufe',
        'qr_dian',
        'dian_response',
        'billing_provider_uuid',
        'provider_document_id',
        'ubl_document',
        'notes',
        'terms',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'valid_until' => 'date',
        'due_date' => 'date',
        'service_start' => 'date',
        'service_end' => 'date',
        'subtotal' => 'float',
        'total_discount' => 'float',
        'total_tax' => 'float',
        'total_withholding' => 'float',
        'invoice_total' => 'float',
        'amount_paid' => 'float',
        'dian_response' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CommercialInvoiceItem::class, 'invoice_uuid', 'uuid')->orderBy('order_index');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CommercialInvoicePayment::class, 'invoice_uuid', 'uuid');
    }

    public function relatedDocuments(): HasMany
    {
        return $this->hasMany(InvoiceRelatedDocument::class, 'invoice_uuid', 'uuid');
    }

    public function electronicInvoice(): HasOne
    {
        return $this->hasOne(ElectronicInvoice::class, 'invoice_uuid', 'uuid');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_uuid', 'uuid');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_uuid', 'uuid');
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(BillingResolution::class, 'billing_resolution_uuid', 'uuid');
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'origin_uuid', 'uuid');
    }

    public function esCotizacion(): bool
    {
        return $this->invoice_type === self::TIPO_COTIZACION;
    }

    public function esFactura(): bool
    {
        return $this->invoice_type === self::TIPO_FACTURA;
    }

    public function saldo(): float
    {
        return round($this->invoice_total - $this->amount_paid, 2);
    }
}
