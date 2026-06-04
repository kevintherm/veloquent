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
        return \Veloquent\Core\Domain\Collections\Models\Collection::create([
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
    $updateResponse->assertJsonValidationErrors('fields.2');

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
    $updateResponse2->assertStatus(422);
});
