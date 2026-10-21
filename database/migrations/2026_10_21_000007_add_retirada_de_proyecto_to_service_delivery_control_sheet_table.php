<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca las hojas de control cuyo coche fue retirado del proyecto (SPEC-009).
     *
     * Al desactivarse la asignación del coche (project_driver_vehicles.is_active
     * = 0), sus hojas ya creadas dejan de salir del listado pero se conservan
     * como histórico. `is_active` queda reservado para el cierre de la hoja o
     * del día (SPEC-002 §7.2): este estado de retiro es una columna aparte y
     * jamás debe usarse `is_active` para él. Sin relleno previo: solo aplica a
     * los retiros hechos desde esta versión.
     */
    public function up(): void
    {
        Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
            $table->boolean('retirada_de_proyecto')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
            $table->dropColumn('retirada_de_proyecto');
        });
    }
};
