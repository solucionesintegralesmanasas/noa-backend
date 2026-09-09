<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo FinancialStatement que representa la tabla financial_statements.
 * Estados financieros anuales (Balance y Estado de Resultados)
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class FinancialStatement extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'financial_statements';

    /**
     * Atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'fiscal_year',
        'currency',
        'current_assets',
        'inventory',
        'total_assets',
        'current_liabilities',
        'financial_obligations',
        'total_liabilities',
        'retained_earnings',
        'equity',
        'operational_income',
        'operating_profit_before_tax',
        'net_income_period',
        'depreciation_amortization',
        'financial_expenses',
        'remarks',
    ];

    /**
     * Atributos que deben ser ocultos para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
    ];

    /**
     * Atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_assets' => MoneyCast::class,
        'inventory' => MoneyCast::class,
        'total_assets' => MoneyCast::class,
        'current_liabilities' => MoneyCast::class,
        'financial_obligations' => MoneyCast::class,
        'total_liabilities' => MoneyCast::class,
        'retained_earnings' => MoneyCast::class,
        'equity' => MoneyCast::class,
        'operational_income' => MoneyCast::class,
        'operating_profit_before_tax' => MoneyCast::class,
        'net_income_period' => MoneyCast::class,
        'depreciation_amortization' => MoneyCast::class,
        'financial_expenses' => MoneyCast::class,
    ];
}
