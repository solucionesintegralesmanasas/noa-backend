<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('branch_uuid')->nullable();
            // COTIZACION = fase 1, FACTURA_VENTA = fase 2 (interna, sin FE), REMISION = entrega sin cobro.
            $table->enum('invoice_type', ['COTIZACION', 'REMISION', 'FACTURA_VENTA'])->default('COTIZACION');
            $table->enum('status', ['BORRADOR', 'ENVIADA', 'APROBADA', 'RECHAZADA', 'VENCIDA', 'CONVERTIDA', 'EMITIDA_INTERNA', 'PAGADA', 'PAGADA_PARCIAL', 'ANULADA'])->default('BORRADOR');
            $table->string('invoice_number', 30);
            $table->uuid('billing_resolution_uuid')->nullable();
            $table->date('issue_date');
            $table->date('valid_until')->nullable()->comment('Vigencia de la cotización');
            $table->date('due_date')->nullable()->comment('Vencimiento de la factura');
            $table->uuid('third_party_uuid')->comment('Cliente');
            $table->uuid('project_uuid')->nullable();
            $table->uuid('contractor_uuid')->nullable();
            $table->uuid('object_contract_uuid')->nullable();
            $table->uuid('vehicle_uuid')->nullable();
            $table->string('origin_route', 255)->nullable();
            $table->string('destination_route', 255)->nullable();
            $table->date('service_start')->nullable();
            $table->date('service_end')->nullable();
            $table->uuid('origin_uuid')->nullable()->comment('Cotización origen cuando la factura se convierte');
            $table->uuid('payment_method_uuid')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total_discount', 18, 2)->default(0);
            $table->decimal('total_tax', 18, 2)->default(0);
            $table->decimal('total_withholding', 18, 2)->default(0);
            $table->decimal('invoice_total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            // Reservados para el proveedor tecnológico (fase posterior): siempre nulos por ahora.
            $table->string('electronic_status', 30)->default('PENDIENTE_DIAN');
            $table->string('cufe', 100)->nullable();
            $table->text('qr_dian')->nullable();
            $table->json('dian_response')->nullable();
            $table->uuid('billing_provider_uuid')->nullable();
            $table->string('provider_document_id', 100)->nullable();
            $table->longText('ubl_document')->nullable()->comment('UBL 2.1 generado y guardado sin transmitir');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamps();

            $table->unique(['company_uuid', 'invoice_type', 'invoice_number'], 'ci_company_type_number_unique');
            $table->index('third_party_uuid');
            $table->index('project_uuid');
            $table->foreign('company_uuid')->references('uuid')->on('companies')->cascadeOnDelete();
            $table->foreign('third_party_uuid')->references('uuid')->on('third_parties')->restrictOnDelete();
            $table->foreign('billing_resolution_uuid')->references('uuid')->on('billing_resolutions')->nullOnDelete();
            $table->foreign('payment_method_uuid')->references('uuid')->on('payment_methods')->nullOnDelete();
            $table->foreign('billing_provider_uuid')->references('uuid')->on('billing_providers')->nullOnDelete();
        });

        Schema::create('commercial_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('invoice_uuid');
            $table->unsignedInteger('order_index')->default(1);
            $table->string('description', 500);
            $table->uuid('measurement_unit_uuid')->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0)->comment('Tarifa aplicada; 0 = excluido (transporte especial)');
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->uuid('tax_type_uuid')->nullable();
            $table->uuid('tribute_uuid')->nullable();
            $table->uuid('fuec_uuid')->nullable()->comment('Servicio origen; no se factura dos veces el mismo FUEC');
            $table->uuid('control_sheet_uuid')->nullable()->comment('Planilla origen; no se factura dos veces');
            $table->uuid('vehicle_uuid')->nullable();
            $table->timestamps();

            $table->index('invoice_uuid');
            $table->foreign('invoice_uuid')->references('uuid')->on('commercial_invoices')->cascadeOnDelete();
            $table->foreign('measurement_unit_uuid')->references('uuid')->on('measurement_units')->nullOnDelete();
            $table->foreign('tax_type_uuid')->references('uuid')->on('tax_types')->nullOnDelete();
            $table->foreign('tribute_uuid')->references('uuid')->on('tributes')->nullOnDelete();
        });

        Schema::create('commercial_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('invoice_uuid');
            $table->decimal('amount', 18, 2);
            $table->date('payment_date');
            $table->uuid('payment_method_uuid')->nullable();
            $table->string('reference', 100)->nullable();
            $table->timestamps();

            $table->index('invoice_uuid');
            $table->foreign('invoice_uuid')->references('uuid')->on('commercial_invoices')->cascadeOnDelete();
            $table->foreign('payment_method_uuid')->references('uuid')->on('payment_methods')->nullOnDelete();
        });

        Schema::create('commercial_invoice_item_withholdings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('invoice_item_uuid');
            $table->uuid('withholding_uuid')->nullable();
            $table->string('withholding_type', 50);
            $table->decimal('withholding_rate', 8, 6)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();

            $table->index('invoice_item_uuid');
            $table->foreign('invoice_item_uuid')->references('uuid')->on('commercial_invoice_items')->cascadeOnDelete();
            $table->foreign('withholding_uuid')->references('uuid')->on('withholdings')->nullOnDelete();
        });

        Schema::create('invoice_related_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('invoice_uuid');
            $table->string('code', 50)->comment('Código DIAN del antecedente');
            $table->date('issue_date');
            $table->string('number', 50);
            $table->timestamps();

            $table->index('invoice_uuid');
            $table->foreign('invoice_uuid')->references('uuid')->on('commercial_invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_related_documents');
        Schema::dropIfExists('commercial_invoice_item_withholdings');
        Schema::dropIfExists('commercial_invoice_payments');
        Schema::dropIfExists('commercial_invoice_items');
        Schema::dropIfExists('commercial_invoices');
    }
};
