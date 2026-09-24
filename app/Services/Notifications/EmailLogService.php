<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Mail\VehicleExpiryDigestMail;
use App\Models\EmailNotificationLog;
use App\Models\OperationCard;
use App\Models\SystemConfiguration;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\BaseService;
use App\Utils\OwnCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envía el digest consolidado de vencimientos al correo principal de la empresa.
 *
 * Cadencia:
 * - 15 días antes: un único correo de recordatorio por documento.
 * - 5 a 1 día antes: un digest diario.
 * - Día de vencimiento y vencidos: dos digest al día (mañana y tarde).
 * Solo cubre vehículos activos de la empresa propia; los afiliados y terceros
 * no reciben correo y siguen únicamente en las notificaciones de la plataforma.
 */
class EmailLogService extends BaseService
{
    public const REMINDER_DAYS = 15;

    public const WINDOW_DAYS = 5;

    public const SLOT_RECORDATORIO = 'recordatorio_15';

    public const SLOT_DIARIO = 'digest_diario';

    public const SLOT_MANANA = 'digest_manana';

    public const SLOT_TARDE = 'digest_tarde';

    public function getModelInstance(): Model
    {
        return new EmailNotificationLog;
    }

    /**
     * Procesa los vencimientos de todas las empresas con correo habilitado.
     * Debe llamarse por schedule en la mañana y en la tarde (ver routes/console.php).
     */
    public function notifyExpiringDocuments(): void
    {
        $configs = SystemConfiguration::query()
            ->where('activate_notifications', true)
            ->where('notify_by_email', true)
            ->whereNotNull('notification_email')
            ->get();

        foreach ($configs as $config) {
            try {
                $this->procesarEmpresa($config);
            } catch (\Exception $e) {
                Log::error("Error procesando digest de vencimientos para la empresa {$config->company_uuid}: ".$e->getMessage());
            }
        }
    }

    /**
     * Construye y envía los correos que correspondan a una empresa.
     */
    private function procesarEmpresa(SystemConfiguration $config): void
    {
        $companyUuid = $config->company_uuid;
        $email = trim((string) $config->notification_email);

        if ($email === '') {
            return;
        }

        $items = $this->recolectarItems($companyUuid);
        if (empty($items)) {
            return;
        }

        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $companyName = $config->company?->business_name ?? $config->company?->trade_name ?? 'la empresa';

        // 1. Recordatorio único a 15 días (uno solo, no diario).
        $recordatorios = array_values(array_filter(
            $items,
            fn (array $item) => $item['days_left'] === self::REMINDER_DAYS
                && ! $this->recordatorioEnviado($item)
        ));

        if (! empty($recordatorios)) {
            $this->enviarDigest(
                $config,
                $email,
                $recordatorios,
                self::SLOT_RECORDATORIO,
                "Recordatorio de vencimientos - {$companyName}",
                'Estos documentos vencen en 15 días. Este es un recordatorio único; el seguimiento diario inicia 5 días antes del vencimiento.',
                true
            );
        }

        // 2. Proceso real: ventana de 5 días, día cero y vencidos.
        $ventana = array_values(array_filter($items, fn (array $item) => $item['days_left'] <= self::WINDOW_DAYS));
        if (empty($ventana)) {
            return;
        }

        $criticos = array_filter($ventana, fn (array $item) => $item['days_left'] <= 0);
        if (! empty($criticos)) {
            $slot = $this->slotPorHora((int) $now->hour);
            if ($slot === null) {
                return;
            }
            $etiqueta = $slot === self::SLOT_MANANA ? ' (mañana)' : ' (tarde)';
            $asunto = "Vencimientos de documentos{$etiqueta} - {$companyName}";
            $intro = 'Hay documentos vencidos o que vencen hoy. Se envían dos reportes al día (mañana y tarde) hasta ponerse al día.';
        } else {
            $slot = self::SLOT_DIARIO;
            $asunto = "Vencimientos próximos - {$companyName}";
            $intro = 'Estos documentos vencen en 5 días o menos. Se envía un reporte diario.';
        }

        if ($this->digestEnviado($companyUuid, $slot, $today)) {
            return;
        }

        $this->enviarDigest($config, $email, $ventana, $slot, $asunto, $intro, false);
    }

