<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

// use App\Exceptions\CompanyNotFoundException;

/**
 * @mixin Model
 *
 * @method static void creating(\Closure $callback)
 * @method static void addGlobalScope(string $identifier, \Closure|\Illuminate\Database\Eloquent\Scope $scope)
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyUuid = self::resolveCurrentCompanyUuid();

            if ($companyUuid) {
                $builder->where($builder->getModel()->getTable().'.company_uuid', $companyUuid);
            } else {
                // Sin contexto de empresa el scope NO filtra (falla abierto). Todavía no se bloquea
                // porque rutas públicas (inspección/planilla firmadas, validación de FUEC) y el cron
                // dependen de esto. Se registra para medir qué flujos son y poder cerrarlo con una
                // lista explícita de excepciones (ver AGENTS.md, "Multi-tenant").
                self::registrarConsultaSinEmpresa($builder->getModel());
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->company_uuid) && $model->isFillable('company_uuid')) {
                $model->company_uuid = self::resolveCurrentCompanyUuid();
            }
        });
    }

    /** @var array<string, true> Una advertencia por modelo y ruta en cada proceso. */
    private static array $sinEmpresaRegistradas = [];

    private static function registrarConsultaSinEmpresa(Model $modelo): void
    {
        $origen = app()->runningInConsole() ? 'consola' : Request::path();
        $clave = $modelo::class.'|'.$origen;

        if (isset(self::$sinEmpresaRegistradas[$clave])) {
            return;
        }
        self::$sinEmpresaRegistradas[$clave] = true;

        Log::channel(config('logging.tenant_channel', config('logging.default')))
            ->warning('Consulta sin contexto de empresa (el scope no filtra)', [
                'modelo' => $modelo::class,
                'origen' => $origen,
                'metodo' => app()->runningInConsole() ? null : Request::method(),
            ]);
    }

    protected static function resolveCurrentCompanyUuid(): ?string
    {
        return Request::instance()->attributes->get('current_company_uuid')
            ?? Request::instance()->header('X-Company-UUID')
            ?? Request::instance()->input('company_uuid')
            ?? session('current_company_uuid')
            ?? Auth::user()?->companies()->first()?->uuid;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }
}
