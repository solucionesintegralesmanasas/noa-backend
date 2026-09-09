<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Utils\Logger;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Servicio encargado de abstraer la comunicación con proveedores externos de IA.
 *
 * Soporta OpenAI y Anthropic como proveedores, seleccionables vía configuración.
 */
class AiProviderService
{
    /**
     * Envía una solicitud de completado al proveedor de IA configurado.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, tokens_used: int, response_time: float}
     */
    public function complete(array $messages, string $systemPrompt): array
    {
        $provider = config('services.ai.provider', 'openai');
        $apiKey = config('services.ai.api_key');
        $model = config('services.ai.model', 'gpt-4o-mini');
        $timeout = config('services.ai.timeout', 25);
        $maxTokens = config('services.ai.max_tokens', 1000);

        if (empty($apiKey)) {
            throw new \RuntimeException('La clave API de IA no está configurada.');
        }

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($timeout)
                ->post(
                    $this->getApiUrl($provider),
                    $this->buildPayload($provider, $model, $systemPrompt, $messages, $maxTokens)
                );

            $duration = round(microtime(true) - $startTime, 3);

            if ($response->failed()) {
                Logger::error('Error en API de IA', null, [
                    'provider' => $provider,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Error al comunicarse con el proveedor de IA.');
            }

            return $this->parseResponse($provider, $response->json(), $duration);
        } catch (Throwable $e) {
            Logger::error('Excepción en AiProviderService', $e);
            throw $e;
        }
    }

    /**
     * Construye el payload según el proveedor.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function buildPayload(string $provider, string $model, string $systemPrompt, array $messages, int $maxTokens): array
    {
        return match ($provider) {
            'anthropic' => [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $systemPrompt,
                'messages' => $messages,
            ],
            default => [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => 0.7,
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $systemPrompt]],
                    $messages
                ),
            ],
        };
    }

    /**
     * Parsea la respuesta según el proveedor.
     *
     * @param  array<string, mixed>  $data
     * @return array{content: string, tokens_used: int, response_time: float}
     */
    private function parseResponse(string $provider, array $data, float $duration): array
    {
        return match ($provider) {
            'anthropic' => [
                'content' => $data['content'][0]['text'] ?? '',
                'tokens_used' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
                'response_time' => $duration,
            ],
            default => [
                'content' => $data['choices'][0]['message']['content'] ?? '',
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                'response_time' => $duration,
            ],
        };
    }

    private function getApiUrl(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'https://api.anthropic.com/v1/messages',
            default => 'https://api.openai.com/v1/chat/completions',
        };
    }
}