    /**
     * Recolecta documentos y tarjetas de vehículos activos de la empresa propia.
     *
     * @return array<int, array{placa: string, documento: string, fecha: string, days_left: int, estado: string, entity_type: string, entity_uuid: string}>
     */
    private function recolectarItems(string $companyUuid): array
    {
        $today = Carbon::today();
        $items = [];

        Vehicle::query()
            ->where('vehicles.company_uuid', $companyUuid)
            ->where('vehicles.is_active', true)
            ->with(['vehicleDocuments', 'operationCards'])
            ->chunk(200, function ($vehicles) use (&$items, $companyUuid, $today) {
                foreach ($vehicles as $vehicle) {
                    if (! OwnCompany::esVehiculoPropio($vehicle, $companyUuid)) {
                        continue;
                    }

                    foreach ($vehicle->vehicleDocuments as $document) {
                        if (! $document->expiry_date) {
                            continue;
                        }
                        $items[] = $this->armarItem(
                            (string) $vehicle->vehicle_license_plate,
                            (string) ($document->document_type ?? 'Documento'),
                            Carbon::parse($document->expiry_date)->startOfDay(),
                            $today,
                            VehicleDocument::class,
                            (string) $document->uuid
                        );
                    }

                    $tarjeta = $vehicle->operationCards
                        ->sortByDesc(fn (OperationCard $t) => (string) $t->expiration_date)
                        ->first();

                    if ($tarjeta && $tarjeta->expiration_date) {
                        $items[] = $this->armarItem(
                            (string) $vehicle->vehicle_license_plate,
                            'Tarjeta de Operación #'.($tarjeta->operating_card_number ?? ''),
                            Carbon::parse($tarjeta->expiration_date)->startOfDay(),
                            $today,
                            OperationCard::class,
                            (string) $tarjeta->uuid
                        );
                    }
                }
            });

        usort($items, fn (array $a, array $b) => $a['days_left'] <=> $b['days_left']);

        return $items;
    }

    /**
     * @return array{placa: string, documento: string, fecha: string, days_left: int, estado: string, entity_type: string, entity_uuid: string}
     */
    private function armarItem(string $placa, string $documento, Carbon $vencimiento, Carbon $today, string $entityType, string $entityUuid): array
    {
        $daysLeft = (int) $today->diffInDays($vencimiento, false);

        return [
            'placa' => $placa,
            'documento' => $documento,
            'fecha' => $vencimiento->format('d/m/Y'),
            'days_left' => $daysLeft,
            'estado' => $daysLeft < 0 ? 'VENCIDO' : ($daysLeft === 0 ? 'VENCE HOY' : 'POR VENCER'),
            'entity_type' => $entityType,
            'entity_uuid' => $entityUuid,
        ];
    }

    /**
     * Resuelve la franja del día para documentos vencidos o que vencen hoy.
     */
    private function slotPorHora(int $hour): ?string
    {
        if ($hour >= 6 && $hour < 12) {
            return self::SLOT_MANANA;
        }
        if ($hour >= 12 && $hour < 20) {
            return self::SLOT_TARDE;
        }

        return null;
    }

    /**
     * Indica si ya se envió hoy el digest de una franja para la empresa.
     */
    private function digestEnviado(string $companyUuid, string $slot, Carbon $today): bool
    {
        return EmailNotificationLog::query()
            ->where('company_uuid', $companyUuid)
            ->where('milestone', $slot)
            ->whereDate('sent_date', $today)
            ->where('status', 'sent')
            ->exists();
    }

    /**
     * Indica si ya se envió el recordatorio único de 15 días para un documento.
     */
    private function recordatorioEnviado(array $item): bool
    {
        return EmailNotificationLog::query()
            ->where('entity_type', $item['entity_type'])
            ->where('entity_uuid', $item['entity_uuid'])
            ->where('milestone', self::SLOT_RECORDATORIO)
            ->where('status', 'sent')
            ->exists();
    }

    /**
     * Envía el digest y registra el resultado para no duplicar envíos.
     */
    private function enviarDigest(
        SystemConfiguration $config,
        string $email,
        array $items,
        string $slot,
        string $asunto,
        string $intro,
        bool $porEntidad
    ): void {
        $companyName = $config->company?->business_name ?? $config->company?->trade_name ?? 'la empresa';

        try {
            Mail::to($email)->send(new VehicleExpiryDigestMail($companyName, $items, $asunto, $intro));
            Log::info("Digest de vencimientos enviado a {$email} | Empresa: {$config->company_uuid} | Slot: {$slot} | Items: ".count($items));

            if ($porEntidad) {
                foreach ($items as $item) {
                    EmailNotificationLog::create([
                        'company_uuid' => $config->company_uuid,
                        'entity_type' => $item['entity_type'],
                        'entity_uuid' => $item['entity_uuid'],
                        'recipient_email' => $email,
                        'milestone' => $slot,
                        'sent_date' => Carbon::today(),
                        'status' => 'sent',
                    ]);
                }
            } else {
                EmailNotificationLog::create([
                    'company_uuid' => $config->company_uuid,
                    'recipient_email' => $email,
                    'milestone' => $slot,
                    'sent_date' => Carbon::today(),
                    'status' => 'sent',
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error enviando digest de vencimientos a {$email}: ".$e->getMessage());

            EmailNotificationLog::create([
                'company_uuid' => $config->company_uuid,
                'recipient_email' => $email,
                'milestone' => $slot,
                'sent_date' => Carbon::today(),
                'status' => 'failed',
            ]);
        }
    }
}
