<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\AffiliateAdminCharge;
use App\Services\BaseService;
use App\Utils\Logger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de negocio para la gestión integral y automatización de AffiliateAdminCharge.
 *
 * Aprovecha completamente BaseService: $searchableFields, getPaginatedData(),
 * applyCompanyFilter(), query(), transaction(), findByUuid(), all(), delete().
 *
 * @author   Darwin Montes
 *
 * @version  2.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-31
 */
class AffiliateAdminChargeService extends BaseService
{
    /**
     * Columnas que participan en la búsqueda libre ($search) de getPaginatedData().
     *
     * @var array<string>
     */
    protected array $searchableFields = [
        'payment_reference',
        'concept',
        'charge_type',
        'status',
        'bank_reference',
        'notes',
    ];

    protected function getModelInstance(): Model
    {
        return new AffiliateAdminCharge;
    }

    /**
     * Método getAllAffiliateAdminChargesWithPagination.
     */
    public function getAllAffiliateAdminChargesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()->with(['vehicle:uuid,vehicle_license_plate']);

        $this->applyActiveChargesFilter($query);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
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

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Optimización de payload in-place
        $paginator->getCollection()->transform(fn ($charge) => [
            'uuid' => $charge->uuid,
            'payment_reference' => $charge->payment_reference,
            'bank_reference' => $charge->bank_reference,
            'charge_type' => $charge->charge_type,
            'concept' => $charge->concept,
            'amount' => $charge->amount,
            'period_date' => $charge->period_date,
            'due_date' => $charge->due_date,
            'payment_date' => $charge->payment_date,
            'status' => $charge->status,
            'vehicle' => $charge->vehicle ? [
                'vehicle_license_plate' => $charge->vehicle->vehicle_license_plate,
            ] : null,
        ]);

