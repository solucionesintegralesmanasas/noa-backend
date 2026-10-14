<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('server_url', 200)->nullable();
            $table->string('client_id', 200)->nullable();
            $table->string('client_secret', 255)->nullable();
            $table->string('email', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->uuid('company_uuid')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('company_uuid');
            $table->foreign('company_uuid')->references('uuid')->on('companies')->nullOnDelete();
        });

        Schema::create('billing_resolutions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('branch_uuid')->nullable();
            $table->string('resolution_type_code', 10)->comment('Código DIAN: 01 factura, 03 transmisión, 91 soporte');
            $table->string('resolution_number', 50)->nullable()->comment('Número oficial DIAN; nulo = rango interno');
            $table->string('invoice_prefix', 10);
            $table->unsignedBigInteger('invoice_start_number');
            $table->unsignedBigInteger('invoice_end_number');
            $table->unsignedBigInteger('last_consecutive')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->uuid('billing_provider_uuid')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('company_uuid');
            $table->index('resolution_type_code');
            $table->foreign('company_uuid')->references('uuid')->on('companies')->cascadeOnDelete();
            $table->foreign('resolution_type_code')->references('code')->on('billing_resolution_types')->restrictOnDelete();
            $table->foreign('billing_provider_uuid')->references('uuid')->on('billing_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_resolutions');
        Schema::dropIfExists('billing_providers');
    }
};
