<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige `contract_signatures.expires_at`.
 *
 * La migración que creó la tabla la declaró como `$table->timestamp('expires_at')`,
 * lo que en MariaDB/MySQL genera
 * `timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
 * El `ON UPDATE` reiniciaba la vigencia cada vez que se actualizaba la fila, así
 * que en el momento de firmar el enlace (o ante cualquier actualización) el
 * `expires_at` volvía a ser la hora actual y el enlace de un solo uso quedaba
 * vencido al instante.
 *
 * La vigencia del enlace se fija siempre al crearlo, por lo que la columna debe
 * ser un `timestamp` normal, sin `ON UPDATE`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sentencia explícita: `change()` depende de la reconstrucción de la
        // columna y no es determinista para este motor.
        DB::statement(
            'ALTER TABLE `contract_signatures` MODIFY `expires_at` TIMESTAMP NULL DEFAULT NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE `contract_signatures` MODIFY `expires_at` TIMESTAMP NOT NULL
             DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );
    }
};