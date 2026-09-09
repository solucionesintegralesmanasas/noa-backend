<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\SocialSecurityContribution;
use App\Services\BaseService;
use App\Utils\Logger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de negocio altamente detallado para la gestión integral de AporteSeguridadSocial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class SocialSecurityContributionService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = [
        'billing_period',
        'pila_pin',
        'contribution_type',
        'eps_name',
        'pension_name',
        'risk_labor_name',
        'compensation_fund_name',
        'notes',
    ];

    protected function getModelInstance(): Model
    {
        return new SocialSecurityContribution;
    }

    /**
     * Método getAllSocialSecurityContributionsWithPagination.
     */
    public function getAllSocialSecurityContributionsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query();

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
     * Método getAllSocialSecurityContributions.
     */
    public function getAllSocialSecurityContributions(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query();

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

        $query->orderByRaw("FIELD(status, 'PAGADO Y EN CURSO') DESC")
            ->orderBy('billing_period', 'desc');

        return $query->get();
    }

    /**
     * Método getSocialSecurityContributionByUuid.
     */
    public function getSocialSecurityContributionByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createSocialSecurityContribution.
     */
    public function createSocialSecurityContribution(array $data): Model
    {
        $paymentDate = $data['payment_date'] ?? null;
        $billingPeriod = $data['billing_period'] ?? null;

        $status = $data['status'] ?? null;
        if ($paymentDate && $billingPeriod) {
            $status = $this->determinePaymentStatus($billingPeriod, $paymentDate);
        }

        return $this->transaction(fn () => SocialSecurityContribution::create([
            'third_party_uuid' => $data['third_party_uuid'],
            'billing_period' => $billingPeriod,
            'pila_pin' => $data['pila_pin'],
            'contribution_type' => $data['contribution_type'] ?? 'E',
            'ibc_amount' => $data['ibc_amount'] ?? null,
            'health_paid' => $data['health_paid'] ?? 1,
            'pension_paid' => $data['pension_paid'] ?? 1,
            'risk_labor_paid' => $data['risk_labor_paid'] ?? 1,
            'compensation_fund_paid' => $data['compensation_fund_paid'] ?? 1,
            'eps_name' => $data['eps_name'] ?? null,
            'eps_affiliation_date' => $data['eps_affiliation_date'] ?? null,
            'pension_name' => $data['pension_name'] ?? null,
            'pension_affiliation_date' => $data['pension_affiliation_date'] ?? null,
            'risk_labor_name' => $data['risk_labor_name'] ?? null,
            'risk_labor_affiliation_date' => $data['risk_labor_affiliation_date'] ?? null,
            'compensation_fund_name' => $data['compensation_fund_name'] ?? null,
            'compensation_fund_affiliation_date' => $data['compensation_fund_affiliation_date'] ?? null,
            'payment_date' => $paymentDate,
            'status' => $status ?? ($paymentDate ? 'PAGADO Y EN CURSO' : 'PENDIENTE'),
            'notes' => $data['notes'] ?? null,
        ]));
    }

    /**
     * Método updateSocialSecurityContribution.
     */
    public function updateSocialSecurityContribution(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
                'billing_period' => $data['billing_period'] ?? $record->billing_period,
                'pila_pin' => $data['pila_pin'] ?? $record->pila_pin,
                'contribution_type' => $data['contribution_type'] ?? $record->contribution_type,
                'ibc_amount' => $data['ibc_amount'] ?? $record->ibc_amount,
                'health_paid' => $data['health_paid'] ?? $record->health_paid,
                'pension_paid' => $data['pension_paid'] ?? $record->pension_paid,
                'risk_labor_paid' => $data['risk_labor_paid'] ?? $record->risk_labor_paid,
                'compensation_fund_paid' => $data['compensation_fund_paid'] ?? $record->compensation_fund_paid,
                'eps_name' => $data['eps_name'] ?? $record->eps_name,
                'eps_affiliation_date' => array_key_exists('eps_affiliation_date', $data) ? $data['eps_affiliation_date'] : $record->eps_affiliation_date,
                'pension_name' => $data['pension_name'] ?? $record->pension_name,
                'pension_affiliation_date' => array_key_exists('pension_affiliation_date', $data) ? $data['pension_affiliation_date'] : $record->pension_affiliation_date,
                'risk_labor_name' => $data['risk_labor_name'] ?? $record->risk_labor_name,
                'risk_labor_affiliation_date' => array_key_exists('risk_labor_affiliation_date', $data) ? $data['risk_labor_affiliation_date'] : $record->risk_labor_affiliation_date,
                'compensation_fund_name' => $data['compensation_fund_name'] ?? $record->compensation_fund_name,
                'compensation_fund_affiliation_date' => array_key_exists('compensation_fund_affiliation_date', $data) ? $data['compensation_fund_affiliation_date'] : $record->compensation_fund_affiliation_date,
                'payment_date' => array_key_exists('payment_date', $data) ? $data['payment_date'] : $record->payment_date,
                'status' => $data['status'] ?? $record->status,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $record->notes,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteSocialSecurityContribution.
     */
    public function deleteSocialSecurityContribution(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('SocialSecurityContributionService@deleteSocialSecurityContribution: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Determina el estado según el período de facturación y la fecha de pago.
     *
     * - PAGADO Y FINALIZADO: billing_period < mes actual + payment_date no nulo
     * - PAGADO Y EN CURSO:   billing_period == mes actual + payment_date no nulo
     */
    public function determinePaymentStatus(string $billingPeriod, string $paymentDate): string
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $bp = Carbon::parse($billingPeriod)->startOfMonth();

        if ($bp->lt($currentMonth)) {
            return 'PAGADO Y FINALIZADO';
        }

        return 'PAGADO Y EN CURSO';
    }

    /**
     * Actualiza automáticamente estados de seguridad social.
     *
     * 1. Registros con payment_date: corrige el estado según período vs mes actual.
     * 2. Si no hay un pago vigente en el mes actual, marca como EN MORA los
     *    períodos anteriores sin payment_date.
     */
    public function autoUpdateExpiredStatuses(): array
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $updated = 0;

        DB::beginTransaction();

        try {
            // 1. Corregir estados de registros pagados según su período
            $pastPaid = SocialSecurityContribution::where('billing_period', '<', $currentMonth)
                ->whereNotNull('payment_date')
                ->where('status', '!=', 'PAGADO Y FINALIZADO')
                ->update(['status' => 'PAGADO Y FINALIZADO']);
            $updated += $pastPaid;

            $currentPaid = SocialSecurityContribution::where('billing_period', $currentMonth)
                ->whereNotNull('payment_date')
                ->where('status', '!=', 'PAGADO Y EN CURSO')
                ->update(['status' => 'PAGADO Y EN CURSO']);
            $updated += $currentPaid;

            // 2. Detectar mora por tercero
            $thirdPartyUuids = SocialSecurityContribution::query()
                ->select('third_party_uuid')
                ->distinct()
                ->pluck('third_party_uuid');

            foreach ($thirdPartyUuids as $tpuuid) {
                $hasCurrentPaid = SocialSecurityContribution::where('third_party_uuid', $tpuuid)
                    ->where('billing_period', $currentMonth)
                    ->whereNotNull('payment_date')
                    ->exists();

                if (!$hasCurrentPaid) {
                    $affected = SocialSecurityContribution::where('third_party_uuid', $tpuuid)
                        ->where('billing_period', '<', $currentMonth)
                        ->whereNull('payment_date')
                        ->where('status', '!=', 'EN MORA')
                        ->update(['status' => 'EN MORA']);
                    $updated += $affected;
                }
            }

            DB::commit();

            return [
                'message' => 'Actualización de seguridad social completada',
                'updated' => $updated,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Logger::error('SocialSecurityContributionService@autoUpdateExpiredStatuses: '.$e->getMessage());
            throw $e;
        }
    }
}
