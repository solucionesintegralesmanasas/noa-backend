<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            if (! Schema::hasColumn('system_configuration', 'corporate_rcc_expiration')) {
                $table->date('corporate_rcc_expiration')->nullable()->after('corporate_rcc_insurer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->dropColumn('corporate_rcc_expiration');
        });
    }
};
