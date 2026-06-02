<?php

namespace Veloquent\Core\Domain\Ai\Services;

use Exception;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Messages\AssistantMessage;
use Veloquent\Core\Domain\Settings\AiSettings;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Ai\Agents\VeloquentAgent;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Ai\Agents\StructuredVeloquentAgent;
use Veloquent\Core\Domain\Ai\Exceptions\AgentNotFoundException;
use Veloquent\Core\Domain\Ai\Exceptions\AiNotConfiguredException;
use Veloquent\Core\Domain\Ai\Exceptions\MalformedResponseException;

class AiService
{
    /**
     * Create a new AiService instance with injected settings dependency.
     */
    public function __construct(
        protected AiSettings $aiSettings
    ) {}

    /**
     * Orchestrate and execute the chatbot interaction.
     *
     * @param array $payload
     * @return mixed
     * @throws Exception
     */
    public function chat(array $payload): mixed
    {
        $agentIdentifier = $payload['agent'];

        $collection = Collection::where('name', 'agents')->first();
        if (!$collection) {
            throw new AgentNotFoundException($agentIdentifier);
        }

        $agent = Record::of($collection)
            ->where('name', $agentIdentifier)
            ->orWhere('id', $agentIdentifier)
            ->first();

        if (!$agent) {
            throw new AgentNotFoundException($agentIdentifier);
        }

        $provider = $this->aiSettings->ai_provider;
        $apiKey = $this->aiSettings->ai_api_key;
        $defaultModel = $this->aiSettings->ai_model;

        if (empty($provider) || empty($apiKey)) {
            throw new AiNotConfiguredException();
        }

        $model = $agent->model ?: $defaultModel;
        $temperature = $agent->temperature !== null ? (float) $agent->temperature : 0.7;
        $outputType = $payload['output_type'] ?? ($agent->output_type ?: 'text');
        
        $schema = $payload['schema'] ?? (is_object($agent->schema) || is_array($agent->schema) ? (array) $agent->schema : json_decode((string) $agent->schema, true));

        $systemPrompt = $agent->system_prompt ?? '';
        if (!empty($agent->tone)) {
            $systemPrompt .= "\nTone: Respond in a {$agent->tone} tone.";
        }
        if (!empty($agent->length)) {
            $systemPrompt .= "\nLength: Keep your response {$agent->length}.";
        }

        config([
            'ai.default' => $provider,
            "ai.providers.{$provider}.driver" => $provider,
            "ai.providers.{$provider}.key" => $apiKey,
            "ai.providers.{$provider}.model" => $model,
        ]);

        $attachments = $payload['attachments'] ?? [];

        $chatMessages = [];
        foreach ($payload['messages'] ?? [] as $msg) {
            if ($msg['role'] === 'user') {
                $chatMessages[] = new UserMessage($msg['content']);
            } elseif ($msg['role'] === 'assistant') {
                $chatMessages[] = new AssistantMessage($msg['content']);
            } elseif ($msg['role'] === 'system') {
                $systemPrompt = trim($systemPrompt . "\n" . $msg['content']);
            }
        }

        $useStructuredAgent = ($outputType === 'json' && !empty($schema) && empty($payload['stream']));
        $agentClass = $useStructuredAgent ? StructuredVeloquentAgent::class : VeloquentAgent::class;

        $agentInstance = new $agentClass(
            instructions: $systemPrompt,
            messages: $chatMessages,
            temperature: $temperature,
            schema: $schema
        );

        if (!empty($payload['stream'])) {
            return $agentInstance->stream(
                prompt: $payload['prompt'],
                attachments: $attachments,
                provider: $provider,
                model: $model
            );
        }

        $response = $agentInstance->prompt(
            prompt: $payload['prompt'],
            attachments: $attachments,
            provider: $provider,
            model: $model
        );

        $text = trim((string) $response);
        $parsedJson = null;

        if ($outputType === 'json') {
            $decoded = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
                throw new MalformedResponseException();
            }
            $parsedJson = $decoded;
        }

        return [
            'text' => $text,
            'json' => $parsedJson,
        ];
    }
}
