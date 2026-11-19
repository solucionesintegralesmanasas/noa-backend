<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\Owner;
use App\Models\RentalContract;
use App\Models\RentalDiscount;
use App\Models\RentalInstallment;
use App\Models\Vehicle;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para gestión de contratos de alquiler.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-11-18
 */
class RentalContractService extends BaseService
{
    /**
     * Campos buscables.
     */
    protected array $searchableFields = ['contract_number', 'vehicle_license_plate', 'owner_name'];

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // Columnas por las que el listado admite orden (contrato `sort_by`).
        // Las de relación se resuelven con subconsulta escalar para no duplicar filas.
        $this->sortableFields = [
            'contract_number' => 'contract_number',
            'vehicle.vehicle_license_plate' => fn (Builder $query, string $direction) => $query->orderBy(
                DB::table('vehicles')
                    ->select('vehicles.vehicle_license_plate')
                    ->whereColumn('vehicles.uuid', 'rental_contracts.vehicle_uuid'),
                $direction
            ),
            'owner.owner_name' => fn (Builder $query, string $direction) => $query->orderBy(
                DB::table('owners')
                    ->select('owners.owner_name')
                    ->whereColumn('owners.uuid', 'rental_contracts.owner_uuid'),
                $direction
            ),
            'start_date' => 'rental_contracts.start_date',
            'monthly_fee' => 'rental_contracts.monthly_fee',
            'status' => 'rental_contracts.status',
        ];
    }

    /**
     * Obtener instancia del modelo.
     */
    protected function getModelInstance(): Model
    {
        return new RentalContract();
    }

    /**
     * Consultar con relaciones y totales agregados en SQL.
     */
    public function query(): Builder
    {
        return parent::query()
            ->with(['vehicle', 'owner'])
            ->withSum('installments as total_billed', 'gross_amount')
            ->withSum('installments as total_discounts', 'discount_amount')
            ->withSum('installments as total_paid', 'paid_amount');
    }

    /**
     * Listar contratos paginados.
     */
    public function listWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $vehicleUuid = null,
        ?string $status = null,
        ?string $sortBy = null,
        ?string $sortOrder = null
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                ->orWhereHas('vehicle', function ($subQ) use ($search) {
                    $subQ->where('vehicle_license_plate', 'like', "%{$search}%");
                })
                ->orWhereHas('owner', function ($subQ) use ($search) {
                    $subQ->where('owner_name', 'like', "%{$search}%");
                });
            });
        }

        $this->applySorting($query, $sortBy, $sortOrder);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Obtener detalle completo con cuotas.
     */
    public function getDetail(string $uuid): ?RentalContract
    {
        return $this->findByUuid($uuid, ['*'], ['vehicle.brand', 'vehicle.vehicleClass', 'owner', 'installments', 'discounts.installment']);
    }

    /**
     * Crear contrato y generar cuotas automáticamente.
     */
    public function create(array $data): RentalContract
    {
        return DB::transaction(function () use ($data) {
            // Validar que no exista otro contrato activo para el mismo vehículo
            $existingActive = RentalContract::where('vehicle_uuid', $data['vehicle_uuid'])
                ->where('status', 'active')
                ->exists();

            if ($existingActive) {
                throw new \Exception('Ya existe un contrato activo para este vehículo.');
            }

            // Resolver propietario desde el vehículo si no se envió explícitamente
            if (empty($data['owner_uuid'])) {
                $data['owner_uuid'] = $this->resolveOwnerUuidForVehicle($data['vehicle_uuid']);
            }

            // Calcular fecha de fin
            $startDate = Carbon::parse($data['start_date']);
            $monthlyTerm = (int) ($data['monthly_term'] ?? 12);
            $endDate = $startDate->copy()->addMonths($monthlyTerm)->subDay();
            $data['monthly_term'] = $monthlyTerm;

            // Generar número de contrato secuencial de 4 dígitos por empresa
            $contractNumber = $this->getNextContractNumber($data['company_uuid'] ?? null, $data['vehicle_uuid']);

            // Crear contrato
            $contract = RentalContract::create(array_merge($data, [
                'contract_number' => $contractNumber,
                'end_date' => $endDate,
            ]));

            // Generar cuotas
            $this->generateInstallments($contract);

            return $contract->fresh(['installments']);
        });
    }

    /**
     * Genera el siguiente número secuencial de contrato de 4 dígitos por empresa.
     */
    private function getNextContractNumber(?string $companyUuid, string $vehicleUuid): string
    {
        $resolvedCompanyUuid = $companyUuid
            ?? request()->attributes->get('current_company_uuid')
            ?? Vehicle::query()->where('uuid', $vehicleUuid)->value('company_uuid');

        $query = DB::table('rental_contracts')->lockForUpdate();

        if ($resolvedCompanyUuid) {
            $query->where('company_uuid', $resolvedCompanyUuid);
        }

        $maxNumber = (int) ($query->selectRaw('MAX(CAST(contract_number AS UNSIGNED)) as max_num')->value('max_num') ?? 0);

        return str_pad((string) ($maxNumber + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Resuelve el UUID de propietario a partir del vehículo.
     * Si el vehículo no tiene fila en `owners` pero sí `third_party_uuid`,
     * reutiliza o crea el registro de propietario a partir de ese tercero.
     */
    private function resolveOwnerUuidForVehicle(string $vehicleUuid): string
    {
        $ownerUuid = Owner::query()
            ->where('vehicle_uuid', $vehicleUuid)
            ->value('uuid');

        if ($ownerUuid) {
            return $ownerUuid;
        }

        $vehicle = Vehicle::query()
            ->with('thirdParty')
            ->where('uuid', $vehicleUuid)
            ->first();

        $thirdParty = $vehicle?->thirdParty;

        if (! $thirdParty) {
            throw new \Exception('El vehículo seleccionado no tiene un propietario registrado.');
        }

        $existingByThirdParty = Owner::query()
            ->where('third_party_uuid', $thirdParty->uuid)
            ->value('uuid');

        if ($existingByThirdParty) {
            return $existingByThirdParty;
        }

        $owner = Owner::create([
            'third_party_uuid' => $thirdParty->uuid,
            'vehicle_uuid' => $vehicle->uuid,
            'document_type_uuid' => $thirdParty->document_type_uuid,
            'owner_name' => $thirdParty->full_name ?: ($thirdParty->trade_name ?: $thirdParty->document_number),
            'document_number' => $thirdParty->document_number,
            'verification_digit' => $thirdParty->nit_check_digit,
        ]);

        return $owner->uuid;
    }

    /**
     * Generar todas las cuotas mensuales independientes.
     */
    private function generateInstallments(RentalContract $contract): void
    {
        $startDate = Carbon::parse($contract->start_date);
        $monthlyFee = (float) $contract->monthly_fee;

        for ($i = 1; $i <= $contract->monthly_term; $i++) {
            $dueDate = $startDate->copy()->addMonths($i - 1)->endOfMonth();

            RentalInstallment::create([
                'rental_contract_uuid' => $contract->uuid,
                'installment_number' => $i,
                'due_date' => $dueDate,
                'gross_amount' => $monthlyFee,
                'previous_balance' => 0,
                'discount_amount' => 0,
                'net_amount' => $monthlyFee,
                'paid_amount' => 0,
                'pending_balance' => $monthlyFee,
                'status' => $this->calculateStatus($dueDate, 0.0, $monthlyFee),
            ]);
        }
    }

    /**
     * Registrar pago o abono parcial de una cuota.
     * - Si es un abono parcial y existe cuota siguiente: la cuota queda cerrada en estado `partial`
     *   y su saldo pendiente se traslada únicamente a la cuota del mes siguiente (`previous_balance`).
     * - Si la cuota se paga completa: cambia a `paid` y también marca como `paid` (saldo pendiente 0)
     *   las cuotas anteriores en estado `partial` cuyo saldo había sido trasladado.
     */
    public function updatePayment(string $uuid, array $data): RentalInstallment
    {
        return DB::transaction(function () use ($uuid, $data) {
            $installment = RentalInstallment::with('contract')->where('uuid', $uuid)->firstOrFail();

            if ($installment->contract?->status === 'cancelled') {
                throw new \InvalidArgumentException('No se pueden registrar pagos en un contrato cancelado.');
            }

            $nextInstallments = RentalInstallment::where('rental_contract_uuid', $installment->rental_contract_uuid)
                ->where('installment_number', '>', $installment->installment_number)
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            $nextInstallment = $nextInstallments->first();

            if ($installment->status === 'partial' && $nextInstallment !== null && (float) $nextInstallment->previous_balance > 0) {
                throw new \InvalidArgumentException('Esta cuota ya fue cerrada en estado parcial; el saldo pendiente fue trasladado al mes siguiente.');
            }

            $baseAmount = (float) $installment->gross_amount + (float) $installment->previous_balance;
            $discountAmount = array_key_exists('discount_amount', $data) && $data['discount_amount'] !== null
                ? (float) $data['discount_amount']
                : (float) $installment->discount_amount;

            if ($discountAmount > $baseAmount) {
                throw new \InvalidArgumentException('El descuento no puede superar el valor de la cuota.');
            }

            $netAmount = $baseAmount - $discountAmount;
            $currentPaid = (float) $installment->paid_amount;

            if (array_key_exists('payment_amount', $data) && $data['payment_amount'] !== null) {
                $abono = (float) $data['payment_amount'];
                $newPaid = $currentPaid + $abono;
            } else {
                $newPaid = array_key_exists('paid_amount', $data) && $data['paid_amount'] !== null
                    ? (float) $data['paid_amount']
                    : $currentPaid;
            }

            $paymentDate = $data['payment_date'] ?? ($newPaid > 0 ? ($installment->payment_date?->toDateString() ?? now()->toDateString()) : null);

            // Caso 1: Pago con saldo a favor (excedente sobre el valor neto de esta cuota) -> cascada FIFO
            if ($newPaid > $netAmount + 0.001) {
                $excedente = $newPaid - $netAmount;
                $totalNextRemaining = $nextInstallments->sum(
                    fn (RentalInstallment $next) => max(
                        0.0,
                        ((float) $next->gross_amount - (float) $next->discount_amount) - (float) $next->paid_amount
                    )
                );

                if ($excedente > $totalNextRemaining + 0.001) {
                    throw new \InvalidArgumentException('El monto pagado supera el saldo pendiente total del contrato.');
                }

                // Liquidar la cuota actual
                $installment->fill([
                    'paid_amount' => $netAmount,
                    'payment_date' => $paymentDate,
                    'discount_amount' => $discountAmount,
                    'notes' => array_key_exists('notes', $data) ? $data['notes'] : $installment->notes,
                ]);
                $this->recalculateInstallment($installment);
                $this->closePreviousPartialInstallments($installment);

                // Limpiar saldo arrastrado en la siguiente cuota si existía
                if ($nextInstallment !== null && (float) $nextInstallment->previous_balance > 0) {
                    $nextInstallment->previous_balance = 0;
                    $this->recalculateInstallment($nextInstallment);
                }

                // Aplicar el saldo a favor como abono adelantado a las cuotas siguientes (sin cerrarlas si quedan parciales)
                foreach ($nextInstallments as $next) {
                    if ($excedente <= 0.001) {
                        break;
                    }

                    $nextNet = max(0.0, (float) $next->gross_amount + (float) $next->previous_balance - (float) $next->discount_amount);
                    $nextRemaining = max(0.0, $nextNet - (float) $next->paid_amount);

                    if ($nextRemaining <= 0.001) {
                        continue;
                    }

                    $aplicar = min($excedente, $nextRemaining);
                    $next->paid_amount = (float) $next->paid_amount + $aplicar;
                    $next->payment_date = $paymentDate;
                    if (empty($next->notes)) {
                        $next->notes = "Saldo a favor aplicado desde cuota #{$installment->installment_number}";
                    }
                    $this->recalculateInstallment($next);
                    $excedente -= $aplicar;
                }

                $this->syncContractCompletionStatus($installment->rental_contract_uuid);

                return $installment->fresh();
            }

            // Caso 2: Pago normal o abono parcial dentro del valor neto de la cuota
            $installment->fill([
                'paid_amount' => $newPaid,
                'payment_date' => $paymentDate,
                'discount_amount' => $discountAmount,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $installment->notes,
            ]);

            $this->recalculateInstallment($installment);

            // Si quedó en estado parcial y hay mes siguiente: trasladar saldo únicamente al mes siguiente
            if ($installment->status === 'partial' && $nextInstallment !== null) {
                $nextInstallment->previous_balance = (float) $installment->pending_balance;
                $this->recalculateInstallment($nextInstallment);
            }

            // Si se pagó completa: marcar también como pagadas las cuotas anteriores en estado parcial
            if ($installment->status === 'paid') {
                $this->closePreviousPartialInstallments($installment);

                if ($nextInstallment !== null && (float) $nextInstallment->previous_balance > 0) {
                    $nextInstallment->previous_balance = 0;
                    $this->recalculateInstallment($nextInstallment);
                }
            }

            $this->syncContractCompletionStatus($installment->rental_contract_uuid);

            return $installment->fresh();
        });
    }

    /**
     * Marca como pagadas las cuotas anteriores en estado `partial` ÚNICAMENTE cuando
     * su saldo pendiente realmente fue trasladado (`previous_balance > 0`) a la cuota siguiente.
     */
    private function closePreviousPartialInstallments(RentalInstallment $paidInstallment): void
    {
        $previousInstallments = RentalInstallment::where('rental_contract_uuid', $paidInstallment->rental_contract_uuid)
            ->where('installment_number', '<', $paidInstallment->installment_number)
            ->orderByDesc('installment_number')
            ->lockForUpdate()
            ->get();

        $carriedFromPrevious = (float) $paidInstallment->previous_balance;

        foreach ($previousInstallments as $prev) {
            if ($prev->status !== 'partial' || $carriedFromPrevious <= 0) {
                break;
            }

            $carriedFromPrevious = (float) $prev->previous_balance;
            $prev->pending_balance = 0;
            $prev->status = 'paid';
            $prev->save();
        }
    }

    /**
     * Recalcular una sola cuota.
     */
    private function recalculateInstallment(RentalInstallment $installment): void
    {
        $netAmount = max(
            0.0,
            (float) $installment->gross_amount
                + (float) $installment->previous_balance
                - (float) $installment->discount_amount
        );
        $paidAmount = (float) $installment->paid_amount;
        $pendingBalance = max(0.0, $netAmount - $paidAmount);

        $installment->net_amount = $netAmount;
        $installment->pending_balance = $pendingBalance;
        $installment->status = $this->calculateStatus(
            $installment->due_date,
            $paidAmount,
            $pendingBalance
        );
        $installment->save();
    }

    /**
     * Calcular estado basado en fechas y montos.
     */
    private function calculateStatus(Carbon $dueDate, float $paidAmount, float $pendingBalance): string
    {
        if ($pendingBalance <= 0) {
            return 'paid';
        }
        if ($paidAmount > 0) {
            return 'partial';
        }
        if ($dueDate->isPast()) {
            return 'overdue';
        }
        return 'pending';
    }

    /**
     * Registrar un descuento aplicado exclusivamente a la cuota vigente del contrato.
     */
    public function addDiscount(string $contractUuid, array $data): RentalDiscount
    {
        return DB::transaction(function () use ($contractUuid, $data) {
            $contract = RentalContract::where('uuid', $contractUuid)->firstOrFail();

            if ($contract->status !== 'active') {
                throw new \InvalidArgumentException('Solo se pueden registrar descuentos en contratos activos.');
            }

            $currentInstallment = $this->resolveCurrentInstallment($contract->uuid);

            if (! $currentInstallment) {
                throw new \InvalidArgumentException('No existe una cuota vigente pendiente para aplicar el descuento.');
            }

            $amount = (float) $data['amount'];
            $available = ((float) $currentInstallment->gross_amount + (float) $currentInstallment->previous_balance)
                - (float) $currentInstallment->discount_amount
                - (float) $currentInstallment->paid_amount;

            if ($amount > $available + 0.001) {
                throw new \InvalidArgumentException('El valor del descuento supera el saldo disponible de la cuota vigente.');
            }

            $discount = RentalDiscount::create([
                'company_uuid' => $contract->company_uuid,
                'rental_contract_uuid' => $contract->uuid,
                'rental_installment_uuid' => $currentInstallment->uuid,
                'discount_date' => $data['discount_date'],
                'concept' => $data['concept'],
                'amount' => $amount,
                'observations' => $data['observations'] ?? null,
            ]);

            $this->syncInstallmentDiscounts($currentInstallment);

            return $discount->load('installment');
        });
    }

    /**
     * Eliminar un descuento de la cuota vigente y recalcularla.
     */
    public function deleteDiscount(string $discountUuid): void
    {
        DB::transaction(function () use ($discountUuid) {
            $discount = RentalDiscount::with(['contract', 'installment'])->where('uuid', $discountUuid)->firstOrFail();

            if ($discount->contract?->status !== 'active') {
                throw new \InvalidArgumentException('Solo se pueden eliminar descuentos en contratos activos.');
            }

            $installment = $discount->installment;
            if (! $installment) {
                $discount->delete();

                return;
            }

            $currentInstallment = $this->resolveCurrentInstallment($discount->rental_contract_uuid);
            if (! $currentInstallment || $currentInstallment->uuid !== $installment->uuid) {
                throw new \InvalidArgumentException('Solo se pueden eliminar descuentos de la cuota vigente.');
            }

            $discount->delete();
            $this->syncInstallmentDiscounts($installment);
        });
    }

    /**
     * Obtiene la cuota vigente del contrato (la primera aún abierta para cobro).
     */
    private function resolveCurrentInstallment(string $contractUuid): ?RentalInstallment
    {
        $installments = RentalInstallment::where('rental_contract_uuid', $contractUuid)
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        $count = $installments->count();

        foreach ($installments as $index => $inst) {
            if (in_array($inst->status, ['pending', 'overdue'], true)) {
                return $inst;
            }

            if ($inst->status === 'partial') {
                $next = ($index + 1 < $count) ? $installments[$index + 1] : null;
                if ($next === null || (float) $next->previous_balance <= 0) {
                    return $inst;
                }
            }
        }

        return null;
    }

    /**
     * Sincroniza `discount_amount` de la cuota a partir de sus descuentos registrados y recalcula.
     */
    private function syncInstallmentDiscounts(RentalInstallment $installment): void
    {
        $totalDiscounts = (float) RentalDiscount::where('rental_installment_uuid', $installment->uuid)->sum('amount');
        $installment->discount_amount = $totalDiscounts;
        $this->recalculateInstallment($installment);

        if ($installment->status === 'paid') {
            $this->closePreviousPartialInstallments($installment);
        }

        $this->syncContractCompletionStatus($installment->rental_contract_uuid);
    }

    /**
     * Si todas las cuotas del contrato están pagadas, cierra el contrato automáticamente (`finished`).
     */
    private function syncContractCompletionStatus(string $contractUuid): void
    {
        $hasUnpaid = RentalInstallment::where('rental_contract_uuid', $contractUuid)
            ->where('status', '!=', 'paid')
            ->exists();

        if (! $hasUnpaid) {
            RentalContract::where('uuid', $contractUuid)
                ->where('status', 'active')
                ->update(['status' => 'finished']);
        }
    }

    /**
     * Cerrar contrato.
     */
    public function closeContract(string $uuid, string $status): RentalContract
    {
        return $this->transaction(function () use ($uuid, $status) {
            $contract = $this->findByUuid($uuid);
            if (!$contract) {
                throw new \Exception('Contrato no encontrado.');
            }

            $contract->update(['status' => $status]);

            // Actualizar estado de cuotas pendientes
            if ($status === 'cancelled') {
                RentalInstallment::where('rental_contract_uuid', $uuid)
                    ->whereNotIn('status', ['paid'])
                    ->update(['status' => 'cancelled']);
            }

            return $contract->fresh(['installments']);
        });
    }
}
