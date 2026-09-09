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
 * Modelo TaxDeclaration que representa la tabla tax_declarations.
 * Declaraciones de renta y complementarios anuales
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class TaxDeclaration extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'tax_declarations';

    /**
     * Atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'fiscal_year',
        'gross_assets',
        'net_assets',
        'total_gross_income',
        'ordinary_net_income',
        'pre_tax_net_profit',
        'total_operating_non_operating_income',
        'remarks',
        'status',
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
        'gross_assets' => MoneyCast::class,
        'net_assets' => MoneyCast::class,
        'total_gross_income' => MoneyCast::class,
        'ordinary_net_income' => MoneyCast::class,
        'pre_tax_net_profit' => MoneyCast::class,
        'total_operating_non_operating_income' => MoneyCast::class,
    ];
}
