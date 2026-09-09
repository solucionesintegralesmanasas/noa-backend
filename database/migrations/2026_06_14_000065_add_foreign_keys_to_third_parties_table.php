<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     */
    public function up(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->foreign('company_uuid', 'fk_tp_company')
                ->references('uuid')->on('companies')
                ->onDelete('cascade');

            // La tabla cost_centers no tiene migración aún
            /*
            $table->foreign('cost_center_uuid', 'fk_tp_cost_center')
                ->references('uuid')->on('cost_centers')
                ->onDelete('set null')
                ->onUpdate('cascade');
            */

            $table->foreign('document_type_uuid', 'fk_tp_doc_type')
                ->references('uuid')->on('type_of_documents')
                ->onDelete('restrict');

            $table->foreign('municipality_uuid', 'fk_tp_municipality')
                ->references('uuid')->on('cities')
                ->onDelete('restrict');

            $table->foreign('tax_responsibility_uuid', 'fk_tp_tax_resp')
                ->references('uuid')->on('tax_responsibilities')
                ->onDelete('restrict');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->dropForeign('fk_tp_company');
            // $table->dropForeign('fk_tp_cost_center');
            $table->dropForeign('fk_tp_doc_type');
            $table->dropForeign('fk_tp_municipality');
            $table->dropForeign('fk_tp_tax_resp');
        });
    }
};
