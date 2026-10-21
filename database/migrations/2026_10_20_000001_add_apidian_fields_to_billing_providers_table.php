<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para el driver ApiDian del proveedor tecnológico.
     *
     * - driver: qué integración usa este proveedor ('nulo' por defecto; 'apidian').
     * - test_set_id: identificador del set de pruebas DIAN (habilitación); nulo en producción.
     * - send_to_dian: false = validar, firmar y persistir SIN contactar la DIAN.
     * - settings: overrides de mapeo ApiDian (catálogos DIAN) si difieren de los por defecto.
     */
    public function up(): void
    {
        Schema::table('billing_providers', function (Blueprint $table) {
            $table->string('driver', 30)->default('nulo')->comment('Integración del proveedor: nulo | apidian')->after('name');
            $table->string('test_set_id', 100)->nullable()->comment('Set de pruebas DIAN (habilitación); nulo = producción')->after('server_url');
            $table->boolean('send_to_dian')->default(false)->comment('false = sin transmisión a la DIAN; true = habilitación/producción')->after('test_set_id');
            $table->json('settings')->nullable()->comment('Overrides de mapeo ApiDian (catálogos DIAN)')->after('send_to_dian');
        });
    }

    public function down(): void
    {
        Schema::table('billing_providers', function (Blueprint $table) {
            $table->dropColumn(['driver', 'test_set_id', 'send_to_dian', 'settings']);
        });
    }
};
