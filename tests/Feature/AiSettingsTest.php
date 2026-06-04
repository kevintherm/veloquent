<?php

use Veloquent\Core\Domain\Auth\Models\Superuser;
use Veloquent\Core\Domain\Settings\AiSettings;
use Veloquent\Core\Support\Http\Middleware\TokenAuthMiddleware;
use Veloquent\Core\Support\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Ai;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

beforeEach(function () {
    $this->tenant = Tenant::current();
    $this->user = Superuser::factory()->create();
    Mail::fake();
    withoutMiddleware(TokenAuthMiddleware::class);
    actingAs($this->user, 'api');

    $this->collection = \Veloquent\Core\Domain\Collections\Models\Collection::withoutEvents(function () {
        $collection = \Veloquent\Core\Domain\Collections\Models\Collection::create([
            'type' => 'agents',
            'is_system' => false,
            'name' => 'agents',
            'table_name' => '_velo_agents',
            'description' => 'User collection for chatbot agents',
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'nullable' => false, 'unique' => true],
                ['name' => 'model', 'type' => 'text', 'nullable' => true, 'unique' => false],
                ['name' => 'system_prompt', 'type' => 'longtext', 'nullable' => true, 'unique' => false],
                ['name' => 'tone', 'type' => 'text', 'nullable' => true, 'unique' => false],
                ['name' => 'length', 'type' => 'text', 'nullable' => true, 'unique' => false],
                ['name' => 'temperature', 'type' => 'number', 'nullable' => true, 'unique' => false, 'allow_decimals' => true],
                ['name' => 'output_type', 'type' => 'select', 'nullable' => true, 'unique' => false, 'default' => 'text', 'options' => ['text', 'json']],
                ['name' => 'schema', 'type' => 'json', 'nullable' => true, 'unique' => false],
                ['name' => 'type', 'type' => 'select', 'nullable' => true, 'unique' => false, 'default' => 'regular', 'options' => ['regular', 'watcher']],
                ['name' => 'watcher_message', 'type' => 'text', 'nullable' => true, 'unique' => false],
                ['name' => 'watchers', 'type' => 'relation_many', 'target_collection_id' => '@self', 'nullable' => true, 'unique' => false],
            ],
            'api_rules' => [
                'list' => '@request.auth.id != null',
                'view' => '@request.auth.id != null',
                'create' => '@request.auth.is_superuser = true',
                'update' => '@request.auth.is_superuser = true',
                'delete' => '@request.auth.is_superuser = true',
                'manage' => null,
                'chat' => '@request.auth.id != null',
            ],
        ]);

        $fields = $collection->fields;
        foreach ($fields as &$f) {
            if ($f['name'] === 'watchers') {
                $f['target_collection_id'] = $collection->id;
            }
        }
        $collection->fields = $fields;
        $collection->save();

        return $collection;
    });

    $this->tableName = $this->collection->getPhysicalTableName();

    Schema::dropIfExists($this->tableName);
    Schema::create($this->tableName, function ($table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('model')->nullable();
        $table->text('system_prompt')->nullable();
        $table->string('tone')->nullable();
        $table->string('length')->nullable();
        $table->decimal('temperature', 3, 2)->nullable();
        $table->string('output_type')->nullable();
        $table->json('schema')->nullable();
        $table->string('type')->nullable();
        $table->text('watcher_message')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('_velo_agents_agents_watchers_pivot');
    Schema::create('_velo_agents_agents_watchers_pivot', function ($table) {
        $table->ulid('id')->primary();
        $table->char('source_id', 26);
        $table->char('target_id', 26);
        $table->timestamps();
    });
});

it('can get AI settings with masked API key', function () {
    actingAs($this->user, 'api');

    // Pre-set some values
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'super-secret-key';
    $settings->save();

    $response = getJson('/api/settings');

    $response->assertStatus(200);
    $response->assertJsonPath('data.ai.ai_provider', 'openai');
    $response->assertJsonPath('data.ai.ai_model', 'gpt-4o-mini');
    $response->assertJsonPath('data.ai.ai_api_key', '••••••••');
});

