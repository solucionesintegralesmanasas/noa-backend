<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Respuesta del proveedor tecnológico / DIAN (fase posterior: solo se crea en PENDIENTE).
        Schema::create('electronic_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('invoice_uuid')->unique();
            $table->string('status', 30)->default('PENDIENTE');
            $table->dateTime('validated_at')->nullable();
            $table->string('cufe', 100)->nullable();
            $table->string('provider_document_id', 100)->nullable();
            $table->string('xml_url', 500)->nullable();
            $table->string('pdf_url', 500)->nullable();
            $table->json('errors')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->foreign('invoice_uuid')->references('uuid')->on('commercial_invoices')->cascadeOnDelete();
        });

        foreach (['credit' => 'CREDITO', 'debit' => 'DEBITO'] as $slug => $label) {
            $notes = "{$slug}_notes";
            $items = "{$slug}_note_items";
            $withholdings = "{$slug}_note_item_withholdings";

            Schema::create($notes, function (Blueprint $table) use ($slug) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('company_uuid');
                $table->uuid('invoice_uuid')->comment('Factura comercial afectada');
                $table->uuid('third_party_uuid')->nullable();
                $table->string('note_number', 30);
                $table->string('correction_concept_code', 5)->comment('Código DIAN de corrección');
                $table->string('observation', 250)->nullable()->comment('Motivo visible');
                $table->date('issue_date');
                $table->uuid('payment_method_uuid')->nullable();
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('total_discount', 18, 2)->default(0);
                $table->decimal('total_tax', 18, 2)->default(0);
                $table->decimal('total_withholding', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->enum('status', ['BORRADOR', 'EMITIDA_INTERNA', 'ANULADA'])->default('BORRADOR');
                $table->string('electronic_status', 30)->default('PENDIENTE_DIAN');
                $table->string('cude', 100)->nullable();
                $table->json('dian_response')->nullable();
                $table->timestamps();

                $table->unique(['company_uuid', 'note_number'], "{$slug}_company_number_unique");
                $table->index('invoice_uuid');
                $table->foreign('company_uuid')->references('uuid')->on('companies')->cascadeOnDelete();
                $table->foreign('invoice_uuid')->references('uuid')->on('commercial_invoices')->restrictOnDelete();
                $table->foreign('third_party_uuid')->references('uuid')->on('third_parties')->nullOnDelete();
                $table->foreign('payment_method_uuid')->references('uuid')->on('payment_methods')->nullOnDelete();
            });

            Schema::create($items, function (Blueprint $table) use ($notes, $slug) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('note_uuid');
                $table->unsignedInteger('order_index')->default(1);
                $table->string('description', 500);
                $table->uuid('measurement_unit_uuid')->nullable();
                $table->decimal('quantity', 14, 4)->default(1);
                $table->decimal('unit_price', 18, 4)->default(0);
                $table->decimal('discount_amount', 18, 2)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('line_total', 18, 2)->default(0);
                $table->uuid('tax_type_uuid')->nullable();
                $table->uuid('tribute_uuid')->nullable();
                $table->timestamps();

                $table->index('note_uuid');
                $table->foreign('note_uuid')->references('uuid')->on($notes)->cascadeOnDelete();
                $table->foreign('measurement_unit_uuid')->references('uuid')->on('measurement_units')->nullOnDelete();
                $table->foreign('tax_type_uuid')->references('uuid')->on('tax_types')->nullOnDelete();
                $table->foreign('tribute_uuid')->references('uuid')->on('tributes')->nullOnDelete();
            });

            Schema::create($withholdings, function (Blueprint $table) use ($items) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('note_item_uuid');
                $table->uuid('withholding_uuid')->nullable();
                $table->string('withholding_type', 50);
                $table->decimal('withholding_rate', 8, 6)->default(0);
                $table->decimal('amount', 18, 2)->default(0);
                $table->timestamps();

                $table->index('note_item_uuid');
                $table->foreign('note_item_uuid')->references('uuid')->on($items)->cascadeOnDelete();
                $table->foreign('withholding_uuid')->references('uuid')->on('withholdings')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('debit_note_item_withholdings');
        Schema::dropIfExists('debit_note_items');
        Schema::dropIfExists('debit_notes');
        Schema::dropIfExists('credit_note_item_withholdings');
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('electronic_invoices');
    }
};
