<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de nacimiento de una persona natural: lugar y fecha de nacimiento.
     *
     * Las columnas son nullable porque la obligatoriedad solo aplica a personas
     * NATURALES y se valida en StoreThirdPartyRequest/UpdateThirdPartyRequest;
     * las personas jurídicas y registros anteriores no tienen el dato.
     */
    public function up(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->uuid('birth_place_uuid')
                ->nullable()
                ->after('branch_uuid')
                ->comment('Ciudad de nacimiento (catálogo cities)');
            $table->date('birth_date')
                ->nullable()
                ->after('birth_place_uuid')
                ->comment('Fecha de nacimiento de la persona natural');
        });

        Schema::table('third_parties', function (Blueprint $table) {
            $table->foreign('birth_place_uuid')
                ->references('uuid')
                ->on('cities')
                ->nullOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->dropForeign(['birth_place_uuid']);
            $table->dropColumn([
                'birth_place_uuid',
                'birth_date',
            ]);
        });
    }
};
