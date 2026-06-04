<?php

namespace Veloquent\Core\Domain\Ai\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Messages\AssistantMessage;
use Veloquent\Core\Domain\Settings\AiSettings;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Ai\Contracts\AiService;
use Veloquent\Core\Domain\Ai\Agents\VeloquentAgent;
use Veloquent\Core\Domain\Hooks\Contracts\HookRunner;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\Ai\Agents\StructuredVeloquentAgent;
use Veloquent\Core\Domain\Ai\Exceptions\AgentNotFoundException;
use Veloquent\Core\Domain\Ai\Exceptions\AiNotConfiguredException;
use Veloquent\Core\Domain\Ai\Exceptions\MalformedResponseException;

class DefaultAiService implements AiService
{
    /**
     * Create a new AiService instance with injected settings and hook runner dependencies.
     */
    public function __construct(
        protected AiSettings $aiSettings,
        protected HookRunner $hookRunner
    ) {}

    /**
     * Orchestrate and execute the chatbot interaction.
     *
     * @param Collection $collection
     * @param array $payload
     * @return mixed
     * @throws Exception
     */
    public function chat(Collection $collection, array $payload): mixed
    {
        $agentIdentifier = $payload['agent'];

        $agent = Record::of($collection)
            ->where('name', $agentIdentifier)
            ->orWhere('id', $agentIdentifier)
            ->first();

        if (!$agent) {
            throw new AgentNotFoundException($agentIdentifier);
        }

        $user = Auth::user();

        $hookPayload = $this->hookRunner->run(new HookPayload(
            event: 'ai.generating',
            collection: $collection,
            record: $agent,
            data: $payload,
            actor: $user,
        ));

        $payloadData = $hookPayload->data;

        if (!empty($payloadData['blocked'])) {
            $blockedMessage = $payloadData['blocked_message'] ?? "I'm sorry, I cannot do that.";

            Log::warning("Request blocked by watcher", compact('agent', 'user', 'collection', 'payload', 'blockedMessage'));

            return [
                'text' => $blockedMessage,
                'json' => null,
            ];
        }

        $provider = $this->aiSettings->ai_provider;
        $apiKey = $this->aiSettings->ai_api_key;
        $defaultModel = $this->aiSettings->ai_model;

        if (empty($provider) || empty($apiKey)) {
            throw new AiNotConfiguredException();
        }

        $model = $agent->model ?: $defaultModel;
        $temperature = $agent->temperature !== null ? (float) $agent->temperature : 0.7;
        $outputType = $payloadData['output_type'] ?? ($agent->output_type ?: 'text');

        if ($outputType === 'json' && !empty($payloadData['stream'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'stream' => ['Streaming is not supported when the output type is JSON.'],
            ]);
        }
        
        $schema = $payloadData['schema'] ?? (is_object($agent->schema) || is_array($agent->schema) ? (array) $agent->schema : json_decode((string) $agent->schema, true));

        $systemPrompt = $agent->system_prompt ?? '';
        if (!empty($agent->tone)) {
            $systemPrompt .= "\nTone: Respond in a {$agent->tone} tone.";
        }
        if (!empty($agent->length)) {
            $systemPrompt .= "\nLength: Keep your response {$agent->length}.";
        }

        $attachments = $payloadData['attachments'] ?? [];

        $chatMessages = [];
        foreach ($payloadData['messages'] ?? [] as $msg) {
            if ($msg['role'] === 'user') {
                $chatMessages[] = new UserMessage($msg['content']);
            } elseif ($msg['role'] === 'assistant') {
                $chatMessages[] = new AssistantMessage($msg['content']);
            } elseif ($msg['role'] === 'system') {
                $systemPrompt = trim($systemPrompt . "\n" . $msg['content']);
            }
        }

        $useStructuredAgent = ($outputType === 'json' && !empty($schema) && empty($payloadData['stream']));
        $agentClass = $useStructuredAgent ? StructuredVeloquentAgent::class : VeloquentAgent::class;

        $agentInstance = new $agentClass(
            instructions: $systemPrompt,
            messages: $chatMessages,
            temperature: $temperature,
            schema: $schema
        );

        if (!empty($payloadData['stream'])) {
            return $agentInstance->stream(
                prompt: $payloadData['prompt'],
                attachments: $attachments,
                provider: $provider,
                model: $model
            );
        }

        $response = $agentInstance->prompt(
            prompt: $payloadData['prompt'],
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

        $responsePayload = [
            'text' => $text,
            'json' => $parsedJson,
        ];

        $generatedPayload = $this->hookRunner->run(new HookPayload(
            event: 'ai.generated',
            collection: $collection,
            record: $agent,
            data: $responsePayload,
            actor: $user,
        ));

        return $generatedPayload->data;
    }
}
