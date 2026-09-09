<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configuration', static function (Blueprint $table): void {
            $table->id()->comment('Identificador interno autoincremental del registro');
            $table->char('uuid', 36)->unique()->comment('UUID v4 único de la configuración');
            $table->char('company_uuid', 36)->unique()->comment('UUID de la empresa propietaria (solo un registro por empresa)');
            $table->boolean('fuec_require_daily_inspections')->default(false)->comment('Obliga a realizar inspecciones preoperacionales diarias por vehículo');
            $table->boolean('fuec_require_social_security')->default(false)->comment('Exige validación de seguridad social para conductores');
            $table->unsignedTinyInteger('maintenance_alert_days')->nullable()->comment('Genera alerta de mantenimiento cada X días (NULL = deshabilitado)');
            $table->char('maintenance_numbers_days', 3)->nullable()->comment('Número de días para alerta de mantenimiento (NULL = deshabilitado)');
            $table->unsignedInteger('maintenance_alert_km')->nullable()->comment('Genera alerta de mantenimiento cada X kilómetros (NULL = deshabilitado)');
            $table->unsignedTinyInteger('payment_cutoff_day')->default(5)->comment('Día del mes programado para la fecha de corte y pago (1-31)');
            $table->unsignedTinyInteger('document_alert_days')->default(30)->comment('Anticipación en días para alertar vencimiento de documentos del vehículo');
            $table->unsignedTinyInteger('license_alert_days')->default(30)->comment('Anticipación en días para alertar vencimiento de la licencia del conductor');
            $table->unsignedTinyInteger('operation_card_alert_days')->default(30)->comment('Anticipación en días para alertar vencimiento de la tarjeta de operación');
            $table->unsignedTinyInteger('soat_alert_days')->default(30)->comment('Anticipación en días para alertar vencimiento del SOAT');
            $table->unsignedTinyInteger('rtm_alert_days')->default(30)->comment('Anticipación en días para alertar vencimiento de la revisión técnico-mecánica (RTM)');
            $table->boolean('activate_notifications')->default(false)->comment('Activa el motor general de notificaciones del sistema');
            $table->boolean('notify_by_email')->default(false)->comment('Permite enviar notificaciones por correo electrónico');
            $table->string('notification_email', 191)->nullable()->comment('Correo electrónico destino para las notificaciones');
            $table->boolean('fuec_pdf_show_signatures')->default(true)->comment('Indica si se deben mostrar las firmas en la impresión del FUEC');
            $table->boolean('fuec_pdf_show_contractor_details')->default(true)->comment('Indica si se muestran detalles del contratista en la impresión del FUEC');
            $table->boolean('fuec_pdf_show_route_details')->default(true)->comment('Indica si se muestran detalles de la ruta en la impresión del FUEC');

            // Número interno automatizado
            $table->unsignedInteger('vehicle_internal_number_counter')->default(1)->nullable()->comment('Contador para autoincrementar el número interno asignado a los vehículos');
            $table->boolean('fuec_enable_auto_internal_number')->default(false)->comment('Determina si se auto-asigna el número interno usando el contador');

            // Pólizas corporativas únicas
            $table->boolean('fuec_use_corporate_policies')->default(false)->comment('Determina si el FUEC valida pólizas corporativas generales en lugar de individuales');
            $table->string('corporate_rcc_insurer', 100)->nullable()->comment('Nombre de la entidad aseguradora para la póliza RCC y RCE corporativa');
            $table->date('corporate_rce_expiration')->nullable()->comment('Fecha de vencimiento de la póliza RCE y RCC corporativa');

            // Tarifas de plataforma
            $table->enum('platform_fee_type', ['PASSENGER_RANGE', 'VEHICLE_CLASS'])->default('VEHICLE_CLASS')->comment('Criterio de cobro de plataforma: por rango de pasajeros o por clase de vehículo');
            $table->json('platform_fee_rates')->nullable()->comment('Configuración de tarifas en formato JSON para el cobro de plataforma');

            $table->timestamps();

            $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configuration');
    }
};