it('can update AI settings and encrypt API key', function () {
    actingAs($this->user, 'api');

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::type(\Laravel\Ai\Prompts\AgentPrompt::class))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'OK',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'gemini')
        ->andReturn($mockProvider);

    $response = patchJson('/api/settings', [
        'general' => [
            'app_name' => 'Custom App Name',
            'app_url' => 'http://localhost',
            'locale' => 'en',
            'contact_email' => 'admin@test.com',
            'lock_schema_change' => false,
        ],
        'storage' => [
            'storage_driver' => 'local',
            's3_key' => '',
            's3_secret' => '',
            's3_region' => '',
            's3_bucket' => '',
            's3_endpoint' => '',
        ],
        'email' => [
            'mail_driver' => 'smtp',
            'mail_host' => '127.0.0.1',
            'mail_port' => 1025,
            'mail_encryption' => 'tls',
            'mail_username' => '',
            'mail_password' => '',
            'mail_from_address' => 'hello@test.com',
            'mail_from_name' => 'Support',
        ],
        'ai' => [
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-1.5-pro',
            'ai_api_key' => 'new-secret-gemini-key',
        ],
    ]);

    $response->assertStatus(200);
    
    $settings = app(AiSettings::class);
    expect($settings->ai_provider)->toBe('gemini');
    expect($settings->ai_model)->toBe('gemini-1.5-pro');
    expect($settings->ai_api_key)->toBe('new-secret-gemini-key');
});

it('retains existing API key when masked value is submitted', function () {
    actingAs($this->user, 'api');

    // Pre-save key
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o';
    $settings->ai_api_key = 'initial-secret-key';
    $settings->save();

    $response = patchJson('/api/settings', [
        'general' => [
            'app_name' => 'Custom App Name',
            'app_url' => 'http://localhost',
            'locale' => 'en',
            'contact_email' => 'admin@test.com',
            'lock_schema_change' => false,
        ],
        'storage' => [
            'storage_driver' => 'local',
            's3_key' => '',
            's3_secret' => '',
            's3_region' => '',
            's3_bucket' => '',
            's3_endpoint' => '',
        ],
        'email' => [
            'mail_driver' => 'smtp',
            'mail_host' => '127.0.0.1',
            'mail_port' => 1025,
            'mail_encryption' => 'tls',
            'mail_username' => '',
            'mail_password' => '',
            'mail_from_address' => 'hello@test.com',
            'mail_from_name' => 'Support',
        ],
        'ai' => [
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o',
            'ai_api_key' => '••••••••', // Masked key sent by frontend
        ],
    ]);

    $response->assertStatus(200);
    
    $settings = app(AiSettings::class);
    expect($settings->ai_api_key)->toBe('initial-secret-key');
});

it('can verify connection when updating AI settings', function () {
    actingAs($this->user, 'api');

    // Mock Provider
    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::type(\Laravel\Ai\Prompts\AgentPrompt::class))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'OK',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    // Mock AI Facade
    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    $response = patchJson('/api/settings', [
        'general' => [
            'app_name' => 'Custom App Name',
            'app_url' => 'http://localhost',
            'locale' => 'en',
            'contact_email' => 'admin@test.com',
            'lock_schema_change' => false,
        ],
        'storage' => [
            'storage_driver' => 'local',
            's3_key' => '',
            's3_secret' => '',
            's3_region' => '',
            's3_bucket' => '',
            's3_endpoint' => '',
        ],
        'email' => [
            'mail_driver' => 'smtp',
            'mail_host' => '127.0.0.1',
            'mail_port' => 1025,
            'mail_encryption' => 'tls',
            'mail_username' => '',
            'mail_password' => '',
            'mail_from_address' => 'hello@test.com',
            'mail_from_name' => 'Support',
        ],
        'ai' => [
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'ai_api_key' => 'test-api-key',
        ],
    ]);

    $response->assertStatus(200);
});

