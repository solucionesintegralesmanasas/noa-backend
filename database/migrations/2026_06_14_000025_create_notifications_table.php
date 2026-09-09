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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id()->comment('Identificador interno autoincremental');
            $table->uuid('uuid')->unique()->comment('UUID v4 único de la notificación/alerta');
            $table->uuid('company_uuid')->index()->comment('UUID de la empresa asociada (tenant)');
            $table->string('type', 50)->comment('Categoría de la alerta (ej: SOAT, RTM, LICENCIA, etc.)');
            $table->string('title', 150)->comment('Título descriptivo de la notificación');
            $table->text('message')->comment('Cuerpo o mensaje detallado de la alerta');
            $table->enum('status', ['PENDIENTE', 'LEIDA'])->default('PENDIENTE')->comment('Estado de lectura de la alerta');
            $table->uuid('entity_uuid')->nullable()->index()->comment('UUID de la entidad origen que detonó la alerta');
            $table->string('entity_type', 100)->nullable()->comment('Modelo/Clase Eloquent de la entidad origen');
            $table->integer('days_left')->nullable()->comment('Días restantes para el vencimiento al momento de generar la alerta');
            $table->date('expiry_date')->nullable()->comment('Fecha de vencimiento/vencida');
            $table->json('extra_data')->nullable()->comment('Detalles técnicos o metadatos de la alerta');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
