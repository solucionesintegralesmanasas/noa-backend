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
        Schema::create('puc_commercial', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->comment('UUID V4 DEL PUC COMERCIAL BASE')->unique();
            $table->string('code', 6)->comment('CÓDIGO CONTABLE SEGÚN PUC OFICIAL (EJ: 130505)');
            $table->tinyInteger('level')->comment('NIVEL JERÁRQUICO DE LA CUENTA (1:CLASE, 2:GRUPO, 3:CUENTA, 4:SUBCUENTA, 5:AUXILIAR)');
            $table->string('description', 300)->comment('DENOMINACIÓN OFICIAL DE LA CUENTA PUC');
            $table->char('nature', 1)->comment('NATURALEZA CONTABLE: D (DÉBITO) O C (CRÉDITO)');
            $table->string('account_class', 50)->comment('CLASE CONTABLE A LA QUE PERTENECE');
            $table->boolean('is_reductive')->default(0)->comment('INDICA SI LA CUENTA ES REDUCTORA DE ACTIVO O PASIVO');
            $table->boolean('is_active')->default(1)->comment('INDICA SI LA CUENTA ESTÁ VIGENTE EN EL CATÁLOGO NACIONAL');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `puc_commercial` COMMENT = 'Catálogo base del Plan Único de Cuentas (PUC) comercial colombiano'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puc_commercial');
    }
};
