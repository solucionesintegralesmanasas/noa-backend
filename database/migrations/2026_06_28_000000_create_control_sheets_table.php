<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_sheets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('uk_uuid');
            $table->uuid('company_uuid');
            $table->uuid('vehicle_uuid');
            $table->text('observations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_uuid', 'fk_company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('vehicle_uuid', 'fk_vehicle_uuid')->references('uuid')->on('vehicles')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_sheets');
    }
};
