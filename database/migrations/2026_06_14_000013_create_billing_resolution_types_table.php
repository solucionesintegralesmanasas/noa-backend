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
        Schema::create('billing_resolution_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->comment('UUID V4 DEL TIPO DE RESOLUCIÓN')->unique();
            $table->string('code', 10)->comment('CÓDIGO DIAN QUE CLASIFICA EL DOCUMENTO ELECTRÓNICO AUTORIZADO')->unique();
            $table->string('name', 100)->comment('DESCRIPCIÓN COMERCIAL DEL DOCUMENTO AMPARADO');
            $table->boolean('is_active')->default(1)->comment('HABILITACIÓN PARA USO EN RESOLUCIONES DE FACTURACIÓN');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `billing_resolution_types` COMMENT = 'Tipos de documentos electrónicos autorizados por la DIAN'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_resolution_types');
    }
};
