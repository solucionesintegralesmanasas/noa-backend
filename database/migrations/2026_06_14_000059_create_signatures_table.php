<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', static function (Blueprint $table): void {
            $table->id()->comment('Identificador único de la firma');
            $table->char('uuid', 36)->unique()->comment('UUID único universal de la firma');
            $table->string('entity_type', 255)->comment('Tipo de entidad');
            $table->unsignedBigInteger('entity_id')->comment('ID de la entidad');
            $table->char('company_uuid', 36)->comment('UUID único universal de la empresa');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP de la firma');
            $table->string('latitude', 255)->nullable()->comment('Latitud de la firma');
            $table->string('longitude', 255)->nullable()->comment('Longitud de la firma');
            $table->string('path', 255)->comment('Ruta del archivo de la firma');
            $table->string('disk', 50)->default('public')->comment('Disco del archivo de la firma');
            $table->string('mime_type', 50)->default('image/png')->comment('Tipo de archivo');
            $table->unsignedBigInteger('size_bytes')->nullable()->comment('Tamaño del archivo');
            $table->string('speed', 50)->nullable()->comment('Velocidad de la firma');
            $table->enum('state', ['DETENIDO', 'EN MOVIMIENTO'])->default('DETENIDO')->comment('Estado de la firma');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'idx_signatures_entity');

            $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
