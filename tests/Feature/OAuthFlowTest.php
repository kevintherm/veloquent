<?php

namespace Tests\Feature;

use App\Domain\Collections\Models\Collection;
use App\Domain\OAuth\Factory\OAuthDriverFactory;
use App\Domain\OAuth\Models\OAuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->collection = Collection::where('name', 'users')->first() ?? Collection::factory()->auth()->create([
        'name' => 'users',
    ]);

    $this->collection->update([
        'options' => [
            'auth_methods' => [
                'oauth' => ['enabled' => true],
            ],
        ],
    ]);

    $this->provider = OAuthProvider::where('collection_id', $this->collection->id)->where('provider', 'github')->first()
        ?? OAuthProvider::factory()->create([
            'collection_id' => $this->collection->id,
            'provider' => 'github',
            'enabled' => true,
        ]);
});

it('redirects to provider', function () {
    $mockDriver = Mockery::mock(GithubProvider::class);
    $mockDriver->shouldReceive('stateless')->andReturnSelf();
    $mockDriver->shouldReceive('with')->andReturnSelf();
    $mockDriver->shouldReceive('redirect')->andReturn(new RedirectResponse('https://github.com/login/oauth/authorize'));

    $this->instance(OAuthDriverFactory::class, Mockery::mock(OAuthDriverFactory::class, function ($mock) use ($mockDriver) {
        $mock->shouldReceive('make')->andReturn($mockDriver);
    }));

    $response = $this->postJson('/api/oauth2/redirect', [
        'collection' => $this->collection->id,
        'provider' => 'github',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.redirect_url', fn ($url) => str_contains($url, 'github.com/login/oauth/authorize'));
});

it('handles callback and creates record', function () {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->map([
        'id' => '12345',
        'nickname' => 'johndoe',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'avatar' => 'https://example.com/avatar.jpg',
    ]);

    $mockDriver = Mockery::mock(GithubProvider::class);
    $mockDriver->shouldReceive('stateless')->andReturnSelf();
    $mockDriver->shouldReceive('user')->andReturn($socialiteUser);

    $this->instance(OAuthDriverFactory::class, Mockery::mock(OAuthDriverFactory::class, function ($mock) use ($mockDriver) {
        $mock->shouldReceive('make')->andReturn($mockDriver);
    }));

    $state = 'fake-state';
    Cache::put("oauth_state:{$state}", [
        'provider' => 'github',
        'collection' => $this->collection->id,
    ]);
    $response = $this->get("/api/oauth2/callback?code=fake-code&state={$state}");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['code']]);

    $exchangeCode = $response->json('data.code');

    $exchangeResponse = $this->postJson('/api/oauth2/exchange', [
        'code' => $exchangeCode,
    ]);

    $exchangeResponse->assertStatus(200)
        ->assertJsonStructure(['data' => ['token', 'record']]);

    $this->assertDatabaseHas('oauth_accounts', [
        'provider' => 'github',
        'provider_user_id' => '12345',
        'collection_id' => $this->collection->id,
        'email' => 'john@example.com',
    ]);

    // Verify record was created in the dynamic table
    $this->assertDatabaseHas($this->collection->getPhysicalTableName(), [
        'email' => 'john@example.com',
    ]);
});

it('logs in existing oauth user', function () {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->map([
        'id' => '12345',
        'email' => 'john@example.com',
    ]);

    $mockDriver1 = Mockery::mock(GithubProvider::class);
    $mockDriver1->shouldReceive('stateless')->andReturnSelf();
    $mockDriver1->shouldReceive('user')->andReturn($socialiteUser);

    $mockDriver2 = Mockery::mock(GithubProvider::class);
    $mockDriver2->shouldReceive('stateless')->andReturnSelf();
    $mockDriver2->shouldReceive('user')->andReturn($socialiteUser);

    $mockFactory = Mockery::mock(OAuthDriverFactory::class);
    $mockFactory->shouldReceive('make')->andReturn($mockDriver1, $mockDriver2);

    $this->instance(OAuthDriverFactory::class, $mockFactory);

    // Run once to create
    $state1 = 'fake-state-1';
    Cache::put("oauth_state:{$state1}", [
        'provider' => 'github',
        'collection' => $this->collection->id,
    ]);
    $callback1 = $this->get("/api/oauth2/callback?code=fake-code&state={$state1}");
    $code1 = $callback1->json('data.code');
    $this->postJson('/api/oauth2/exchange', ['code' => $code1])->assertStatus(200);

    $count = DB::table($this->collection->getPhysicalTableName())->count();
    expect($count)->toBe(1);

    // Run again to login
    $state2 = 'fake-state-2';
    Cache::put("oauth_state:{$state2}", [
        'provider' => 'github',
        'collection' => $this->collection->id,
    ]);
    $callback2 = $this->get("/api/oauth2/callback?code=fake-code&state={$state2}");
    $code2 = $callback2->json('data.code');
    $response = $this->postJson('/api/oauth2/exchange', ['code' => $code2]);
    $response->assertStatus(200);

    // Still should only be 1 record
    $count = DB::table($this->collection->getPhysicalTableName())->count();
    expect($count)->toBe(1);
});
