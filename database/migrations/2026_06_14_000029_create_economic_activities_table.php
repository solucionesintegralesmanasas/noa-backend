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
        Schema::create('economic_activities', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la actividad');
            $table->uuid('uuid')->unique()->comment('UUID universal único de la actividad económica');
            $table->uuid('company_uuid')->comment('UUID de la empresa que la ejerce');
            $table->string('activity_code', 20)->comment('Código de la actividad económica (CIIU Rev. 4 A.C.)');
            $table->string('activity_description', 255)->nullable()->comment('Descripción detallada de la actividad');
            $table->boolean('is_main_activity')->nullable()->default(false)->comment('Indica si es la actividad económica principal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('economic_activities');
    }
};