it('fails settings update when AI connection test fails', function () {
    actingAs($this->user, 'api');

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'anthropic')
        ->andThrow(new Exception('Invalid API Key provided'));

    $response = patchJson('/api/settings', [
        'general' => [
            'app_name' => 'Custom App Name',
            'app_url' => 'http://localhost',
            'locale' => 'en',
            'contact_email' => 'admin@test.com',
            'lock_schema_change' => false,
        ],
        'storage' => [
            'storage_driver' => 'local',
            's3_key' => '',
            's3_secret' => '',
            's3_region' => '',
            's3_bucket' => '',
            's3_endpoint' => '',
        ],
        'email' => [
            'mail_driver' => 'smtp',
            'mail_host' => '127.0.0.1',
            'mail_port' => 1025,
            'mail_encryption' => 'tls',
            'mail_username' => '',
            'mail_password' => '',
            'mail_from_address' => 'hello@test.com',
            'mail_from_name' => 'Support',
        ],
        'ai' => [
            'ai_provider' => 'anthropic',
            'ai_model' => 'claude-3-5-sonnet-20241022',
            'ai_api_key' => 'invalid-key',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('ai.ai_provider');
    $response->assertJsonFragment([
        'ai.ai_provider' => ['AI connection test failed: Invalid API Key provided']
    ]);
});

it('can chat with configured agent', function () {
    // 1. Seed tenant settings
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cde',
        'name' => 'support-bot',
        'model' => 'gpt-4o',
        'system_prompt' => 'You are a helpful customer service representative.',
        'tone' => 'professional',
        'length' => 'short',
        'temperature' => 0.5,
        'output_type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 3. Mock AI Facade
    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    
    // Verify system instructions: includes tone, length and prompt combined
    $expectedSystem = "You are a helpful customer service representative.\nTone: Respond in a professional tone.\nLength: Keep your response short.";

    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::on(function ($prompt) use ($expectedSystem) {
            return $prompt->prompt === 'Can you help me?'
                && $prompt->agent->instructions() === $expectedSystem
                && $prompt->model === 'gpt-4o';
        }))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'Sure, how can I assist you today?',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'support-bot',
        'prompt' => 'Can you help me?',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Sure, how can I assist you today?');
});

it('can chat with agent utilizing past conversation history', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cdf',
        'name' => 'sales-bot',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'You are a warm sales representative.',
        'tone' => 'friendly',
        'length' => 'medium',
        'temperature' => 0.8,
        'output_type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $expectedSystem = "You are a warm sales representative.\nTone: Respond in a friendly tone.\nLength: Keep your response medium.";

    // Mock AI Facade
    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::on(function ($prompt) use ($expectedSystem) {
            $msgArray = iterator_to_array($prompt->agent->messages());
            return $prompt->prompt === 'Is there any discount?'
                && $prompt->agent->instructions() === $expectedSystem
                && $prompt->model === 'gpt-4o-mini'
                && count($msgArray) === 2
                && $msgArray[0] instanceof \Laravel\Ai\Messages\UserMessage
                && $msgArray[0]->content === 'What is the price of product A?'
                && $msgArray[1] instanceof \Laravel\Ai\Messages\AssistantMessage
                && $msgArray[1]->content === 'It is $99.';
        }))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'Yes! You can get a 10% discount today.',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'sales-bot',
        'prompt' => 'Is there any discount?',
        'messages' => [
            ['role' => 'user', 'content' => 'What is the price of product A?'],
            ['role' => 'assistant', 'content' => 'It is $99.'],
        ]
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Yes! You can get a 10% discount today.');
});

it('can chat with agent and enforce structured JSON output', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cde',
        'name' => 'json-bot',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'You output movie list.',
        'tone' => 'neutral',
        'length' => 'short',
        'temperature' => 0.5,
        'output_type' => 'json',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Mock AI Facade
    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::on(function ($prompt) {
            return $prompt->prompt === 'Give me top movies'
                && $prompt->agent->schema !== null
                && array_key_exists('movies_count', $prompt->agent->schema);
        }))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            '{"movies_count": 2, "movies": ["The Matrix", "Inception"]}',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\StructuredVeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'json-bot',
        'prompt' => 'Give me top movies',
        'schema' => [
            'movies_count' => 'integer',
            'movies' => 'array',
        ]
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', '{"movies_count": 2, "movies": ["The Matrix", "Inception"]}');
    $response->assertJsonPath('data.json.movies_count', 2);
    $response->assertJsonPath('data.json.movies.0', 'The Matrix');
});

