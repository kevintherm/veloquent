# AI & Agents

Veloquent features first-class support for integrating Artificial Intelligence (AI) and Large Language Models (LLMs) directly into your backend. This enables developers to create AI-powered chatbot agents, store their system instructions and settings dynamically, secure access with expression-based API rules, and hook into generation lifecycles.

---

## AI Provider Configuration

Before utilizing AI features, you must configure your LLM provider credentials at the tenant level. Veloquent leverages the `laravel/ai` SDK to interact with leading AI providers (e.g. OpenAI, Anthropic, Gemini, DeepSeek).

### Configuration Options
*   **Provider**: The AI driver name (e.g. `openai`, `gemini`, `anthropic`).
*   **Model**: The default LLM model name (e.g. `gpt-4o`, `claude-4.8-opus`).
*   **API Key**: The API secret key, which is encrypted at rest using `EncryptedSettingsCast`.

These settings can be managed directly in the **Settings** section of the Veloquent dashboard or updated programmatically using the settings endpoints.

---

## Agent Collections

AI configurations and system prompts are stored in **Agent Collections**. When creating a new collection, select the type **agents**. Veloquent will automatically inject the required system fields into your database schema.

### System Fields
Every `agents` collection includes the following auto-managed fields:

| Field Name | Type | Nullable | Description |
| :--- | :--- | :--- | :--- |
| `id` | `text` | ✗ No | Unique ULID representing the agent record. |
| `name` | `text` | ✗ No | A unique name or handle for the agent (e.g., `support-assistant`). |
| `system_prompt` | `longtext` | ✓ Yes | The base system prompt defining the agent's persona and instructions. |
| `model` | `text` | ✓ Yes | Override the default model specifically for this agent (e.g., `gpt-4o-mini`). |
| `temperature` | `number` | ✓ Yes | Set the generation temperature. Value must be between `0` and `1`. |
| `tone` | `text` | ✓ Yes | Adjectives specifying response style (e.g., `friendly`, `concise`, `formal`). |
| `length` | `text` | ✓ Yes | Constraint on output length (e.g., `under 2 sentences`, `detailed`). |
| `output_type` | `select` | ✓ Yes | Expected response format. Options: `text` or `json`. |
| `schema` | `json` | ✓ Yes | Target JSON Schema constraint when `output_type` is set to `json`. |
| `created_at` | `datetime` | ✗ No | Timestamp when the agent record was created. |
| `updated_at` | `datetime` | ✗ No | Timestamp when the agent record was last updated. |

> [!NOTE]
> When creating an agents collection, you can submit the schema creation payload without manually defining these fields; Veloquent automatically merges them with any custom columns you wish to add.

---

## Chat Security and API Rules

To prevent unauthorized usage of your LLM API tokens, interaction with agents is protected via custom access control policies:

### The Chat Rule
Collections of type `agents` feature a dedicated API rule called **chat**. 
Like standard Veloquent security policies, the chat rule is expression-based and supports evaluating user context dynamically. For more information about Veloquent API Rule see [Api Rule Section](/docs/security/api-rules.md).

#### Bypassing Rules
*   **Superusers** automatically bypass all API rules, allowing administrators to converse with any agent without restriction.

---

## Chat API Endpoints

Once your agent is configured and the collection is secured, clients can interact with the agent via the REST API.

### Initiate Chat
Converse with a specific agent configured in an agents collection:

*   **Endpoint**: `POST /api/collections/{collection}/ai/chat`
*   **Payload Schema**:
    ```json
    {
      "agent": "support-assistant",
      "prompt": "How do I reset my password?",
      "messages": [
        { "role": "user", "content": "Hello!" },
        { "role": "assistant", "content": "Hi! How can I help you today?" }
      ],
      "stream": false
    }
    ```

#### Request Arguments
*   `agent` (string, Required): The ULID `id` or unique `name` of the agent record.
*   `prompt` (string, Required): The new prompt to send to the chatbot.
*   `messages` (array, Optional): Conversational history as a list of message objects (`role` and `content`).
*   `stream` (boolean, Optional): Set to `true` to receive a chunked response stream (Server-Sent Events).

For more information about the stream see [laravel/ai docs](https://laravel.com/docs/ai-sdk#streaming).

---

## AI Lifecycles & Hooks

You can intercept and extend chatbot behavior by registering custom logic on the following lifecycle events:

### `ai.generating`
Fires **before** the prompt is sent to the LLM. 
*   **Use Cases**: Inject custom system instructions dynamically, redact sensitive user inputs, or run advanced input validation.
*   **Payload**: Modifying `$payload->data` in a `before` hook alters the messages history, tone, attachments, or system instructions before the LLM prompt is executed.

### `ai.generated`
Fires **after** the LLM generates a response successfully.
*   **Use Cases**: Log tokens consumed, audit user interactions, persist conversation history to the database, or sanitize/filter output text.
*   **Payload**: The `$payload->data` contains the generated output format:
    ```json
    {
      "text": "The raw text response from the LLM...",
      "json": { ... } // Decoded JSON object if output_type is json
    }
    ```

#### Registration Example
```php
use Veloquent\Core\Domain\Hooks\Facades\Hooks;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;

// Inject custom system rules before LLM execution
Hooks::before('ai.generating', function (HookPayload $payload, Closure $next) {
    if ($payload->actor) {
        $payload->data['prompt'] .= " (Remember, the user is authenticated as ID: {$payload->actor->id})";
    }
    return $next($payload);
});
```
