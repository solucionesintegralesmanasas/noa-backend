<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\DriverLicense;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de LicenciaConduccion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class DriverLicenseService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['document_number'];

    public function __construct(
        private readonly OwnerDriverService $ownerDriverService
    ) {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new DriverLicense;
    }

    /**
     * Método query.
     */
    public function query(): Builder
    {
        return parent::query()->with('ownerDriver');
    }

    /**
     * Método getAllDriverLicensesWithPagination.
     */
    public function getAllDriverLicensesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()->with('ownerDriver');

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->where('third_party_uuid', $thirdPartyUuid);
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

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Método getAllDriverLicenses.
     */
    public function getAllDriverLicenses(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query()->with('ownerDriver');

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->where('third_party_uuid', $thirdPartyUuid);
        }

        return $query->get();
    }

    /**
     * Método getDriverLicenseByUuid.
     */
    public function getDriverLicenseByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Licencia que edita el formulario de terceros (espejo de `elegirLicencia`
     * del frontend): prefiere la ACTIVA con vencimiento más lejano; sin ACTIVA,
     * la más reciente. Evita editar una licencia vieja cuando ya existe una
     * renovación vigente (el orden natural de BD trae la vieja primero).
     */
    public function licenciaParaEdicion(string $thirdPartyUuid, ?string $companyUuid = null): ?Model
    {
        return $this->getAllDriverLicenses($companyUuid, $thirdPartyUuid)
            ->sortByDesc(fn (Model $l) => (self::estadoNormalizado($l->status) === 'ACTIVA' ? '1' : '0')
                .($l->expiration_date ? (string) $l->expiration_date : ''))
            ->first();
    }

    /**
     * Estado normalizado para comparar (tolera minúsculas y espacios).
     */
    private static function estadoNormalizado(mixed $estado): string
    {
        return mb_strtoupper(trim((string) $estado));
    }

    /**
     * Indica si una licencia cuenta como vigente: estado ACTIVA y sin vencer
     * por fecha (sin fecha o vence hoy/después).
     */
    private static function esVigente(mixed $licencia): bool
    {
        if (self::estadoNormalizado($licencia->status ?? null) !== 'ACTIVA') {
            return false;
        }
        if (empty($licencia->expiration_date)) {
            return true;
        }

        return \Carbon\Carbon::parse($licencia->expiration_date)->gte(today());
    }

    /**
     * ¿Tiene el conductor otra licencia vigente distinta de la indicada?
     */
    private function otraVigente(string $thirdPartyUuid, ?string $ignorarUuid = null): bool
    {
        return DriverLicense::where('third_party_uuid', $thirdPartyUuid)
            ->when($ignorarUuid, fn ($q) => $q->where('uuid', '!=', $ignorarUuid))
            ->get(['uuid', 'status', 'expiration_date'])
            ->contains(fn ($licencia) => self::esVigente($licencia));
    }

    /**
     * Marca VENCIDA cualquier otra licencia ACTIVA del conductor (limpieza de
     * estados rancios al activar la nueva; las SUSPENDIDA/CANCELADA/VENCIDA no
     * se tocan).
     */
    private function marcarAnterioresVencidas(string $thirdPartyUuid, ?string $exceptoUuid = null): void
    {
        DriverLicense::where('third_party_uuid', $thirdPartyUuid)
            ->when($exceptoUuid, fn ($q) => $q->where('uuid', '!=', $exceptoUuid))
            ->whereRaw("UPPER(TRIM(status)) = 'ACTIVA'")
            ->update(['status' => 'VENCIDA', 'updated_at' => now()]);
    }

    /**
     * Afiliado propietario del conductor según su licencia más reciente que
     * tenga un propietario distinto de él mismo. Se usa para heredar el
     * propietario al renovar desde el modal de licencias (no envía
     * affiliate_uuid) y no perderlo en la licencia nueva.
     */
    private function afiliadoDelConductor(string $thirdPartyUuid): ?string
    {
        return DriverLicense::where('third_party_uuid', $thirdPartyUuid)
            ->whereHas('ownerDriver', fn ($q) => $q->whereColumn('owners_drivers.third_party_uuid', '!=', 'driver_licenses.third_party_uuid'))
            ->orderByDesc('expiration_date')
            ->orderByDesc('id')
            ->with('ownerDriver')
            ->first()
            ?->ownerDriver
            ?->third_party_uuid;
    }

    /**
     * Método createDriverLicense.
     */
    public function createDriverLicense(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            if (self::estadoNormalizado($data['status'] ?? 'ACTIVA') === 'ACTIVA'
                && $this->otraVigente($data['third_party_uuid'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'El conductor ya tiene una licencia vigente. Solo se puede crear una nueva cuando la anterior esté vencida.',
                ]);
            }

            $license = DriverLicense::create([
                'company_uuid' => $data['company_uuid'],
                'third_party_uuid' => $data['third_party_uuid'],
                'number' => $data['number'],
                'category' => $data['category'],
                'issue_date' => $data['issue_date'],
                'expiration_date' => $data['expiration_date'],
                'restrictions' => $data['restrictions'] ?? null,
                'status' => $data['status'] ?? 'ACTIVA',
            ]);

            $this->ownerDriverService->createOwnerDriver([
                'company_uuid' => $license->company_uuid,
                'driver_license_uuid' => $license->uuid,
                'third_party_uuid' => $data['affiliate_uuid']
                    ?? $this->afiliadoDelConductor($license->third_party_uuid)
                    ?? $license->third_party_uuid,
            ]);

            if (self::estadoNormalizado($license->status) === 'ACTIVA') {
                $this->marcarAnterioresVencidas($license->third_party_uuid, $license->uuid);
            }

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($license->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras crear licencia: '.$e->getMessage());
            }

            return $license->load('ownerDriver');
        });
    }

    /**
     * Método updateDriverLicense.
     */
    public function updateDriverLicense(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $estadoNuevo = self::estadoNormalizado($data['status'] ?? $record->status);
            if ($estadoNuevo === 'ACTIVA' && $this->otraVigente($record->third_party_uuid, $uuid)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'El conductor ya tiene otra licencia vigente. Solo se puede activar una cuando la anterior esté vencida.',
                ]);
            }

            $record->update([
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
                'number' => $data['number'] ?? $record->number,
                'category' => $data['category'] ?? $record->category,
                'issue_date' => $data['issue_date'] ?? $record->issue_date,
                'expiration_date' => $data['expiration_date'] ?? $record->expiration_date,
                'restrictions' => $data['restrictions'] ?? $record->restrictions,
                'status' => $data['status'] ?? $record->status,
            ]);

            $targetAffiliateUuid = $data['affiliate_uuid'] ?? $record->ownerDriver?->third_party_uuid;
            if ($targetAffiliateUuid) {
                if ($record->ownerDriver) {
                    $this->ownerDriverService->updateOwnerDriver($record->ownerDriver->uuid, [
                        'third_party_uuid' => $targetAffiliateUuid,
                    ]);
                } else {
                    $this->ownerDriverService->createOwnerDriver([
                        'company_uuid' => $record->company_uuid,
                        'driver_license_uuid' => $record->uuid,
                        'third_party_uuid' => $targetAffiliateUuid,
                    ]);
                }
            }

            if (self::estadoNormalizado($record->fresh()->status) === 'ACTIVA') {
                $this->marcarAnterioresVencidas($record->third_party_uuid, $record->uuid);
            }

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($record->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras actualizar licencia: '.$e->getMessage());
            }

            return $record->fresh()->load('ownerDriver');
        });
    }

    /**
     * Método deleteDriverLicense.
     */
    public function deleteDriverLicense(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('DriverLicenseService@deleteDriverLicense: '.$e->getMessage());
            throw $e;
        }
    }
}
