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
use Illuminate\Database\QueryException;
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

        // 2. Proceso real: próximos (5 a 1 día) y críticos (hoy y vencidos).
        // Cada grupo tiene su propia cadencia para no mezclarlos.
        $proximos = array_values(array_filter(
            $items,
            fn (array $item) => $item['days_left'] >= 1 && $item['days_left'] <= self::WINDOW_DAYS
        ));
        $criticos = array_values(array_filter($items, fn (array $item) => $item['days_left'] <= 0));

        // 2a. Digest diario de próximos: una vez al día, en cualquier corrida.
        if (! empty($proximos) && ! $this->digestEnviado($companyUuid, self::SLOT_DIARIO, $today)) {
            $this->enviarDigest(
                $config,
                $email,
                $proximos,
                self::SLOT_DIARIO,
                "Vencimientos próximos - {$companyName}",
                'Estos documentos vencen en 5 días o menos. Se envía un reporte diario.',
                false
            );
        }

        // 2b. Digest crítico: dos al día según la franja. Fuera de franja
        // solo se omite este digest, nunca el diario de próximos.
        if (! empty($criticos)) {
            $slot = $this->slotPorHora((int) $now->hour);
            if ($slot === null) {
                return;
            }
            if ($this->digestEnviado($companyUuid, $slot, $today)) {
                return;
            }
            $etiqueta = $slot === self::SLOT_MANANA ? ' (mañana)' : ' (tarde)';
            $this->enviarDigest(
                $config,
                $email,
                $criticos,
                $slot,
                "Vencimientos de documentos{$etiqueta} - {$companyName}",
                'Hay documentos vencidos o que vencen hoy. Se envían dos reportes al día (mañana y tarde) hasta ponerse al día.',
                false
            );
        }
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
                        // Los reemplazados quedan INACTIVA al registrar el nuevo;
                        // incluirlos reviviría historial ya superado en el correo.
                        if (strtoupper((string) ($document->status ?? '')) === 'INACTIVA') {
                            continue;
                        }
                        // Los reemplazados quedan INACTIVA al crear el nuevo;
                        // avisar por ellos sería revivir historial superado.
                        if (strtoupper((string) ($document->status ?? '')) === 'INACTIVA') {
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

                    $tarjetas = $vehicle->operationCards
                        ->filter(fn (OperationCard $t) => $t->expiration_date !== null)
                        ->sortByDesc(fn (OperationCard $t) => (string) $t->expiration_date)
                        ->values();

                    // Siempre la tarjeta más reciente; además, cualquier otra
                    // tarjeta vigente (las vencidas no recientes están superadas
                    // y no deben revivir en el reporte).
                    foreach ($tarjetas as $indice => $tarjeta) {
                        $vencimiento = Carbon::parse($tarjeta->expiration_date)->startOfDay();
                        if ($indice > 0 && $vencimiento->lt($today)) {
                            continue;
                        }
                        $items[] = $this->armarItem(
                            (string) $vehicle->vehicle_license_plate,
                            'Tarjeta de Operación #'.($tarjeta->operating_card_number ?? ''),
                            $vencimiento,
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
                // Clave por entidad (sin empresa ni fecha): el recordatorio es
                // único y el índice (entity, milestone) lo garantiza.
                foreach ($items as $item) {
                    $this->registrarEnvio([
                        'company_uuid' => null,
                        'entity_type' => $item['entity_type'],
                        'entity_uuid' => $item['entity_uuid'],
                        'recipient_email' => $email,
                        'milestone' => $slot,
                        'sent_date' => null,
                        'status' => 'sent',
                    ], true);
                }
            } else {
                $this->registrarEnvio([
                    'company_uuid' => $config->company_uuid,
                    'recipient_email' => $email,
                    'milestone' => $slot,
                    'sent_date' => Carbon::today(),
                    'status' => 'sent',
                ], true);
            }
        } catch (\Exception $e) {
            Log::error("Error enviando digest de vencimientos a {$email}: ".$e->getMessage());

            // El fallo se registra sin pisar un envío exitoso concurrente.
            if ($porEntidad) {
                foreach ($items as $item) {
                    $this->registrarEnvio([
                        'company_uuid' => null,
                        'entity_type' => $item['entity_type'],
                        'entity_uuid' => $item['entity_uuid'],
                        'recipient_email' => $email,
                        'milestone' => $slot,
                        'sent_date' => null,
                        'status' => 'failed',
                    ], false);
                }
            } else {
                $this->registrarEnvio([
                    'company_uuid' => $config->company_uuid,
                    'recipient_email' => $email,
                    'milestone' => $slot,
                    'sent_date' => Carbon::today(),
                    'status' => 'failed',
                ], false);
            }
        }
    }

    /**
     * Registra un envío de forma idempotente ante carreras o reintentos.
     * Si la clave ya existe como enviada, no hace nada; si existe como
     * fallida y el envío actual fue exitoso, la promueve a enviada.
     */
    private function registrarEnvio(array $atributos, bool $promoverFallido): void
    {
        try {
            EmailNotificationLog::create($atributos);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            $existente = $this->buscarRegistro($atributos);
            if (! $existente) {
                throw $e;
            }
            if ($existente->status === 'sent' || ! $promoverFallido) {
                Log::info("Digest {$atributos['milestone']} ya registrado para {$atributos['recipient_email']}; se evita el duplicado.");

                return;
            }
            $existente->update([
                'recipient_email' => $atributos['recipient_email'],
                'sent_date' => $atributos['sent_date'] ?? $existente->sent_date,
                'status' => 'sent',
            ]);
        }
    }

    /**
     * Localiza el registro dueño de una clave de idempotencia.
     */
    private function buscarRegistro(array $atributos): ?EmailNotificationLog
    {
        $query = EmailNotificationLog::query()->where('milestone', $atributos['milestone']);
        if (! empty($atributos['entity_uuid'])) {
            $query->where('entity_type', $atributos['entity_type'])
                ->where('entity_uuid', $atributos['entity_uuid']);
        } elseif (! empty($atributos['company_uuid']) && ! empty($atributos['sent_date'])) {
            $query->where('company_uuid', $atributos['company_uuid'])
                ->whereDate('sent_date', $atributos['sent_date']);
        } else {
            return null;
        }

        return $query->first();
    }
}
