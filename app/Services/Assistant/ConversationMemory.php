<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\ChatSession;

/**
 * Memoria conversacional para el asistente.
 *
 * Detecta preguntas de seguimiento y mantiene el contexto
 * de la última interacción (entidad, término, tipo de respuesta).
 */
class ConversationMemory
{
    private const KEY_ENTITY = 'last_entity';

    private const KEY_TERM = 'last_search_term';

    private const KEY_RESPONSE_TYPE = 'last_response_type';

    private const KEY_USER_MESSAGE = 'last_user_message';

    private const FOLLOW_UP_STARTS = [
        'y', 'ese', 'esa', 'esos', 'esas', 'su', 'sus',
        'el', 'la', 'los', 'las', 'cuál', 'cual',
    ];

    private const FOLLOW_UP_EXACT = [
        'cuál', 'cual', 'siguiente', 'primero', 'primera',
        'último', 'ultimo', 'ultima', 'última', 'otro', 'otra',
        'mismo', 'misma', 'mejor', 'peor',
    ];

    public function getLastEntity(ChatSession $session): ?string
    {
        return $session->meta[self::KEY_ENTITY] ?? null;
    }

    public function getLastTerm(ChatSession $session): ?string
    {
        return $session->meta[self::KEY_TERM] ?? null;
    }

    public function getLastResponseType(ChatSession $session): ?string
    {
        return $session->meta[self::KEY_RESPONSE_TYPE] ?? null;
    }

    /**
     * Determina si el mensaje del usuario es un seguimiento
     * de la conversación anterior.
     */
    public function isFollowUp(string $message, ChatSession $session): bool
    {
        $lastEntity = $this->getLastEntity($session);

        if ($lastEntity === null) {
            return false;
        }

        $trimmed = trim($message);
        $lower = mb_strtolower($trimmed);

        if ($lower === '') {
            return false;
        }

        // Palabras exactas de seguimiento
        if (in_array($lower, self::FOLLOW_UP_EXACT, true)) {
            return true;
        }

        $words = explode(' ', $lower);

        // Mensaje muy corto es probable seguimiento
        if (count($words) <= 3) {
            return true;
        }

        // Empieza con palabra de seguimiento
        $firstWord = $words[0];
        if (in_array($firstWord, self::FOLLOW_UP_STARTS, true)) {
            return true;
        }

        // Contiene referencias a lo anterior
        if (str_contains($lower, 'del ') || str_contains($lower, 'de la ')) {
            return true;
        }

        return false;
    }

    /**
     * Guarda el contexto de la última interacción en la sesión.
     */
    public function remember(
        ChatSession $session,
        string $entityType,
        ?string $searchTerm,
        string $responseType,
        string $userMessage
    ): void {
        $meta = $session->meta ?? [];

        $meta[self::KEY_ENTITY] = $entityType;
        $meta[self::KEY_TERM] = $searchTerm;
        $meta[self::KEY_RESPONSE_TYPE] = $responseType;
        $meta[self::KEY_USER_MESSAGE] = $userMessage;

        $session->meta = $meta;
        $session->save();
    }

    /**
     * Limpia el contexto de la sesión.
     */
    public function forget(ChatSession $session): void
    {
        $session->meta = [];
        $session->save();
    }
}