        return $paginator;
    }

    /**
     * Método getAllAffiliateAdminCharges.
     */
    public function getAllAffiliateAdminCharges(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query()->with(['vehicle:uuid,vehicle_license_plate']);

        $this->applyActiveChargesFilter($query);

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        return $query->get();
    }

    /**
     * Método getAffiliateAdminChargeByUuid.
     */
    public function getAffiliateAdminChargeByUuid(string $uuid): ?AffiliateAdminCharge
    {
        /** @var AffiliateAdminCharge|null $charge */
        $charge = $this->findByUuid($uuid, ['*'], ['vehicle:uuid,vehicle_license_plate']);

        return $charge;
    }

    /**
     * Método getForReceiptPdf.
     */
    public function getForReceiptPdf(string $uuid): ?AffiliateAdminCharge
    {
        return $this->query()
            ->select([
                'id',
                'uuid',
                'company_uuid',
                'vehicle_uuid',
                'payment_reference',
                'charge_type',
                'payment_method',
                'concept',
                'amount',
                'status',
                'payment_date',
                'period_date',
                'due_date',
                'next_payment_date',
                'bank_reference',
                'notes',
                'created_at',
            ])
            ->with([
                'company',
                'company.municipality:id,name',
                'vehicle:uuid,vehicle_license_plate,type_of_service,model,passenger_capacity,brand_uuid,third_party_uuid',
                'vehicle.brand:uuid,description',
                'vehicle.thirdParty:uuid,document_number,company_name,first_name,last_name,address,email,phone',
            ])
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Método createAffiliateAdminCharge.
     */
    public function createAffiliateAdminCharge(array $data): AffiliateAdminCharge
    {
        $charge = $this->transaction(function () use ($data) {
            // Regla de Negocio: Solo puede existir una creación inicial de CUOTA_ADMINISTRACION por vehículo.
            if (isset($data['charge_type']) && $data['charge_type'] === 'CUOTA_ADMINISTRACION') {
                $exists = AffiliateAdminCharge::query()
                    ->where('vehicle_uuid', '=', $data['vehicle_uuid'])
                    ->where('charge_type', '=', 'CUOTA_ADMINISTRACION')
                    ->exists();

                if ($exists) {
                    throw new \RuntimeException('Este vehículo ya tiene una Cuota de Administración registrada. Los meses posteriores se generarán automáticamente al aplicar los pagos.');
                }
            }

            // Gestionar la referencia de pago (Cuenta) si está vacía
            if (empty($data['payment_reference'])) {
                $data['payment_reference'] = $this->resolvePaymentReference($data['vehicle_uuid']);
            }

            // Establecer estado por defecto si no viene
            if (! isset($data['status'])) {
                $data['status'] = 'PENDIENTE';
            }

            // Si el estado es PAGADO, asegurar que tenga fecha de pago
            if ($data['status'] === 'PAGADO' && ! isset($data['payment_date'])) {
                $data['payment_date'] = Carbon::now()->toDateString();
            }

            $record = AffiliateAdminCharge::create($data);

            // Generar el próximo mes automáticamente si el primer registro se crea ya como PAGADO
            if ($record->status === 'PAGADO') {
                $this->generateNextPeriodCharge($record);
            }

            return $record;
        });

        return $charge;
    }

    /**
     * Método updateAffiliateAdminCharge.
     */
    public function updateAffiliateAdminCharge(string $uuid, array $data): AffiliateAdminCharge
    {
        /** @var AffiliateAdminCharge $charge */
        $charge = $this->transaction(function () use ($uuid, $data) {
            /** @var AffiliateAdminCharge $record */
            $record = $this->findByUuid($uuid);

            // Asegurar consistencia de la referencia de pago
            if (empty($record->payment_reference) && empty($data['payment_reference'])) {
                $vehicleUuid = $data['vehicle_uuid'] ?? $record->vehicle_uuid;
                $data['payment_reference'] = $this->resolvePaymentReference($vehicleUuid, $record->uuid);
            }

            // Validar consistencia si se cambia a PAGADO
            if (isset($data['status']) && $data['status'] === 'PAGADO') {
                if (! isset($data['payment_date'])) {
                    $data['payment_date'] = $record->payment_date ?? Carbon::now()->toDateString();
                }
            }

            $oldStatus = $record->status;
            $record->update($data);

            // Generar el próximo mes automáticamente si el registro se cambia a PAGADO desde el formulario
            if (isset($data['status']) && $data['status'] === 'PAGADO' && $oldStatus !== 'PAGADO') {
                $this->generateNextPeriodCharge($record);
            }

            return $record->fresh();
        });

        return $charge;
    }

    /**
     * Método applyMonthlyPayment.
     */
    public function applyMonthlyPayment(string $uuid, array $paymentData): AffiliateAdminCharge
    {
        /** @var AffiliateAdminCharge $charge */
        $charge = $this->transaction(function () use ($uuid, $paymentData) {
            /** @var AffiliateAdminCharge|null $record */
            $record = $this->findByUuid($uuid);
            if (! $record) {
                throw new \RuntimeException("El cargo de administración con UUID: {$uuid} no existe.");
            }

            $record->update([
                'charge_type' => 'PAGO_MENSUALIDAD',
                'status' => 'PAGADO',
                'payment_method' => $paymentData['payment_method'] ?? 'EFECTIVO',
                'payment_date' => $paymentData['payment_date'] ?? Carbon::now()->toDateString(),
                'bank_reference' => $paymentData['bank_reference'] ?? null,
                'notes' => $paymentData['notes'] ?? $record->notes,
            ]);

            // Automatizar la generación del siguiente período (si aplica)
            $this->generateNextPeriodCharge($record);

            return $record->fresh();
        });

        return $charge;
    }

    /**
     * Método updateStatus.
     */
    public function updateStatus(string $uuid, string $status, ?string $paymentDate = null, ?string $bankReference = null, ?string $paymentMethod = null): AffiliateAdminCharge
    {
        /** @var AffiliateAdminCharge $charge */
        $charge = $this->transaction(function () use ($uuid, $status, $paymentDate, $bankReference, $paymentMethod) {
            /** @var AffiliateAdminCharge|null $record */
            $record = $this->findByUuid($uuid);
            if (! $record) {
                throw new \RuntimeException("El cargo de administración con UUID: {$uuid} no existe.");
            }

            $updateData = ['status' => $status];

            if ($status === 'PAGADO') {
                $updateData['payment_date'] = $paymentDate ?? Carbon::now()->toDateString();
                $updateData['bank_reference'] = $bankReference;
                $updateData['payment_method'] = $paymentMethod ?? 'EFECTIVO';
            } elseif ($status === 'ANULADO') {
                $updateData['payment_date'] = null;
                $updateData['bank_reference'] = null;
            }

            $record->update($updateData);

            // Si se marcó como PAGADO, generar automáticamente el siguiente periodo
            if ($status === 'PAGADO') {
                $this->generateNextPeriodCharge($record);
            }

            return $record->fresh();
        });

        return $charge;
    }

    /**
     * Método deleteAffiliateAdminCharge.
     */
    public function deleteAffiliateAdminCharge(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el cargo de administración con UUID: {$uuid}");
        }
    }

    /**
     * Método getByVehicle.
     */
    public function getByVehicle(string $vehicleUuid): Collection
    {
        return $this->query()
            ->with('vehicle:uuid,vehicle_license_plate')
            ->where('vehicle_uuid', $vehicleUuid)
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Método updateOverdueCharges.
     */
    public function updateOverdueCharges(): array
    {
        return $this->transaction(function () {
            $now = Carbon::now();
            $table = $this->getModel()->getTable();

            // 1. Pasar de PENDIENTE a VENCIDO
            $updatedPending = $this->query()
                ->where('status', 'PENDIENTE')
                ->where('due_date', '<', $now->toDateString())
                ->update(['status' => 'VENCIDO']);

            // 2. Pasar de VENCIDO a EN_MORA si tiene más de 30 días de vencimiento
            $updatedOverdue = $this->query()
                ->where('status', 'VENCIDO')
                ->where('due_date', '<', $now->copy()->subDays(30)->toDateString())
                ->update(['status' => 'EN_MORA']);

            $totalUpdated = $updatedPending + $updatedOverdue;

            return [
                'updated_count' => $totalUpdated,
                'message' => "Se actualizaron {$totalUpdated} cobros (de PENDIENTE a VENCIDO y de VENCIDO a EN_MORA).",
            ];
        });
    }

    /**
     * Método getSummary.
     */
    public function getSummary(string $dateFrom, string $dateTo, ?string $vehicleUuid = null): array
    {
        $query = $this->query()->whereBetween('period_date', [$dateFrom, $dateTo]);

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        // Totales por estado
        $totalsByStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Totales por tipo de cobro
        $totalsByType = (clone $query)
            ->select('charge_type', DB::raw('COUNT(*) as total_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('charge_type')
            ->get();

        return [
            'totals' => [
                'pending' => $totalsByStatus['PENDIENTE']->total_amount ?? 0,
                'paid' => $totalsByStatus['PAGADO']->total_amount ?? 0,
                'overdue' => $totalsByStatus['VENCIDO']->total_amount ?? 0,
                'in_arrears' => $totalsByStatus['EN_MORA']->total_amount ?? 0,
                'canceled' => $totalsByStatus['ANULADO']->total_amount ?? 0,
                'general' => (clone $query)->sum('amount'),
            ],
            'count_by_type' => $totalsByType,
            'count_by_status' => $totalsByStatus->values(),
        ];
    }

    /**
     * Método getNextDueDate.
     */
    public function getNextDueDate(string $vehicleUuid): ?AffiliateAdminCharge
    {
        /** @var AffiliateAdminCharge|null */
        return $this->query()
            ->where('vehicle_uuid', $vehicleUuid)
            ->whereNotIn('status', ['PAGADO', 'ANULADO'])
            ->orderBy('due_date', 'asc')
            ->first();
    }

    /**
     * Método getPaymentHistory.
     */
    public function getPaymentHistory(string $paymentReference): Collection
    {
        return $this->query()
            ->with('vehicle:uuid,vehicle_license_plate')
            ->where('payment_reference', $paymentReference)
            ->whereIn('status', ['PAGADO', 'PENDIENTE', 'VENCIDO', 'EN_MORA'])
            ->orderBy('period_date', 'asc')
            ->get();
    }

    /**
     * Método getPendingCharge.
     */
    public function getPendingCharge(string $paymentReference): ?AffiliateAdminCharge
    {
        /** @var AffiliateAdminCharge|null */
        return $this->query()
            ->with('vehicle:uuid,vehicle_license_plate')
            ->where('payment_reference', $paymentReference)
            ->whereIn('status', ['PENDIENTE', 'EN_MORA'])
            ->orderBy('period_date', 'asc')
            ->first();
    }

    // ──────────────────────────────────────────────────────────────
    //  Métodos privados auxiliares
    // ──────────────────────────────────────────────────────────────

    /**
     * Aplica el filtro para mostrar solo los cobros activos o el último pagado temporalmente.
     */
    private function applyActiveChargesFilter($query): void
    {
        $query->where(function ($q) {
            $today = now()->toDateString();

            $q->where(function ($subPending) use ($today) {
                $subPending->whereIn('status', ['PENDIENTE', 'VENCIDO', 'EN_MORA'])
                    ->where('period_date', '<=', $today);
            })
                ->orWhere(function ($subPaid) use ($today) {
                    $subPaid->where('status', 'PAGADO')
                        ->whereRaw('id = (
                          SELECT id FROM affiliate_admin_charges AS c2 
                          WHERE c2.vehicle_uuid = affiliate_admin_charges.vehicle_uuid 
                            AND c2.status = \'PAGADO\' 
                          ORDER BY c2.period_date DESC 
                          LIMIT 1
                      )')
                        ->whereNotExists(function ($ex) use ($today) {
                            $ex->select(DB::raw(1))
                                ->from('affiliate_admin_charges as c3')
                                ->whereColumn('c3.vehicle_uuid', 'affiliate_admin_charges.vehicle_uuid')
                                ->whereIn('c3.status', ['PENDIENTE', 'VENCIDO', 'EN_MORA'])
                                ->where('c3.period_date', '<=', $today);
                        });
                });
        });
    }

    /**
     * Resolver la referencia de pago para un vehículo: hereda la existente o genera una nueva.
     */
    private function resolvePaymentReference(string $vehicleUuid, ?string $excludeUuid = null): string
    {
        $query = $this->query()
            ->where('vehicle_uuid', $vehicleUuid)
            ->whereNotNull('payment_reference');

        if ($excludeUuid) {
            $query->where('uuid', '!=', $excludeUuid);
        }

        $existing = $query->value('payment_reference');

        return $existing ?? Carbon::now()->format('ymdHis').rand(10, 99);
    }

    /**
     * Generar automáticamente el cobro del siguiente periodo a partir de uno pagado.
     */
    private function generateNextPeriodCharge(AffiliateAdminCharge $currentCharge): ?AffiliateAdminCharge
    {
        try {
            // Permitir automatizar tanto la Cuota Inicial como las Mensualidades subsecuentes
            if (! in_array($currentCharge->charge_type, ['CUOTA_ADMINISTRACION', 'PAGO_MENSUALIDAD'])) {
                return null;
            }

            // Calcular nuevas fechas (incrementar un mes)
            $nextPeriodDate = Carbon::parse($currentCharge->period_date)->addMonth();
            $nextDueDate = Carbon::parse($currentCharge->due_date)->addMonth();
            $nextPaymentDate = $currentCharge->next_payment_date
                ? Carbon::parse($currentCharge->next_payment_date)->addMonth()
                : null;

            // Evitar duplicados (mismo vehículo, mismo periodo y tipo de mensualidad)
            $exists = $this->query()
                ->where('vehicle_uuid', $currentCharge->vehicle_uuid)
                ->where('period_date', $nextPeriodDate->format('Y-m-d'))
                ->where('charge_type', 'PAGO_MENSUALIDAD')
                ->exists();

            if ($exists) {
                return null;
            }

            // Actualizar inteligentemente el mes y año en el concepto
            $newConcept = $this->updateConceptForNextMonth($currentCharge->concept, $nextPeriodDate);

            // Gestionar la referencia de pago (Cuenta)
            $paymentReference = $currentCharge->payment_reference;
            if (empty($paymentReference)) {
                $paymentReference = Carbon::now()->format('ymdHis').rand(10, 99);
            }

            return AffiliateAdminCharge::create([
                'company_uuid' => $currentCharge->company_uuid,
                'vehicle_uuid' => $currentCharge->vehicle_uuid,
                'payment_reference' => $paymentReference,
                'charge_type' => 'PAGO_MENSUALIDAD',
                'concept' => $newConcept,
                'amount' => $currentCharge->amount,
                'currency_code' => $currentCharge->currency_code,
                'period_date' => $nextPeriodDate->format('Y-m-d'),
                'due_date' => $nextDueDate->format('Y-m-d'),
                'next_payment_date' => $nextPaymentDate ? $nextPaymentDate->format('Y-m-d') : $nextDueDate->format('Y-m-d'),
                'late_fee_percentage' => $currentCharge->late_fee_percentage,
                'status' => 'PENDIENTE',
                'notes' => 'Generado automáticamente a partir del cobro anterior pagado.',
            ]);
        } catch (\Exception $e) {
            Logger::error('Error generando cobro automático del próximo mes: '.$e->getMessage(), $e);

            return null;
        }
    }

    /**
     * Intenta actualizar el nombre del mes en el concepto descriptivo.
     */
    private function updateConceptForNextMonth(string $concept, Carbon $nextDate): string
    {
        $months = [
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
        ];

        $monthName = $months[$nextDate->month - 1];
        $year = $nextDate->year;

        // Si el concepto contiene el nombre de algún mes, lo reemplazamos
        foreach ($months as $m) {
            if (stripos($concept, $m) !== false) {
                $concept = (string) preg_replace('/'.$m.'/i', $monthName, $concept);
                $concept = (string) preg_replace('/\b20\d{2}\b/', (string) $year, $concept);
                // Reemplazar el prefijo viejo por el nuevo de mensualidad
                $concept = (string) preg_replace('/Cuota de Administración/i', 'Cuota de mensualidad', $concept);

                return $concept;
            }
        }

        // Si no se pudo detectar mes, simplemente generamos uno nuevo descriptivo
        return "Mensualidad {$monthName} {$year}";
    }

    /**
     * Método generateReceipt.
     */
    public function generateReceipt(string $chargeUuid): ?AffiliateAdminCharge
    {
        try {
            return $this->query()
                ->with([
                    'vehicle.affiliate:uuid,full_name',
                    'company:uuid,name',
                ])
                ->where('uuid', $chargeUuid)
                ->where('status', 'PAGADO')
                ->first();
        } catch (\Exception $e) {
            Logger::error('Error consultando recibo: '.$e->getMessage(), $e);

            return null;
        }
    }
}
