<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Company;
use App\Models\User;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Throwable;

/**
 * Orquestador principal del flujo de conversación del asistente.
 *
 * Coordina el guardado de mensajes, la construcción de contexto,
 * la búsqueda en ERP y la comunicación con el proveedor de IA.
 */
class AssistantService extends BaseService
{
    protected function getModelInstance(): Model
    {
        return new ChatSession;
    }

    public function __construct(
        protected AiProviderService $aiProvider,
        protected ContextService $contextService,
        protected SearchService $searchService,
        protected LocalResponseBuilder $localResponseBuilder,
        protected ContextualQuestionService $contextualQuestionService,
        protected ConversationMemory $conversationMemory
    ) {
        parent::__construct();
    }

    /**
     * Procesa un mensaje nuevo dentro de una transacción.
     */
    public function processMessage(ChatSession $session, User $user, string $userMessage): ChatMessage
    {
        return $this->transaction(function () use ($session, $user, $userMessage) {
            $companyUuid = $session->company_uuid ?? $this->resolveCurrentCompanyUuid();

            ChatMessage::create([
                'company_uuid' => $companyUuid,
                'session_id' => $session->id,
                'role' => 'USUARIO',
                'content' => $userMessage,
            ]);

            // 1. Intentar pregunta contextual primero
            $contextualQuestion = $this->contextualQuestionService->findMatch($userMessage);
            if ($contextualQuestion !== null) {
                $contextualResponse = $this->contextualQuestionService->execute($contextualQuestion);

                $this->conversationMemory->remember(
                    $session,
                    $contextualQuestion->entity_type ?? 'general',
                    null,
                    $contextualQuestion->response_type,
                    $userMessage
                );

                return ChatMessage::create([
                    'company_uuid' => $companyUuid,
                    'session_id' => $session->id,
                    'role' => 'ASISTENTE',
                    'content' => $contextualResponse,
                    'metadata' => [
                        'contextual_question_uuid' => $contextualQuestion->uuid,
                        'contextual_title' => $contextualQuestion->title,
                    ],
                ]);
            }

            // 2. Detectar seguimiento de la conversación anterior
            $erpData = [];
            $usedEntity = null;

            if ($this->conversationMemory->isFollowUp($userMessage, $session)) {
                $lastEntity = $this->conversationMemory->getLastEntity($session);
                $lastTerm = $this->conversationMemory->getLastTerm($session);

                if ($lastEntity !== null) {
                    $erpData = $this->searchService->searchByEntity($lastEntity, $lastTerm ?? $userMessage);

                    if (! empty($erpData)) {
                        $usedEntity = $lastEntity;
                    }
                }
            }

            // 3. Si no fue seguimiento o no dio resultados, búsqueda normal
            if (empty($erpData)) {
                $erpData = $this->searchService->detectAndSearch($userMessage);
                $usedEntity = ! empty($erpData) ? array_key_first($erpData) : null;
            }

            $contextData = $this->contextService->buildContext($user, $session->uuid);
            $systemPrompt = $this->buildSystemPrompt($user, $companyUuid, $erpData);

            $apiKey = config('services.ai.api_key');

            if (! empty($apiKey)) {
                try {
                    $aiResponse = $this->aiProvider->complete($contextData['history'], $systemPrompt);

                    $this->conversationMemory->remember(
                        $session,
                        $usedEntity ?? 'general',
                        null,
                        'ai',
                        $userMessage
                    );

                    return ChatMessage::create([
                        'company_uuid' => $companyUuid,
                        'session_id' => $session->id,
                        'role' => 'ASISTENTE',
                        'content' => $aiResponse['content'],
                        'tokens_used' => $aiResponse['tokens_used'],
                        'response_time' => $aiResponse['response_time'],
                        'metadata' => ! empty($erpData) ? ['erp_results' => $erpData] : null,
                    ]);
                } catch (Throwable $e) {
                    Logger::error('Error al procesar mensaje con IA, usando respuesta local', $e);
                }
            }

            // 4. Respuesta local sin IA
            $firstName = explode(' ', trim($user->name))[0];

            // Si no hay datos, incluir sugerencias contextuales
            if (empty($erpData)) {
                $suggestions = $this->contextualQuestionService->getSuggestionsForMessage($userMessage);
                $localContent = "Hola {$firstName}.\n\n{$suggestions}";
                $responseType = 'suggestions';
            } else {
                $localContent = $this->localResponseBuilder->build($userMessage, $erpData, $firstName);
                $responseType = 'search';
            }

            $searchTerm = $this->searchService->extractSearchTermPublic($userMessage);

            $this->conversationMemory->remember(
                $session,
                $usedEntity ?? 'general',
                $searchTerm,
                $responseType,
                $userMessage
            );

            return ChatMessage::create([
                'company_uuid' => $companyUuid,
                'session_id' => $session->id,
                'role' => 'ASISTENTE',
                'content' => $localContent,
                'metadata' => ! empty($erpData) ? ['erp_results' => $erpData] : null,
            ]);
        });
    }

    /**
     * Construye el prompt del sistema para la IA.
     *
     * @param  array<string, mixed>  $erpData
     */
    private function buildSystemPrompt(User $user, ?string $companyUuid, array $erpData): string
    {
        $companyName = 'NoaTrasporteApi';
        if ($companyUuid) {
            $company = Company::withoutGlobalScopes()
                ->where('uuid', $companyUuid)
                ->first();
            $companyName = $company?->business_name ?? $company?->name ?? 'NoaTrasporteApi';
        }

        $prompt = 'Eres NOA Assistant, asistente inteligente del ERP NoaTrasporteApi. Responde en español.';
        $prompt .= "\nUsuario: {$user->name} ({$user->email})";
        $prompt .= "\nEmpresa: {$companyName}";

        if (! empty($erpData)) {
            $prompt .= "\n\nDATOS DEL SISTEMA:\n".json_encode($erpData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return $prompt;
    }

    /**
     * Obtiene una sesión con sus mensajes cargados.
     */
    public function getSessionWithMessages(string $uuid): ?ChatSession
    {
        $session = $this->findByUuid($uuid);

        if ($session) {
            $session->load('messages');
        }

        return $session;
    }

    /**
     * Registra feedback sobre un mensaje del asistente.
     *
     * @throws ModelNotFoundException
     */
    public function rateMessage(string $messageUuid, int $feedback, User $user): ChatMessage
    {
        return $this->transaction(function () use ($messageUuid, $feedback, $user) {
            $message = ChatMessage::where('uuid', $messageUuid)->firstOrFail();

            $session = ChatSession::withoutGlobalScopes()->find($message->session_id);

            if (! $session || $session->user_uuid !== $user->uuid) {
                throw new RuntimeException('No autorizado para calificar este mensaje.');
            }

            $message->update(['feedback' => $feedback]);

            return $message->fresh();
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
