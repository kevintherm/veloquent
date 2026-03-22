<?php

use App\Http\Middleware\TokenAuthMiddleware;
use App\Models\Superuser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->testDate = '2020-01-01';
    $this->logPath = storage_path("logs/laravel-{$this->testDate}.log");

    $content = [
        '[2020-01-01 00:00:01] local.INFO: First log {"key":"val"}',
        '[2020-01-01 01:00:01] local.ERROR: Second log {"error":"true"}',
        '[2020-01-01 02:00:01] local.WARNING: Third log {"warn":"yes"}',
        '[2020-01-01 03:00:01] local.INFO: Fourth log',
        '[2020-01-01 03:30:01] local.INFO: Fifth log matching query',
    ];

    File::ensureDirectoryExists(storage_path('logs'));
    File::put($this->logPath, implode("\n", $content));

    $this->user = Superuser::factory()->create();
    actingAs($this->user, 'api');

    // The TokenAuthMiddleware clears the guard if no token is in request,
    // which breaks actingAs(). So we bypass it.
    withoutMiddleware(TokenAuthMiddleware::class);
});

afterEach(function () {
    /** @var TestCase $this */
    if (File::exists($this->logPath)) {
        File::delete($this->logPath);
    }
});

it('can fetch log dates', function () {
    /** @var TestCase $this */
    $this->getJson('/api/logs/dates')
        ->assertSuccessful()
        ->assertJsonFragment([$this->testDate]);
});

it('can fetch paginated logs', function () {
    /** @var TestCase $this */
    $this->getJson("/api/logs?date={$this->testDate}&per_page=2&page=1")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 3);
});

it('can filter logs by level', function () {
    /** @var TestCase $this */
    $this->getJson("/api/logs?date={$this->testDate}&level=ERROR")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.level', 'ERROR');
});

it('can filter logs by query', function () {
    /** @var TestCase $this */
    $this->getJson("/api/logs?date={$this->testDate}&query=matching")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.message', 'Fifth log matching query');
});

it('can filter logs by hour', function () {
    /** @var TestCase $this */
    $this->getJson("/api/logs?date={$this->testDate}&hour=1")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.level', 'ERROR');
});

it('returns hourly statistics', function () {
    /** @var TestCase $this */
    $response = $this->getJson("/api/logs?date={$this->testDate}");

    $response->assertSuccessful()
        ->assertJsonStructure(['stats']);

    $stats = $response->json('stats');
    expect($stats[0]['count'])->toBe(1);
    expect($stats[1]['error'])->toBe(1);
    expect($stats[2]['warning'])->toBe(1);
    expect($stats[3]['count'])->toBe(2);
});
