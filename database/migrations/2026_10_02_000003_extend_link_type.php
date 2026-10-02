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
        DB::statement("ALTER TABLE procedures MODIFY COLUMN link_type ENUM('CAMBIO_DE_EMPRESA','NUEVO_VEHICULO') NULL");
    }
};
