<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El login del conductor y la resolución de su afiliado principal consultan por
 * driver_uuid + is_primary sin empresa en el filtro; este índice evita el scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_drivers', function (Blueprint $table) {
            $table->index(['driver_uuid', 'is_primary'], 'idx_affiliate_drivers_primary');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_drivers', function (Blueprint $table) {
            $table->dropIndex('idx_affiliate_drivers_primary');
        });
    }
};
