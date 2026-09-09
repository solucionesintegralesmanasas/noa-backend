<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo BankDetail que representa la tabla bank_details.
 * Información bancaria corporativa de la empresa
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class BankDetail extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'bank_details';

    /**
     * Atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'bank_name',
        'branch_office',
        'account_type',
        'account_number',
        'account_holder',
        'is_active',
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
        'is_active' => 'boolean',
    ];
}
