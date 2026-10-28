<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de identidad de la cédula de una persona natural: lugar y fecha de
     * expedición, sexo, estado civil y grupo sanguíneo.
     *
     * Todas las columnas son nullable porque la obligatoriedad solo aplica a
     * personas NATURALES y se exige en StoreThirdPartyRequest/UpdateThirdPartyRequest;
     * los terceros existentes no tienen el dato.
     */
    public function up(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->uuid('id_card_issue_place_uuid')
                ->nullable()
                ->after('branch_uuid')
                ->comment('Ciudad en la que se expidió la cédula (catálogo cities)');
            $table->date('id_card_issue_date')
                ->nullable()
                ->after('id_card_issue_place_uuid')
                ->comment('Fecha de expedición de la cédula');
            $table->enum('sex', ['M', 'F', 'O'])
                ->nullable()
                ->after('id_card_issue_date')
                ->comment('Sexo de la persona natural (M/F/O: otro)');
            $table->enum('marital_status', ['SOLTERO', 'CASADO', 'UNION_LIBRE', 'SEPARADO', 'DIVORCIADO', 'VIUDO'])
                ->nullable()
                ->after('sex')
                ->comment('Estado civil de la persona natural');
            $table->enum('blood_type', ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])
                ->nullable()
                ->after('marital_status')
                ->comment('Grupo sanguíneo de la persona natural');
        });

        Schema::table('third_parties', function (Blueprint $table) {
            $table->foreign('id_card_issue_place_uuid')
                ->references('uuid')
                ->on('cities')
                ->nullOnDelete()
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->dropForeign(['id_card_issue_place_uuid']);
            $table->dropColumn([
                'id_card_issue_place_uuid',
                'id_card_issue_date',
                'sex',
                'marital_status',
                'blood_type',
            ]);
        });
    }
};
