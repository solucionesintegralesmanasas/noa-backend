<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\BelongsToCompany;
use App\Traits\HasFiles;
use App\Utils\Logger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Class BaseService
 *
 * Proporciona una base sólida para todos los servicios del sistema,
 * integrando logging, transacciones y filtros comunes de forma nativa.
 */
abstract class BaseService
{
    /**
     * El modelo Eloquent que este servicio gestiona.
     */
    protected Model $model;

    /**
     * Constructor del servicio.
     */
    public function __construct()
    {
        $this->model = $this->getModelInstance();
    }

    /**
     * Cada servicio debe implementar esto para retornar su modelo.
     */
    abstract protected function getModelInstance(): Model;

    /**
     * Obtiene la instancia del modelo.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Inicia una nueva consulta para el modelo.
     * Carga automáticamente la relación 'media' si el modelo usa el trait HasFiles.
     */
    public function query(): Builder
    {
        $query = $this->model->newQuery();

        // Cargamos la relación 'media' solo si el modelo usa el trait HasFiles
        $usedTraits = class_uses_recursive($this->model);
        if (in_array(HasFiles::class, $usedTraits)) {
            $query->with('media');
        }

        return $query;
    }

    /**
     * Ejecuta una operación dentro de una transacción de base de datos.
     *
     * @throws Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        try {
            DB::beginTransaction();
            $result = $callback();
            DB::commit();

            return $result;
        } catch (Throwable $e) {
            DB::rollBack();
            Logger::error(class_basename($this) . ' Transaction Error: ' . $e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Obtiene todos los registros.
     */
    public function all(array $columns = ['*'], array $relations = []): Collection
    {
        try {
            return $this->query()->with($relations)->get($columns);
        } catch (Throwable $e) {
            Logger::error(class_basename($this) . '#all error: ' . $e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Obtiene registros paginados.
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null, array $relations = []): LengthAwarePaginator
    {
        try {
            return $this->query()->with($relations)->paginate($perPage, $columns, $pageName, $page);
        } catch (Throwable $e) {
            Logger::error(class_basename($this) . '#paginate error: ' . $e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Busca un registro por su UUID.
     */
    public function findByUuid(string $uuid, array $columns = ['*'], array $relations = []): ?Model
    {
        try {
            return $this->query()->with($relations)->where('uuid', $uuid)->first($columns);
        } catch (Throwable $e) {
            Logger::error(class_basename($this) . '#findByUuid error: ' . $e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Crea un nuevo registro.
     */
    public function create(array $attributes): Model
    {
        return $this->transaction(fn() => $this->model->create($attributes));
    }

    /**
     * Actualiza un registro existente por su UUID.
     */
    public function update(string $uuid, array $attributes): bool
    {
        return $this->transaction(function () use ($uuid, $attributes) {
            $record = $this->findByUuid($uuid);

            return $record ? $record->update($attributes) : false;
        });
    }

    /**
     * Elimina un registro por su UUID.
     */
    public function delete(string $uuid): bool
    {
        return $this->transaction(function () use ($uuid) {
            $record = $this->findByUuid($uuid);

            return $record ? $record->delete() : false;
        });
    }

    /**
     * Borrado físico de un registro (incluyendo eliminados lógicamente).
     */
    public function forceDelete(string $uuid): bool
    {
        return $this->transaction(function () use ($uuid) {
            $record = $this->query()->withTrashed()->where('uuid', $uuid)->first();

            return $record ? $record->forceDelete() : false;
        });
    }

    /**
     * Cambia el estado de un registro.
     * Soporta valores booleanos (1/0) invirtiéndolos automáticamente.
     * Para valores ENUM o String, detecta el tipo y requiere un mapa de estados alternos.
     *
     * @param  array<string, string>  $enumMapping  Mapa opcional para ENUM, ej: ['VIGENTE' => 'SUSPENDIDO', 'SUSPENDIDO' => 'VIGENTE']
     */
    public function toggleStatus(string $uuid, string $field = 'status', array $enumMapping = []): bool
    {
        return $this->transaction(function () use ($uuid, $field, $enumMapping) {
            $record = $this->findByUuid($uuid);
            if (! $record) {
                return false;
            }

            $currentValue = $record->{$field};

            // Detectar si el campo es un texto puro (probable ENUM) y no un 0/1 numérico
            $isEnumOrString = is_string($currentValue) && ! is_numeric($currentValue);

            if ($isEnumOrString) {
                if (empty($enumMapping)) {
                    throw new \InvalidArgumentException(
                        "El campo '{$field}' contiene el valor '{$currentValue}' (tipo ENUM/String). " .
                            'No se puede alternar automáticamente de forma segura. ' .
                            "Debe pasar un mapa de estados en \$enumMapping, ej: ['VIGENTE' => 'SUSPENDIDO', 'SUSPENDIDO' => 'VIGENTE']."
                    );
                }

                if (! array_key_exists($currentValue, $enumMapping)) {
                    throw new \InvalidArgumentException(
                        "El estado actual '{$currentValue}' no tiene una transición definida en el mapa proporcionado."
                    );
                }

                $record->{$field} = $enumMapping[$currentValue];
            } else {
                // Es numérico o booleano, se puede invertir de forma nativa
                $record->{$field} = ! $currentValue;
            }

            return $record->save();
        });
    }

    /**
     * Propiedad que las clases hijas pueden definir para indicar
     * qué columnas son afectadas por la búsqueda libre ($search).
     *
     * @var array<string>
     */
    protected array $searchableFields = [];

    /**
     * Búsqueda avanzada con filtros dinámicos y soporte para multi-tenancy.
     *
     * @param  array  $filters  Filtros a aplicar.
     * @param  array  $columns  Columnas a retornar.
     * @param  int|null  $perPage  Si se desea paginación.
     * @param  array  $relations  Relaciones a cargar.
     */
    public function search(
        array $filters = [],
        array $columns = ['*'],
        ?int $perPage = null,
        array $relations = []
    ): Collection|LengthAwarePaginator {
        try {
            $query = $this->query()->with($relations);

            foreach ($filters as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                // Soporte nativo para filtrado por empresa
                if ($field === 'company_uuid') {
                    $this->applyCompanyFilter($query, $value);

                    continue;
                }

                // Soporte nativo para filtrado por tercero propietario
                if ($field === 'third_party_uuid') {
                    $query->where($this->model->getTable() . '.third_party_uuid', $value);

                    continue;
                }

                // Lógica de filtrado inteligente
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } elseif (is_string($value) && ! Str::isUuid((string) $value)) {
                    $query->where($field, 'like', "%{$value}%");
                } else {
                    $query->where($field, $value);
                }
            }

            return $perPage ? $query->paginate($perPage, $columns) : $query->get($columns);
        } catch (Throwable $e) {
            Logger::error(class_basename($this) . '#search error: ' . $e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Obtiene una colección paginada estándar aplicando filtros básicos de listado.
     */
    public function getPaginatedData(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        array $relations = [],
        array $columns = ['*']
    ): LengthAwarePaginator {
        $query = $this->query()->with($relations);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if (! empty($search) && ! empty($this->searchableFields)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }
            });
        }

        return $query->paginate($perPage, $columns, 'page', $page);
    }

    /**
     * Búsqueda avanzada usando Spatie Query Builder.
     *
     * @param  string|null  $companyUuid  Opcional: UUID de la empresa para forzar aislamiento de datos (multi-tenant).
     */
    public function buildQuery(?string $companyUuid = null): QueryBuilder
    {
        $query = $this->query();

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        return QueryBuilder::for($query);
    }

    /**
     * Aplica el filtro de empresa de forma nativa basándose en la estructura del proyecto.
     */
    protected function applyCompanyFilter(Builder $query, string $companyUuid): void
    {
        $table = $this->model->getTable();
        $fillable = $this->model->getFillable();

        // 1. Si el modelo utiliza el trait BelongsToCompany o tiene company_uuid en fillable
        $usesTenantTrait = in_array(BelongsToCompany::class, class_uses_recursive(get_class($this->model)));
        if ($usesTenantTrait || in_array('company_uuid', $fillable)) {
            $query->where($table . '.company_uuid', $companyUuid);
        }
        // 2. Si el modelo tiene relación con third_party (muy común en FleetManagement)
        elseif (method_exists($this->model, 'third_party')) {
            $query->whereHas('third_party', function ($q) use ($companyUuid) {
                $q->where('third_parties.company_uuid', $companyUuid);
            });
        }
        // 3. Si el modelo tiene relación con branch/branche/sucursal
        elseif (method_exists($this->model, 'branch')) {
            $query->whereHas('branch', function ($q) use ($companyUuid) {
                $q->where('branches.company_uuid', $companyUuid);
            });
        } elseif (method_exists($this->model, 'branche')) {
            $query->whereHas('branche', function ($q) use ($companyUuid) {
                $q->where('branches.company_uuid', $companyUuid);
            });
        }

        // Filtro adicional por third_party_uuid para AFILIADO / CONDUCTOR
        $user = Auth::user();
        if ($user && ($user->hasRole('AFILIADO') || $user->hasRole('CONDUCTOR'))) {
            $thirdPartyUuid = request()->attributes->get('current_third_party_uuid');

            if (! $thirdPartyUuid) {
                $companyUser = $user->companies()->where('company_user.company_uuid', $companyUuid)->first()
                    ?? $user->companies()->where('companies.uuid', $companyUuid)->first()
                    ?? $user->companies()->first();
                $thirdPartyUuid = $companyUser ? $companyUser->pivot->third_party_uuid : null;
            }

            $affiliateUuid = request()->attributes->get('current_affiliate_uuid');

            if (! $affiliateUuid && $user->hasRole('CONDUCTOR') && $thirdPartyUuid) {
                $affiliate = \App\Models\OwnerDriver::withoutGlobalScopes()->whereIn(
                    'driver_license_uuid',
                    \App\Models\DriverLicense::withoutGlobalScopes()
                        ->where('third_party_uuid', $thirdPartyUuid)
                        ->whereIn('status', ['VIGENTE', 'ACTIVA'])
                        ->pluck('uuid')
                )->first();
                if ($affiliate) {
                    $affiliateUuid = $affiliate->third_party_uuid;
                }
            }

            // Fallback si no hay afiliado (ej. no es conductor o no tiene dueño asignado)
            $affiliateUuid = $affiliateUuid ?? $thirdPartyUuid;

            if (! $affiliateUuid) {
                // Si el usuario es AFILIADO pero no tiene un third_party_uuid asociado en su contexto,
                // forzamos a que la consulta devuelva vacío por seguridad, para que no vea la info de todos.
                $query->whereRaw('1 = 0');
            } else {
                if ($table === 'third_parties') {
                    if ($user->hasRole('AFILIADO')) {
                        // Un afiliado ve a sus conductores vinculados (a través de owners_drivers) y a sí mismo
                        $driverLicenseUuids = \App\Models\OwnerDriver::withoutGlobalScopes()
                            ->where('third_party_uuid', $affiliateUuid)
                            ->pluck('driver_license_uuid');

                        $driverThirdPartyUuids = \App\Models\DriverLicense::withoutGlobalScopes()
                            ->whereIn('uuid', $driverLicenseUuids)
                            ->pluck('third_party_uuid')
                            ->toArray();

                        $allowedUuids = array_unique(array_merge([$affiliateUuid], $driverThirdPartyUuids));

                        $query->whereIn($table . '.uuid', $allowedUuids);
                    } elseif ($user->hasRole('CONDUCTOR')) {
                        // Un conductor solo se ve a sí mismo
                        $query->where($table . '.uuid', $thirdPartyUuid);
                    }
                } elseif ($table === 'driver_licenses') {
                    if ($user->hasRole('AFILIADO')) {
                        $driverLicenseUuids = \App\Models\OwnerDriver::withoutGlobalScopes()
                            ->where('third_party_uuid', $affiliateUuid)
                            ->pluck('driver_license_uuid')
                            ->toArray();

                        $query->where(function ($q) use ($table, $affiliateUuid, $driverLicenseUuids) {
                            $q->where($table . '.third_party_uuid', $affiliateUuid)
                                ->orWhereIn($table . '.uuid', $driverLicenseUuids);
                        });
                    } elseif ($user->hasRole('CONDUCTOR')) {
                        $query->where($table . '.third_party_uuid', $thirdPartyUuid);
                    }
                } elseif ($table === 'social_security_contributions') {
                    if ($user->hasRole('AFILIADO')) {
                        $driverLicenseUuids = \App\Models\OwnerDriver::withoutGlobalScopes()
                            ->where('third_party_uuid', $affiliateUuid)
                            ->pluck('driver_license_uuid');

                        $driverThirdPartyUuids = \App\Models\DriverLicense::withoutGlobalScopes()
                            ->whereIn('uuid', $driverLicenseUuids)
                            ->pluck('third_party_uuid')
                            ->toArray();

                        $allowedUuids = array_unique(array_merge([$affiliateUuid], $driverThirdPartyUuids));

                        $query->whereIn($table . '.third_party_uuid', $allowedUuids);
                    } elseif ($user->hasRole('CONDUCTOR')) {
                        $query->where($table . '.third_party_uuid', $thirdPartyUuid);
                    }
                } elseif ($table === 'owners_drivers') {
                    if ($user->hasRole('AFILIADO')) {
                        $query->where($table . '.third_party_uuid', $affiliateUuid);
                    } elseif ($user->hasRole('CONDUCTOR')) {
                        $driverLicenseUuids = \App\Models\DriverLicense::withoutGlobalScopes()
                            ->where('third_party_uuid', $thirdPartyUuid)
                            ->pluck('uuid')
                            ->toArray();

                        $query->whereIn($table . '.driver_license_uuid', $driverLicenseUuids);
                    }
                } elseif ($table === 'vehicles') {
                    $query->where(function ($q) use ($affiliateUuid) {
                        $q->where('vehicles.third_party_uuid', $affiliateUuid)
                            ->orWhereHas('owners', function ($ownerQuery) use ($affiliateUuid) {
                                $ownerQuery->where('third_party_uuid', $affiliateUuid);
                            });
                    });
                } elseif (in_array('third_party_uuid', $fillable)) {
                    $query->where($table . '.third_party_uuid', $affiliateUuid);
                } elseif (method_exists($this->model, 'third_party')) {
                    $query->whereHas('third_party', function ($q) use ($affiliateUuid) {
                        $q->where('third_parties.uuid', $affiliateUuid);
                    });
                } elseif (method_exists($this->model, 'thirdParty')) {
                    $query->whereHas('thirdParty', function ($q) use ($affiliateUuid) {
                        $q->where('third_parties.uuid', $affiliateUuid);
                    });
                }
            }
        }
    }
}
