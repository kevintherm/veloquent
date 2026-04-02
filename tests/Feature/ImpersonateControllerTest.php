<?php

use App\Domain\Auth\Models\Superuser;
use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use App\Domain\Auth\Services\TokenAuthService;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authCollection = app(CreateCollectionAction::class)->execute([
        'type' => CollectionType::Auth,
        'name' => 'test_users',
        'fields' => [],
        'indexes' => [],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
            'manage' => null,
        ],
    ]);

    $this->standardCollection = app(CreateCollectionAction::class)->execute([
        'type' => CollectionType::Base,
        'name' => 'test_posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'nullable' => false],
        ],
        'indexes' => [],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
            'manage' => null,
        ],
    ]);

    $this->user = Record::of($this->authCollection)->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post = Record::of($this->standardCollection)->create([
        'title' => 'Hello World',
    ]);
});
it('allows superuser to masquerade/generate a token for an auth record', function () {
    $superuser = Superuser::factory()->create();
    $superuserRecord = Record::fromTable('superusers');
    $superuserRecord->forceFill($superuser->toArray());
    $superuserRecord->exists = true;

    $this->mock(TokenAuthService::class, function (\Mockery\MockInterface $mock) use ($superuserRecord) {
        $mock->shouldReceive('authenticate')->with('super-token')->andReturn($superuserRecord);
        $mock->shouldReceive('extractTokenFromRequest')->andReturn('super-token');
    })->makePartial();

    $response = $this->withHeaders(['Authorization' => 'Bearer super-token'])
        ->postJson("/api/collections/{$this->authCollection->id}/auth/impersonate/{$this->user->id}");

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'token',
            'expires_in',
            'collection_name',
        ]
    ]);
    
    $token = $response->json('data.token');
    expect($token)->not->toBeEmpty();
});

it('rejects non-superuser from generating a token', function () {
    $tokenData = app(TokenAuthService::class)->generateToken($this->user);

    $response = $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->postJson("/api/collections/{$this->authCollection->id}/auth/impersonate/{$this->user->id}");

    $response->assertForbidden();
});

it('rejects generating a token for a non-auth collection', function () {
    $superuser = Superuser::factory()->create();
    $superuserRecord = Record::fromTable('superusers');
    $superuserRecord->forceFill($superuser->toArray());
    $superuserRecord->exists = true;

    $this->mock(TokenAuthService::class, function (\Mockery\MockInterface $mock) use ($superuserRecord) {
        $mock->shouldReceive('authenticate')->with('super-token')->andReturn($superuserRecord);
        $mock->shouldReceive('extractTokenFromRequest')->andReturn('super-token');
    })->makePartial();

    $response = $this->withHeaders(['Authorization' => 'Bearer super-token'])
        ->postJson("/api/collections/{$this->standardCollection->id}/auth/impersonate/{$this->post->id}");

    $response->assertForbidden();
    $response->assertJsonPath('message', 'This collection does not support authentication.');
});

it('returns 404 for non-existent record', function () {
    $superuser = Superuser::factory()->create();
    $superuserRecord = Record::fromTable('superusers');
    $superuserRecord->forceFill($superuser->toArray());
    $superuserRecord->exists = true;

    $this->mock(TokenAuthService::class, function (\Mockery\MockInterface $mock) use ($superuserRecord) {
        $mock->shouldReceive('authenticate')->with('super-token')->andReturn($superuserRecord);
        $mock->shouldReceive('extractTokenFromRequest')->andReturn('super-token');
    })->makePartial();

    $fakeId = \Illuminate\Support\Str::uuid()->toString();

    $response = $this->withHeaders(['Authorization' => 'Bearer super-token'])
        ->postJson("/api/collections/{$this->authCollection->id}/auth/impersonate/{$fakeId}");

    $response->assertNotFound();
});
