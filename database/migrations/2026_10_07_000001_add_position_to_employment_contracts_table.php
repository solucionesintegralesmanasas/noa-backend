<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('employment_contracts', 'position')) {
                $table->string('position', 150)->nullable()->after('contract_type')
                    ->comment('Cargo o posición del empleado en el contrato');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('employment_contracts', 'position')) {
                $table->dropColumn('position');
            }
        });
    }
};