<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sucursal a la que pertenece el tercero.
     *
     * La columna es nullable porque los terceros existentes no tienen sucursal;
     * la obligatoriedad se exige en StoreThirdPartyRequest/UpdateThirdPartyRequest.
     * Al eliminar una sucursal, los terceros asociados quedan sin sucursal.
     */
    public function up(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->uuid('branch_uuid')
                ->nullable()
                ->after('municipality_uuid')
                ->comment('UUID de la sucursal a la que pertenece el tercero');
            $table->index('branch_uuid', 'idx_third_parties_branch');
        });

        Schema::table('third_parties', function (Blueprint $table) {
            $table->foreign('branch_uuid')
                ->references('uuid')
                ->on('branches')
                ->nullOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->dropForeign(['branch_uuid']);
            $table->dropIndex('idx_third_parties_branch');
            $table->dropColumn('branch_uuid');
        });
    }
};
