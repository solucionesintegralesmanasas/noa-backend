<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE procedures MODIFY COLUMN link_type ENUM('CAMBIO_DE_EMPRESA','NUEVO_VEHICULO','RENOVACION','DESVINCULACION_MUTUO','DESVINCULACION_UNILATERAL') NULL");
    }

    public function down(): void
    {
        // Devuelve los valores nuevos a uno válido del enum antiguo antes de
        // estrecharlo; con STRICT_TRANS_TABLES el ALTER falla si quedan filas
        // con valores fuera del enum destino.
        DB::statement("UPDATE procedures SET link_type = 'CAMBIO_DE_EMPRESA' WHERE link_type IN ('RENOVACION','DESVINCULACION_MUTUO','DESVINCULACION_UNILATERAL')");
        DB::statement("ALTER TABLE procedures MODIFY COLUMN link_type ENUM('CAMBIO_DE_EMPRESA','NUEVO_VEHICULO') NULL");
    }
};
