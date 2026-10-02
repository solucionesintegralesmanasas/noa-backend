<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_provision_contracts', function (Blueprint $table) {
            $table->id()->comment('Contrato de prestación de servicios (segunda tabla separada)');
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('procedure_uuid');
            $table->uuid('vehicle_uuid')->nullable();
            $table->uuid('third_party_uuid')->nullable()->comment('Contratante / cliente');
            $table->string('contract_number', 50)->unique();
            $table->date('issue_date');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('duration')->comment('Duración en días');
            $table->decimal('valuation_amount', 12, 2)->default(0);
            $table->string('coverage', 255)->default('NACIONAL');
            $table->text('object_description')->nullable();
            $table->enum('status', ['BORRADOR', 'PENDIENTE_FIRMA', 'FIRMADO', 'VENCIDO', 'CANCELADO'])->default('BORRADOR');
            $table->string('document_hash', 128)->nullable()->comment('SHA256 del PDF para inviolabilidad');
            $table->timestamps();
        });

        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->enum('contract_origin', ['ADMIN_FLOTA', 'PRESTACION']);
            $table->uuid('contract_uuid')->comment('UUID en su tabla origen');
            $table->enum('signer_role', ['PROPIETARIO', 'REP_LEGAL', 'CLIENTE', 'TESTIGO']);
            $table->string('signer_name', 255);
            $table->string('signer_document', 50);
            $table->string('signer_email', 255)->nullable();
            $table->string('signer_phone', 50)->nullable();
            $table->string('token_hash', 128)->unique()->comment('SHA256 del token de enlace');
            // Nullable y sin `ON UPDATE CURRENT_TIMESTAMP`: la vigencia del enlace se fija al
            // crearlo y no debe reiniciarse al actualizar la fila (al firmarla).
            // La migración 2026_10_02_000007 corrige las bases ya creadas.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('document_hash', 128)->nullable();
            $table->text('signature_data')->nullable()->comment('Base64 trazo canvas');
            $table->enum('status', ['PENDIENTE', 'FIRMADO', 'EXPIRADO', 'REVOCADO'])->default('PENDIENTE');
            $table->timestamps();
        });

        Schema::create('runt_txt_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('procedure_uuid');
            $table->enum('origin', ['ADMIN_FLOTA', 'PRESTACION']);
            $table->uuid('contract_uuid');
            $table->unsignedInteger('sequence')->default(1);
            $table->mediumText('content')->comment('Contenido del .txt Portal TO');
            $table->string('content_hash', 128);
            $table->text('runt_response')->nullable();
            $table->enum('status', ['GENERADO', 'ENVIADO', 'ACEPTADO', 'RECHAZADO'])->default('GENERADO');
            $table->timestamps();
        });

        Schema::table('procedures', function (Blueprint $table) {
            if (! Schema::hasColumn('procedures', 'parent_procedure_uuid')) {
                $table->uuid('parent_procedure_uuid')->nullable()->after('uuid');
            }
            if (! Schema::hasColumn('procedures', 'current_step')) {
                $table->string('current_step', 50)->nullable()->after('procedure_type');
            }
            if (! Schema::hasColumn('procedures', 'global_status')) {
                $table->string('global_status', 50)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runt_txt_batches');
        Schema::dropIfExists('contract_signatures');
        Schema::dropIfExists('service_provision_contracts');
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropColumn(['parent_procedure_uuid', 'current_step', 'global_status']);
        });
    }
};