it('can stream responses from chatbot agent', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cdf',
        'name' => 'streaming-bot',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'You stream.',
        'tone' => 'friendly',
        'length' => 'short',
        'temperature' => 0.7,
        'output_type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Mock AI Facade for stream
    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);

    $streamableResponse = new \Laravel\Ai\Responses\StreamableAgentResponse(
        'invocation-id',
        function () {
            yield new \Laravel\Ai\Streaming\Events\TextDelta('Hello');
            yield new \Laravel\Ai\Streaming\Events\TextDelta(' World');
        }
    );

    $mockProvider->shouldReceive('stream')
        ->once()
        ->andReturn($streamableResponse);

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'streaming-bot',
        'prompt' => 'Hello',
        'stream' => true,
    ]);

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
});

it('can chat with agent resolved by its UUID ID', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cde',
        'name' => 'id-resolved-bot',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'You identify.',
        'tone' => 'friendly',
        'length' => 'short',
        'temperature' => 0.7,
        'output_type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'Hello by ID',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => '01h7c989r148s89m257a3b4cde',
        'prompt' => 'Hello',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Hello by ID');
});

it('returns 500 malformed response when JSON decode fails', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cdb',
        'name' => 'malformed-bot',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'You return JSON.',
        'tone' => 'friendly',
        'length' => 'short',
        'temperature' => 0.7,
        'output_type' => 'json',
        'schema' => json_encode([
            'status' => 'string'
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'not a valid json string',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\StructuredVeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'malformed-bot',
        'prompt' => 'Give me JSON',
    ]);

    $response->assertStatus(500);
    $response->assertJsonPath('message', 'AI prompt failed: Malformed response.');
});

it('can chat with agent utilizing complex nested schemas', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cda',
        'name' => 'nested-bot',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'You return nested structures.',
        'tone' => 'friendly',
        'length' => 'short',
        'temperature' => 0.7,
        'output_type' => 'json',
        'schema' => json_encode([
            'user' => [
                'name' => 'string',
                'age' => 'integer',
            ],
            'tags' => ['string']
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            json_encode([
                'user' => [
                    'name' => 'Alice',
                    'age' => 30,
                ],
                'tags' => ['admin', 'editor']
            ]),
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\StructuredVeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'nested-bot',
        'prompt' => 'Give me nested user details',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.json.user.name', 'Alice');
    $response->assertJsonPath('data.json.user.age', 30);
    $response->assertJsonPath('data.json.tags.0', 'admin');
});

it('allows authenticated users to chat based on the chat API rule', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    \Veloquent\Core\Domain\Collections\Models\Collection::withoutEvents(function () {
        $this->collection->api_rules = [
            'list' => '@request.auth.id != null',
            'view' => '@request.auth.id != null',
            'create' => '@request.auth.is_superuser = true',
            'update' => '@request.auth.is_superuser = true',
            'delete' => '@request.auth.is_superuser = true',
            'chat' => '@request.auth.id != null',
        ];
        $this->collection->save();
    });

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cde',
        'name' => 'rule-bot',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 42);

    actingAs($regularUser, 'api');

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'Hello Regular User',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->andReturn($mockProvider);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'rule-bot',
        'prompt' => 'Hello',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Hello Regular User');
});

it('denies guests from chatting if rule blocks them', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    \Veloquent\Core\Domain\Collections\Models\Collection::withoutEvents(function () {
        $this->collection->api_rules = [
            'list' => '@request.auth.id != null',
            'view' => '@request.auth.id != null',
            'create' => '@request.auth.is_superuser = true',
            'update' => '@request.auth.is_superuser = true',
            'delete' => '@request.auth.is_superuser = true',
            'chat' => '@request.auth.id != null',
        ];
        $this->collection->save();
    });

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cdf',
        'name' => 'rule-bot-2',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Illuminate\Support\Facades\Auth::logout();

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'rule-bot-2',
        'prompt' => 'Hello',
    ]);

    $response->assertStatus(403);
});

