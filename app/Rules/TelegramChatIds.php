<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida la lista de chat_id de Telegram separada por comas (máximo 5).
 *
 * Acepta el id numérico del chat o un @usuario público.
 *
 * @author Darwin Montes
 */
class TelegramChatIds implements ValidationRule
{
    private const MAXIMO = 5;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $destinos = array_values(array_filter(array_map('trim', explode(',', (string) $value))));

        if (count($destinos) > self::MAXIMO) {
            $fail('Puede configurar como máximo '.self::MAXIMO.' chat_id de Telegram.');

            return;
        }

        foreach ($destinos as $destino) {
            if (! preg_match('/^(-?\d{5,20}|@[A-Za-z0-9_]{5,32})$/', $destino)) {
                $fail("El chat_id «{$destino}» no es válido. Use el id numérico o @usuario.");

                return;
            }
        }
    }
}
