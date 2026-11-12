<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

/**
 * Alerta de Telegram con los documentos y tarjetas de operación vencidos
 * (o próximos a vencer) de una empresa.
 *
 * Es síncrona a propósito: el hosting compartido no tiene workers de cola.
 *
 * @author Darwin Montes
 */
class DocumentosVencidosTelegramNotification extends Notification
{
    private const MAX_ITEMS = 20;

    private const MAX_CARACTERES = 3500;

    /**
     * @param  array<int, array{placa: string, documento: string, fecha: string, days_left: int, estado: string}>  $items
     */
    public function __construct(
        private readonly string $companyName,
        private readonly array $items,
        private readonly string $titulo,
        private readonly string $intro
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->options(['parse_mode' => 'HTML'])
            ->content($this->texto());
    }

    private function texto(): string
    {
        $lineas = [
            '<b>'.e($this->titulo).'</b>',
            e($this->intro),
            '',
        ];

        foreach (array_slice($this->items, 0, self::MAX_ITEMS) as $item) {
            $lineas[] = sprintf(
                '• <b>%s</b> · %s — vence %s (%s)',
                e($item['placa']),
                e($item['documento']),
                e($item['fecha']),
                e($item['estado'])
            );
        }

        if (count($this->items) > self::MAX_ITEMS) {
            $lineas[] = '… y '.(count($this->items) - self::MAX_ITEMS).' documentos más.';
        }

        $texto = implode("\n", $lineas);

        return mb_strlen($texto) > self::MAX_CARACTERES
            ? mb_substr($texto, 0, self::MAX_CARACTERES - 1).'…'
            : $texto;
    }
}