it('triggers ai.generating and ai.generated hooks during chat', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    \Veloquent\Core\Domain\Collections\Models\Collection::withoutEvents(function () {
        $this->collection->api_rules = [
            'list' => '@request.auth.id != null',
            'view' => '@request.auth.id != null',
            'create' => '@request.auth.is_superuser = true',
            'update' => '@request.auth.is_superuser = true',
            'delete' => '@request.auth.is_superuser = true',
            'chat' => '',
        ];
        $this->collection->save();
    });

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cde',
        'name' => 'hook-bot',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(\Veloquent\Core\Domain\Hooks\Contracts\HookRegistry::class)->register(
        'ai.generating',
        fn (\Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload $payload, Closure $next): mixed => $next($payload->withData(array_merge($payload->data, ['prompt' => $payload->data['prompt'] . ' modified by hook'])))
    );

    app(\Veloquent\Core\Domain\Hooks\Contracts\HookRegistry::class)->register(
        'ai.generated',
        fn (\Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload $payload, Closure $next): mixed => $next($payload->withData(array_merge($payload->data, ['text' => $payload->data['text'] . ' post-processed'])))
    );

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::on(function ($prompt) {
            return $prompt->prompt === 'Original prompt modified by hook';
        }))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'Response',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->andReturn($mockProvider);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'hook-bot',
        'prompt' => 'Original prompt',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Response post-processed');
});

it('merges agents collection with system fields when created with empty fields', function () {
    $response = postJson('/api/collections', [
        'type' => 'agents',
        'is_system' => false,
        'name' => 'test_agents_collection',
        'table_name' => '_velo_test_agents_collection',
        'description' => 'Test agents collection',
        'fields' => [],
        'api_rules' => [
            'list' => '@request.auth.id != null',
            'view' => '@request.auth.id != null',
            'create' => '@request.auth.is_superuser = true',
            'update' => '@request.auth.is_superuser = true',
            'delete' => '@request.auth.is_superuser = true',
            'chat' => '@request.auth.id != null',
        ],
    ]);

    $response->assertStatus(200);
    
    // Assert all agents reserved fields are in the collection fields
    $collection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'test_agents_collection')->first();
    $fieldNames = collect($collection->fields)->pluck('name')->all();

    foreach (\Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange::getAgentsReservedFields() as $field) {
        expect($fieldNames)->toContain($field);
    }

    // Try to update the collection and remove a reserved field -> should fail
    $updatePayload = [
        'name' => 'test_agents_collection',
        'fields' => collect($collection->fields)->reject(fn($f) => $f['name'] === 'system_prompt')->all(),
    ];

    $updateResponse = patchJson("/api/collections/{$collection->id}", $updatePayload);
    $updateResponse->assertStatus(422);
    $updateResponse->assertJsonValidationErrors('fields.3');

    // Try to update the collection and modify a reserved field type -> should fail
    $modifiedFields = collect($collection->fields)->map(function ($f) {
        if ($f['name'] === 'system_prompt') {
            $f['type'] = 'number'; // system_prompt is text
        }
        return $f;
    })->all();

    $updatePayload2 = [
        'name' => 'test_agents_collection',
        'fields' => $modifiedFields,
    ];

    $updateResponse2 = patchJson("/api/collections/{$collection->id}", $updatePayload2);
});

it('resolves self-referential target_collection_id on agents collection create', function () {
    $response = postJson('/api/collections', [
        'type' => 'agents',
        'is_system' => false,
        'name' => 'test_self_agents',
        'table_name' => '_velo_test_self_agents',
        'description' => 'Test self-referential agents collection',
        'fields' => [],
        'api_rules' => [
            'list' => '@request.auth.id != null',
            'view' => '@request.auth.id != null',
            'create' => '@request.auth.is_superuser = true',
            'update' => '@request.auth.is_superuser = true',
            'delete' => '@request.auth.is_superuser = true',
            'chat' => '@request.auth.id != null',
        ],
    ]);

    $response->assertStatus(200);

    $collection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'test_self_agents')->first();
    expect($collection)->not->toBeNull();

    // Check target_collection_id in fields metadata
    $watchersField = collect($collection->fields)->firstWhere('name', 'watchers');
    expect($watchersField)->not->toBeNull();
    expect($watchersField['target_collection_id'])->toBe($collection->id);
    expect($watchersField['target_collection_id'])->not->toBe('@self');

    // Check pivot table existence
    $pivotTable = '_velo_test_self_agents_test_self_agents_watchers_pivot';
    expect(\Illuminate\Support\Facades\Schema::hasTable($pivotTable))->toBeTrue();
});

