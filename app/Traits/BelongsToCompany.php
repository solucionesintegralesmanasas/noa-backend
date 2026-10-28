<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @mixin Model
 *
 * @method static void creating(\Closure $callback)
 * @method static void addGlobalScope(string $identifier, \Closure|\Illuminate\Database\Eloquent\Scope $scope)
 */
trait BelongsToCompany
{
    /**
     * Ejecuta una operación en un contexto explícitamente exento de la restricción de empresa
     * (por ejemplo: sincronizaciones globales de cron, procesamiento entre empresas o inicializaciones del sistema).
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutCompanyScope(callable $callback)
    {
        $previous = TenantScopeContext::$bypass;
        TenantScopeContext::$bypass = true;

        try {
            return $callback();
        } finally {
            TenantScopeContext::$bypass = $previous;
        }
    }

    /**
     * Determina si la ejecución actual está autorizada para consultar sin contexto de empresa.
     */
    public static function isExemptFromCompanyContext(): bool
    {
        // 1. Bypass explícito en código compartido
        if (TenantScopeContext::$bypass) {
            return true;
        }

        // 2. Si la petición HTTP tiene la marca de exención explícita
        $request = Request::instance();
        if ($request && $request->attributes->get('exempt_from_company_context', false)) {
            return true;
        }

        // 3. Rutas públicas legítimas (firmadas, validación de FUEC, cron web)
        if ($request && ($request->is('api/v1/public/*', 'public/*'))) {
            return true;
        }

        // 4. Consola CLI en producción/operación real (NO en testing, para que los tests evalúen el comportamiento real)
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return true;
        }

        return false;
    }

    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            // 1. Si el flujo está explícitamente exento, NUNCA restringir (incluso si hay un company_uuid en request)
            if (self::isExemptFromCompanyContext()) {
                self::registrarConsultaSinEmpresa($builder->getModel());

                return;
            }

            $companyUuid = self::resolveCurrentCompanyUuid();

            if ($companyUuid) {
                $builder->where($builder->getModel()->getTable().'.company_uuid', $companyUuid);
            } else {
                // FALLA CERRADO (Issue #2): Sin contexto de empresa en flujo no exento.
                // 1. Candado en base de datos: consulta imposible (1 = 0) para nunca filtrar datos ajenos.
                $builder->whereRaw('1 = 0');

                // 2. Bloquear con excepción HTTP 403 Forbidden
                self::bloquearConsultaSinEmpresa($builder->getModel());
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->company_uuid) && $model->isFillable('company_uuid')) {
                if (self::isExemptFromCompanyContext()) {
                    return;
                }

                $companyUuid = self::resolveCurrentCompanyUuid();
                if ($companyUuid) {
                    $model->company_uuid = $companyUuid;
                } else {
                    throw new AccessDeniedHttpException('No se puede crear '.class_basename($model).' sin contexto de empresa.');
                }
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

    /**
     * Bloquea la consulta sin contexto de empresa arrojando 403 Forbidden (Issue #2).
     *
     * @throws AccessDeniedHttpException
     */
    protected static function bloquearConsultaSinEmpresa(Model $modelo): void
    {
        $origen = app()->runningInConsole() ? 'consola' : Request::path();
        Log::warning('Consulta bloqueada: falta contexto de empresa en ' . $modelo::class, [
            'modelo' => $modelo::class,
            'origen' => $origen,
            'metodo' => app()->runningInConsole() ? null : Request::method(),
        ]);

        throw new AccessDeniedHttpException(
            'Acceso denegado: se requiere un contexto de empresa válido para consultar ' . class_basename($modelo) . '.'
        );
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
