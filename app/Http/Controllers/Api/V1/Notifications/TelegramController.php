<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Models\SystemConfiguration;
use App\Notifications\DocumentosVencidosTelegramNotification;
use App\Rules\TelegramChatIds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use OpenApi\Attributes as OA;

/**
 * Envío de prueba de las alertas de Telegram.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 */
class TelegramController extends Controller
{
    #[OA\Post(
        path: '/api/v1/telegram/test',
        summary: 'Enviar un mensaje de prueba a los chat_id de Telegram de la empresa',
        operationId: 'testTelegram',
        tags: ['Notificaciones'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Resultado del envío por chat.'),
            new OA\Response(response: 422, description: 'Sin chat_id configurado o sin token del bot.'),
        ]
    )]
    public function test(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->attributes->get('current_company_uuid')
                ?? $request->user()?->companies()->first()?->uuid;

            if (! $companyUuid) {
                return $this->errorResponse('No se identificó la empresa activa.', 422);
            }

            if (trim((string) config('services.telegram-bot-api.token')) === '') {
                return $this->errorResponse('Falta TELEGRAM_BOT_TOKEN en el servidor; el envío no puede realizarse.', 422);
            }

            // La pantalla puede probar los chat_id recién escritos, sin guardarlos.
            $request->validate(['chat_ids' => ['nullable', 'string', 'max:500', new TelegramChatIds]]);
            $chatIds = SystemConfiguration::separarChatIds($request->input('chat_ids'));

            $config = SystemConfiguration::query()->where('company_uuid', $companyUuid)->first();

            if ($chatIds !== []) {
                $destinos = $chatIds;
            } elseif ($config) {
                $destinos = $config->destinosTelegram();
            } else {
                // Sin configuración de la empresa se usa el chat_id del .env como respaldo.
                $destinos = SystemConfiguration::separarChatIds(config('services.telegram-bot-api.chat_id'));
            }

            if ($destinos === []) {
                return $this->errorResponse('Configure al menos un chat_id de Telegram en Configuración del sistema.', 422);
            }

            $empresa = $config?->company?->business_name ?? $config?->company?->trade_name ?? 'la empresa';
            $item = [
                'placa' => 'ABC123',
                'documento' => 'SOAT (ejemplo)',
                'fecha' => Carbon::today()->format('d/m/Y'),
                'days_left' => 0,
                'estado' => 'PRUEBA',
            ];

            $enviados = [];
            $fallidos = [];

            foreach ($destinos as $chatId) {
                try {
                    Notification::route('telegram', $chatId)->notify(new DocumentosVencidosTelegramNotification(
                        $empresa,
                        [$item],
                        'Prueba de notificaciones por Telegram',
                        'Si recibe este mensaje, las alertas de documentos vencidos llegarán a este chat.'
                    ));
                    $enviados[] = $chatId;
                } catch (\Throwable $e) {
                    Log::warning("Error en la prueba de Telegram para {$chatId}: ".$e->getMessage());
                    $fallidos[] = $chatId;
                }
            }

            $mensaje = $enviados === []
                ? 'No se pudo enviar la prueba a ningún chat de Telegram.'
                : 'Prueba de Telegram enviada a '.count($enviados).' chat(s).';

            return $this->successResponse(['enviados' => $enviados, 'fallidos' => $fallidos], $mensaje);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