it('passes through and logs a warning if the pivot table does not exist', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4caa',
        'name' => 'regular-bot',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'tone' => 'friendly',
        'length' => 'short',
        'temperature' => 0.5,
        'output_type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::dropIfExists('_velo_agents_agents_watchers_pivot');
    \Veloquent\Core\Support\Database\SchemaCache::forget('_velo_agents_agents_watchers_pivot');

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'Hello!',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    \Illuminate\Support\Facades\Log::shouldReceive('warning')
        ->once()
        ->with(Mockery::pattern('/Watchers pivot table.*does not exist/'));
    \Illuminate\Support\Facades\Log::shouldIgnoreMissing();

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 123);
    actingAs($regularUser, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'regular-bot',
        'prompt' => 'Hi',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Hello!');
});

it('allows normal prompt when watchers field is empty', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4caa',
        'name' => 'regular-bot',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'tone' => 'friendly',
        'length' => 'short',
        'temperature' => 0.5,
        'output_type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id',
            'Hello!',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->with(Mockery::type(\Veloquent\Core\Domain\Ai\Agents\VeloquentAgent::class), 'openai')
        ->andReturn($mockProvider);

    // Act as regular user (non-superuser) to make sure hook is evaluated
    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 123);
    actingAs($regularUser, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'regular-bot',
        'prompt' => 'Hi',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Hello!');
});

it('allows normal prompt when watcher decides it is safe', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cab',
        'name' => 'safe-bot',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cac',
        'name' => 'my-watcher',
        'type' => 'watcher',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Check safety.',
        'temperature' => 0.0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('_velo_agents_agents_watchers_pivot')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'source_id' => '01h7c989r148s89m257a3b4cab',
        'target_id' => '01h7c989r148s89m257a3b4cac',
        'created_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);

    // First prompt call: to the watcher agent (uses StructuredVeloquentAgent)
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::on(function ($prompt) {
            return $prompt->agent instanceof \Veloquent\Core\Domain\Ai\Agents\StructuredVeloquentAgent;
        }))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-watcher',
            '{"safe": true, "message": ""}',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    // Second prompt call: to the main agent
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::on(function ($prompt) {
            return $prompt->agent instanceof \Veloquent\Core\Domain\Ai\Agents\VeloquentAgent
                && !($prompt->agent instanceof \Veloquent\Core\Domain\Ai\Agents\StructuredVeloquentAgent);
        }))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-main',
            'Hello from main!',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->twice()
        ->andReturn($mockProvider);

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 123);
    actingAs($regularUser, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'safe-bot',
        'prompt' => 'Hi',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Hello from main!');
});

it('blocks malicious prompt with fallback static message', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cad',
        'name' => 'protected-bot-1',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cae',
        'name' => 'my-watcher-1',
        'type' => 'watcher',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Check safety.',
        'watcher_message' => 'Blocked by fallback message.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('_velo_agents_agents_watchers_pivot')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'source_id' => '01h7c989r148s89m257a3b4cad',
        'target_id' => '01h7c989r148s89m257a3b4cae',
        'created_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-watcher',
            '{"safe": false, "message": ""}',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->andReturn($mockProvider);

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 123);
    actingAs($regularUser, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'protected-bot-1',
        'prompt' => 'Hi',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Blocked by fallback message.');
    $response->assertJsonPath('data.json', null);
});

it('blocks malicious prompt with dynamic message from watcher', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4caf',
        'name' => 'protected-bot-2',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb0',
        'name' => 'my-watcher-2',
        'type' => 'watcher',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Check safety.',
        'watcher_message' => 'Fallback message.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('_velo_agents_agents_watchers_pivot')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'source_id' => '01h7c989r148s89m257a3b4caf',
        'target_id' => '01h7c989r148s89m257a3b4cb0',
        'created_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-watcher',
            '{"safe": false, "message": "Blocked dynamically by AI assistant."}',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->andReturn($mockProvider);

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 123);
    actingAs($regularUser, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'protected-bot-2',
        'prompt' => 'Hi',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Blocked dynamically by AI assistant.');
});

