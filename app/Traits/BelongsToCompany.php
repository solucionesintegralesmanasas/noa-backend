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

        static::addGlobalScope('branch', function (Builder $builder) {
            if (! config('authorization.branch_isolation', true)) {
                return;
            }

            $branchUuid = self::resolveCurrentBranchUuid();
            if ($branchUuid === null) {
                return;
            }

            if ($branchUuid === '') {
                // Usuario restringido sin sede asignada: no ve nada.
                $builder->whereRaw('1 = 0');

                return;
            }

            self::applyBranchScope($builder, $branchUuid);
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

    /**
     * Sede efectiva del usuario restringido. Devuelve null cuando no hay
     * restricción por sede y '' cuando el usuario está restringido pero no
     * tiene sede asignada (en ese caso no debe ver registros).
     */
    protected static function resolveCurrentBranchUuid(): ?string
    {
        $request = Request::instance();

        if (! $request->attributes->get('branch_restricted', false)) {
            return null;
        }

        return (string) ($request->attributes->get('current_branch_uuid') ?? '');
    }

    /**
     * Aplica el alcance por sede según la estructura del modelo: columna
     * directa, vehículo, tercero, inspección, planilla o proyecto.
     */
    private static function applyBranchScope(Builder $builder, string $branchUuid): void
    {
        $model = $builder->getModel();
        $table = $model->getTable();

        if ($table === 'branches') {
            $builder->where('branches.uuid', $branchUuid);

            return;
        }

        // En vehículos la sede vive en el historial vehicles_branches (no hay columna directa).
        if ($table === 'vehicles') {
            $builder->whereHas('vehicleBranches', function (Builder $q) use ($branchUuid) {
                $q->where('vehicles_branches.branch_uuid', $branchUuid)
                    ->where('vehicles_branches.is_active', 1);
            });

            return;
        }

        if (in_array('branch_uuid', $model->getFillable(), true)) {
            $builder->where($table.'.branch_uuid', $branchUuid);

            return;
        }

        if (method_exists($model, 'vehicle')) {
            $builder->whereHas('vehicle');

            return;
        }

        if (method_exists($model, 'third_party')) {
            $builder->whereHas('third_party');

            return;
        }

        if (method_exists($model, 'thirdParty')) {
            $builder->whereHas('thirdParty');

            return;
        }

        if (method_exists($model, 'inspection')) {
            $builder->whereHas('inspection.vehicle');

            return;
        }

        if (method_exists($model, 'internalControl')) {
            $builder->whereHas('internalControl.vehicle');

            return;
        }

        if (method_exists($model, 'procedure')) {
            $builder->whereHas('procedure.vehicle');

            return;
        }

        if (method_exists($model, 'vehicles')) {
            $builder->whereHas('vehicles');
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }
}
