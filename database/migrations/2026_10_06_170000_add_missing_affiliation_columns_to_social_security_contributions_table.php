<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $stringColumns = [
        'pension_name',
        'risk_labor_name',
        'compensation_fund_name',
    ];

    private array $dateColumns = [
        'eps_affiliation_date',
        'pension_affiliation_date',
        'risk_labor_affiliation_date',
        'compensation_fund_affiliation_date',
        'payment_date',
    ];

    public function up(): void
    {
        Schema::table('social_security_contributions', function (Blueprint $table) {
            foreach ($this->stringColumns as $col) {
                if (!Schema::hasColumn('social_security_contributions', $col)) {
                    $table->string($col, 100)->nullable();
                }
            }
            foreach ($this->dateColumns as $col) {
                if (!Schema::hasColumn('social_security_contributions', $col)) {
                    $table->date($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        // No-op: columns may belong to the original create migration.
    }
};