it('stops pipeline when any watcher in sequential chain fails', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb1',
        'name' => 'pipeline-bot',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb2',
        'name' => 'watcher-a',
        'type' => 'watcher',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Check safety A.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb3',
        'name' => 'watcher-b',
        'type' => 'watcher',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Check safety B.',
        'watcher_message' => 'Blocked by watcher B.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('_velo_agents_agents_watchers_pivot')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'source_id' => '01h7c989r148s89m257a3b4cb1',
        'target_id' => '01h7c989r148s89m257a3b4cb2',
        'created_at' => now(),
    ]);

    DB::table('_velo_agents_agents_watchers_pivot')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'source_id' => '01h7c989r148s89m257a3b4cb1',
        'target_id' => '01h7c989r148s89m257a3b4cb3',
        'created_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);

    // First watcher returns safe
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::on(function ($prompt) {
            return $prompt->agent->instructions() === 'Check safety A.';
        }))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-watcher-a',
            '{"safe": true, "message": ""}',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    // Second watcher returns unsafe, triggering immediate block response
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::on(function ($prompt) {
            return $prompt->agent->instructions() === 'Check safety B.';
        }))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-watcher-b',
            '{"safe": false, "message": ""}',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->twice()
        ->andReturn($mockProvider);

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 123);
    actingAs($regularUser, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'pipeline-bot',
        'prompt' => 'Hi',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Blocked by watcher B.');
});

it('bypasses watchers completely for superusers', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb4',
        'name' => 'superuser-bot',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb5',
        'name' => 'unsafe-watcher',
        'type' => 'watcher',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Check safety.',
        'watcher_message' => 'Should not see this.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('_velo_agents_agents_watchers_pivot')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'source_id' => '01h7c989r148s89m257a3b4cb4',
        'target_id' => '01h7c989r148s89m257a3b4cb5',
        'created_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);

    // Only prompt call should be to the main agent
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->with(Mockery::on(function ($prompt) {
            return $prompt->agent instanceof \Veloquent\Core\Domain\Ai\Agents\VeloquentAgent;
        }))
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-main',
            'Hello Superuser!',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->andReturn($mockProvider);

    // Act as superuser (which has isSuperuser() returning true)
    actingAs($this->user, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'superuser-bot',
        'prompt' => 'Hi',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Hello Superuser!');
});

it('fails with 422 and returns Cannot process request when output type is json', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb6',
        'name' => 'json-agent',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'output_type' => 'json',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb7',
        'name' => 'watcher-json',
        'type' => 'watcher',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Check safety.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('_velo_agents_agents_watchers_pivot')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'source_id' => '01h7c989r148s89m257a3b4cb6',
        'target_id' => '01h7c989r148s89m257a3b4cb7',
        'created_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-watcher',
            '{"safe": false, "message": "Unsafe prompt content."}',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->andReturn($mockProvider);

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 123);
    actingAs($regularUser, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'json-agent',
        'prompt' => 'Hi',
        'output_type' => 'json',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Cannot process request.');
});

it('handles invalid json from watcher robustly by logging and treating as unsafe', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb8',
        'name' => 'text-agent',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'output_type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cb9',
        'name' => 'watcher-text',
        'type' => 'watcher',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Check safety.',
        'watcher_message' => 'Blocked fallback.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('_velo_agents_agents_watchers_pivot')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'source_id' => '01h7c989r148s89m257a3b4cb8',
        'target_id' => '01h7c989r148s89m257a3b4cb9',
        'created_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-watcher',
            'not-valid-json',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->andReturn($mockProvider);

    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with(Mockery::pattern('/Watcher agent returned invalid JSON response/'));
    \Illuminate\Support\Facades\Log::shouldIgnoreMissing();

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 123);
    actingAs($regularUser, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'text-agent',
        'prompt' => 'Hi',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Invalid security checker response.');
});

