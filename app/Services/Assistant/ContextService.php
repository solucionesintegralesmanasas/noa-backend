<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\ChatContextCache;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio para construir y mantener el contexto de las conversaciones.
 *
 * Administra el historial reciente de mensajes y la caché persistente
 * de contexto por usuario/empresa.
 */
class ContextService extends BaseService
{
    protected function getModelInstance(): Model
    {
        return new ChatContextCache;
    }

    /**
     * Construye el contexto usando UUIDs de sesión.
     *
     * @return array{history: array<int, array{role: string, content: string}>, persistent_data: array<string, mixed>}
     */
    public function buildContext(User $user, string $sessionUuid): array
    {
        $recentMessages = ChatMessage::whereHas('session', function ($q) use ($sessionUuid) {
            $q->where('uuid', $sessionUuid);
        })
            ->orderBy('created_at', 'desc')
            ->limit(config('chat.context_window', 10))
            ->get()
            ->reverse()
            ->map(fn ($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->toArray();

        $cache = $this->query()
            ->where('user_uuid', $user->uuid)
            ->first();

        return [
            'history' => $recentMessages,
            'persistent_data' => $cache?->context ?? [],
        ];
    }

    /**
     * Actualiza la caché de contexto persistente.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateContextCache(User $user, array $data): void
    {
        $companyUuid = $this->resolveCurrentCompanyUuid();

        $this->transaction(function () use ($user, $companyUuid, $data) {
            ChatContextCache::updateOrCreate(
                [
                    'user_uuid' => $user->uuid,
                    'company_uuid' => $companyUuid,
                ],
                ['context' => $data]
            );
        });
    }

    private function resolveCurrentCompanyUuid(): ?string
    {
        return request()->attributes->get('current_company_uuid')
            ?? request()->header('X-Company-UUID')
            ?? request()->input('company_uuid')
            ?? session('current_company_uuid');
    }
}
