<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos electrónicos genéricos ApiDian (fase 3): documento soporte y
     * su nota de ajuste, documentos equivalentes POS y su nota, nómina 102/103,
     * eventos RADIAN (030-051), lote y zip de envío.
     *
     * El payload JSON llega ya construido (lo arma el llamador); NOA solo lo
     * guarda, lo transmite al driver del proveedor de la empresa y registra la
     * respuesta. Los modelos de negocio dedicados (nómina completa, soporte,
     * POS) llegarán en iteraciones posteriores sobre esta tabla.
     */
    public function up(): void
    {
        Schema::create('electronic_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('billing_provider_uuid')->nullable()->comment('Proveedor usado (apidian); nulo = aún sin asignar');
            $table->string('api_document_type', 40)->comment('Tipo de ruta ApiDian: support-document, support-adjustment-note, equivalent-document, equivalent-adjustment-note, payroll, payroll-adjust-note, event, batch, send-zip');
            $table->string('event_code', 3)->nullable()->comment('Código RADIAN 030-051 cuando api_document_type = event');
            $table->string('document_number', 50)->nullable()->comment('Número propio del documento (DS1001, POS99001, NIE-001...)');
            $table->json('payload')->comment('Payload JSON ya armado con el formato del proveedor');
            $table->string('electronic_status', 30)->default('PENDIENTE_DIAN')->comment('PENDIENTE_DIAN, VALIDADA_SIN_ENVIO, ACEPTADA_DIAN, RECHAZADA_DIAN');
            $table->string('document_key', 100)->nullable()->comment('CUFE/CUDE/CUNE devuelto por ApiDian');
            $table->string('zip_key', 100)->nullable();
            $table->json('dian_response')->nullable();
            $table->longText('signed_xml')->nullable()->comment('XML firmado (base64) devuelto por ApiDian');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_uuid');
            $table->index(['company_uuid', 'api_document_type']);
            $table->index('document_number');
            $table->foreign('company_uuid')->references('uuid')->on('companies')->cascadeOnDelete();
            $table->foreign('billing_provider_uuid')->references('uuid')->on('billing_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_documents');
    }
};
