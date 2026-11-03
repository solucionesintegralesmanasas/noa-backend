<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo conductor ↔ afiliado.
 *
 * Un conductor sigue siendo un único tercero de la empresa (documento único),
 * pero puede estar vinculado a varios afiliados. `is_primary` conserva el
 * concepto de propietario original y da un contexto determinista al login del
 * conductor; los demás vínculos solo habilitan visibilidad y uso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_drivers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('uk_affiliate_drivers_uuid');
            $table->uuid('company_uuid');
            $table->uuid('driver_uuid')->comment('Tercero conductor');
            $table->uuid('affiliate_uuid')->comment('Tercero afiliado');
            $table->boolean('is_primary')->default(false)->comment('Propietario principal del conductor');
            $table->boolean('is_active')->default(true)->comment('Vínculo vigente');
            $table->uuid('created_by_uuid')->nullable()->comment('Usuario que creó el vínculo');
            $table->timestamps();

            $table->unique(
                ['company_uuid', 'driver_uuid', 'affiliate_uuid'],
                'uq_affiliate_drivers'
            );
            $table->index(['company_uuid', 'affiliate_uuid'], 'idx_affiliate_drivers_affiliate');
            $table->index(['company_uuid', 'driver_uuid'], 'idx_affiliate_drivers_driver');

            $table->foreign('company_uuid', 'fk_affiliate_drivers_company')
                ->references('uuid')->on('companies')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('driver_uuid', 'fk_affiliate_drivers_driver')
                ->references('uuid')->on('third_parties')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('affiliate_uuid', 'fk_affiliate_drivers_affiliate')
                ->references('uuid')->on('third_parties')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_drivers');
    }
};
