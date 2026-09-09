<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Mail\VehicleDocumentExpiringMail;
use App\Models\EmailNotificationLog;
use App\Models\OperationCard;
use App\Models\VehicleDocument;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailLogService extends BaseService
{
    public function getModelInstance(): Model
    {
        return new EmailNotificationLog;
    }

    /**
     * Procesa y notifica los documentos próximos a vencer o vencidos.
     * Esta función debe ser llamada por un Schedule (ej. cada hora) en routes/console.php.
     */
    public function notifyExpiringDocuments(): void
    {
        $documents = VehicleDocument::with(['vehicle.thirdParty', 'company'])->get();

        foreach ($documents as $document) {
            $this->processSingleDocument($document);
        }

        $operationCards = OperationCard::with(['vehicle.thirdParty', 'company'])->get();

        foreach ($operationCards as $card) {
            $this->processOperationCard($card);
        }
    }

    /**
     * Procesa y valida de forma instantánea un solo documento.
     */
    public function processSingleDocument(VehicleDocument $document): void
    {
        if (! $document->expiry_date) {
            return;
        }

        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $expiryDate = Carbon::parse($document->expiry_date)->startOfDay();

        // Positivo si falta tiempo, Negativo si ya pasó, 0 si es hoy.
        $daysDiff = (int) $today->diffInDays($expiryDate, false);

        $milestone = null;

        if ($daysDiff === 30) {
            $milestone = '30_days_before';
        } elseif ($daysDiff === 15) {
            $milestone = '15_days_before';
        } elseif ($daysDiff === 5) {
            $milestone = '5_days_before';
        } elseif ($daysDiff === 1) {
            $milestone = '1_day_before';
        } elseif ($daysDiff === 0) {
            // Día de vencimiento: 3 mensajes (mañana, mediodía, tarde)
            $hour = $now->hour;
            if ($hour >= 6 && $hour < 12) {
                $milestone = '0_days_morning';
            } elseif ($hour >= 12 && $hour < 15) {
                $milestone = '0_days_noon';
            } elseif ($hour >= 15 && $hour < 20) {
                $milestone = '0_days_afternoon';
            }
        } elseif ($daysDiff < 0) {
            // Vencido: 1 mensaje por día
            $milestone = 'expired_'.abs($daysDiff).'_days';
        }

        if ($milestone) {
            $this->sendNotificationIfPending($document, $milestone);
        }
    }

    /**
     * Procesa y valida de forma instantánea una tarjeta de operación.
     */
    public function processOperationCard(OperationCard $document): void
    {
        if (! $document->expiration_date) {
            return;
        }

        $expiryDate = Carbon::parse($document->expiration_date)->startOfDay();
        $today = Carbon::today();
        $now = Carbon::now();

        $daysDiff = (int) $today->diffInDays($expiryDate, false);

        $milestone = null;

        if ($daysDiff === 30) {
            $milestone = '30_days_before';
        } elseif ($daysDiff === 15) {
            $milestone = '15_days_before';
        } elseif ($daysDiff === 5) {
            $milestone = '5_days_before';
        } elseif ($daysDiff === 1) {
            $milestone = '1_day_before';
        } elseif ($daysDiff === 0) {
            // Día de vencimiento: 3 mensajes (mañana, mediodía, tarde)
            $hour = $now->hour;
            if ($hour >= 6 && $hour < 12) {
                $milestone = '0_days_morning';
            } elseif ($hour >= 12 && $hour < 15) {
                $milestone = '0_days_noon';
            } elseif ($hour >= 15 && $hour < 20) {
                $milestone = '0_days_afternoon';
            }
        } elseif ($daysDiff < 0) {
            // Vencido: 1 mensaje por día
            $milestone = 'expired_'.abs($daysDiff).'_days';
        }

        if ($milestone) {
            $this->sendNotificationIfPending($document, $milestone);
        }
    }

    /**
     * Verifica si el hito (milestone) ya fue notificado y lo envía de forma síncrona.
     */
    private function sendNotificationIfPending($document, string $milestone): void
    {
        // 1. Validar en tabla de logs que no se haya enviado este $milestone para este documento
        $alreadySent = EmailNotificationLog::where('document_uuid', '=', $document->uuid, 'and')
            ->where('milestone', '=', $milestone, 'and')
            ->where('status', '=', 'sent', 'and')
            ->exists();

        if ($alreadySent) {
            return;
        }

        // Si el envío falló hoy, no reintentar en la misma jornada (evita saturar el SMTP cada hora)
        $alreadyFailedToday = EmailNotificationLog::where('document_uuid', '=', $document->uuid, 'and')
            ->where('milestone', '=', $milestone, 'and')
            ->where('status', '=', 'failed', 'and')
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if ($alreadyFailedToday) {
            return;
        }

        // Se envía la notificación al tercero (dueño del vehículo)
        $email = $document->vehicle?->thirdParty?->email ?? null;

        if (! $email) {
            return;
        }

        try {
            // Envío de correo directo y síncrono (sin Jobs)
            Mail::to($email)->send(new VehicleDocumentExpiringMail($document, $milestone));
            Log::info("Notificación enviada al propietario ({$email}) | Doc: {$document->uuid} | Milestone: {$milestone}");

            // Guardar el log en base de datos para no volver a enviar
            EmailNotificationLog::create([
                'document_uuid' => $document->uuid,
                'recipient_email' => $email,
                'milestone' => $milestone,
                'status' => 'sent',
            ]);
        } catch (\Exception $e) {
            Log::error("Error enviando correo de vencimiento para el doc {$document->uuid}: ".$e->getMessage());

            // Registrar el fallo para no intentarlo de nuevo en la misma jornada
            EmailNotificationLog::create([
                'document_uuid' => $document->uuid,
                'recipient_email' => $email,
                'milestone' => $milestone,
                'status' => 'failed',
            ]);
        }
    }
}
