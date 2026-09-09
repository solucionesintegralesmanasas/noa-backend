<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('affiliate_admin_charges', function (Blueprint $table) {
            $table->id()->comment('Identificador interno autoincremental');
            $table->uuid('uuid')->unique()->comment('UUID v4 único del cargo administrativo');
            $table->uuid('company_uuid')->index()->comment('UUID de la empresa asociada (tenant)');
            $table->string('payment_reference', 50)->comment('Referencia o número consecutivo de cobro');
            $table->uuid('vehicle_uuid')->index()->comment('UUID del vehículo asociado al afiliado');
            $table->enum('charge_type', [
                'CUOTA_ADMINISTRACION',
                'PAGO_MENSUALIDAD',
                'PAGO_CUPO',
            ])->comment('Tipo de cargo administrativo aplicado');
            $table->enum('payment_method', [
                'EFECTIVO',
                'TRANSFERENCIA',
                'CHEQUE',
                'TARJETA',
                'CORTESIA',
                'OTRO',
            ])->default('EFECTIVO')->comment('Método de pago utilizado');
            $table->string('concept', 255)->comment('Concepto o descripción corta del cobro');
            $table->decimal('amount', 15, 2)->comment('Monto total del cargo administrativo');
            $table->char('currency_code', 3)->default('COP')->comment('Código ISO de la moneda (predeterminado COP)');
            $table->date('period_date')->comment('Fecha correspondiente al periodo facturado');
            $table->date('due_date')->comment('Fecha límite de pago oportuno');
            $table->date('next_payment_date')->comment('Fecha programada para el siguiente pago');
            $table->decimal('late_fee_percentage', 5, 2)->default(0.00)->comment('Porcentaje de recargo por mora aplicado');
            $table->enum('status', [
                'PENDIENTE',
                'PAGADO',
                'VENCIDO',
                'EN_MORA',
                'ANULADO',
            ])->default('PENDIENTE')->comment('Estado del recaudo o cartera del cargo');
            $table->date('payment_date')->nullable()->comment('Fecha en la que el afiliado realizó el pago');
            $table->string('bank_reference', 100)->nullable()->comment('Referencia de la transacción bancaria o pasarela');
            $table->text('notes')->nullable()->comment('Observaciones o notas adicionales sobre el cargo');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_admin_charges');
    }
};