it('logs block event on stream requests when stream is true and output type is text', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4cca',
        'name' => 'stream-agent',
        'type' => 'regular',
        'model' => 'gpt-4o',
        'system_prompt' => 'Be helpful.',
        'output_type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4ccb',
        'name' => 'watcher-stream',
        'type' => 'watcher',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'Check safety.',
        'watcher_message' => 'Blocked fallback.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('_velo_agents_agents_watchers_pivot')->insert([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'source_id' => '01h7c989r148s89m257a3b4cca',
        'target_id' => '01h7c989r148s89m257a3b4ccb',
        'created_at' => now(),
    ]);

    $mockProvider = Mockery::mock(\Laravel\Ai\Providers\OpenAiProvider::class);
    $mockProvider->shouldReceive('prompt')
        ->once()
        ->andReturn(new \Laravel\Ai\Responses\AgentResponse(
            'invocation-id-watcher',
            '{"safe": false, "message": "Stream blocked message."}',
            new \Laravel\Ai\Responses\Data\Usage,
            new \Laravel\Ai\Responses\Data\Meta
        ));

    Ai::shouldReceive('textProviderFor')
        ->once()
        ->andReturn($mockProvider);

    \Illuminate\Support\Facades\Log::shouldReceive('warning')
        ->once()
        ->with('Request blocked by watcher', Mockery::type('array'));
    \Illuminate\Support\Facades\Log::shouldIgnoreMissing();

    $usersCollection = \Veloquent\Core\Domain\Collections\Models\Collection::where('name', 'users')->first();
    $regularUser = \Veloquent\Core\Domain\Records\Models\Record::of($usersCollection);
    $regularUser->setAttribute('id', 123);
    actingAs($regularUser, 'api');

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'stream-agent',
        'prompt' => 'Hi',
        'stream' => true,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.text', 'Stream blocked message.');
});

it('fails validation when streaming is enabled on a JSON output agent via database config', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4c1a',
        'name' => 'db-json-bot',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'You output JSON.',
        'tone' => 'friendly',
        'length' => 'short',
        'temperature' => 0.7,
        'output_type' => 'json',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'db-json-bot',
        'prompt' => 'Hello',
        'stream' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['stream']);
});

it('fails validation when streaming and JSON output type are both requested in the payload', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4c1b',
        'name' => 'payload-text-bot',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'You output text.',
        'tone' => 'friendly',
        'length' => 'short',
        'temperature' => 0.7,
        'output_type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = postJson("/api/collections/{$this->collection->id}/ai/chat", [
        'agent' => 'payload-text-bot',
        'prompt' => 'Hello',
        'output_type' => 'json',
        'stream' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['stream']);
});

it('properly structures list schemas under items property', function () {
    $settings = app(AiSettings::class);
    $settings->ai_provider = 'openai';
    $settings->ai_model = 'gpt-4o-mini';
    $settings->ai_api_key = 'sk-proj-test';
    $settings->save();

    DB::table($this->tableName)->insert([
        'id' => '01h7c989r148s89m257a3b4c1c',
        'name' => 'list-schema-bot',
        'model' => 'gpt-4o-mini',
        'system_prompt' => 'You output JSON.',
        'tone' => 'friendly',
        'length' => 'short',
        'temperature' => 0.7,
        'output_type' => 'json',
        'schema' => json_encode(['string']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $agent = \Veloquent\Core\Domain\Records\Models\Record::of($this->collection)->find('01h7c989r148s89m257a3b4c1c');
    $structuredAgent = new \Veloquent\Core\Domain\Ai\Agents\StructuredVeloquentAgent(
        instructions: $agent->system_prompt,
        messages: [],
        temperature: 0.7,
        schema: is_array($agent->schema) ? $agent->schema : json_decode((string) $agent->schema, true)
    );

    $factory = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
    $mappedSchema = $structuredAgent->schema($factory);
    
    $serialized = (new \Laravel\Ai\ObjectSchema($mappedSchema))->toSchema();
    
    expect($serialized['properties'])->toBeArray()
        ->and(array_is_list($serialized['properties']))->toBeFalse()
        ->and($serialized['properties'])->toHaveKey('items');
});
