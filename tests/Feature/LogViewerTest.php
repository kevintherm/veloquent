<?php

use App\Domain\Auth\Models\Superuser;
use App\Http\Middleware\TokenAuthMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->testDate = '2020-01-01';
    $this->logPath = storage_path("logs/laravel-{$this->testDate}.log");

    $content = [
        json_encode(['datetime' => '2020-01-01 00:00:01', 'level_name' => 'INFO', 'channel' => 'local', 'message' => 'First log', 'context' => ['key' => 'val']]),
        json_encode(['datetime' => '2020-01-01 01:00:01', 'level_name' => 'ERROR', 'channel' => 'local', 'message' => 'Second log', 'context' => ['error' => true]]),
        json_encode(['datetime' => '2020-01-01 02:00:01', 'level_name' => 'WARNING', 'channel' => 'local', 'message' => 'Third log', 'context' => ['warn' => 'yes']]),
        json_encode(['datetime' => '2020-01-01 03:00:01', 'level_name' => 'INFO', 'channel' => 'local', 'message' => 'Fourth log', 'context' => null]),
        json_encode(['datetime' => '2020-01-01 03:30:01', 'level_name' => 'INFO', 'channel' => 'local', 'message' => 'Fifth log matching query', 'context' => null]),
    ];

    File::ensureDirectoryExists(storage_path('logs'));
    File::put($this->logPath, implode("\n", $content));

    if (Schema::hasTable('superusers')) {
        $this->user = Superuser::factory()->create();
        actingAs($this->user, 'api');
    } else {
        withoutMiddleware();
    }

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
    // With 5 logs and per_page=2, the total pages are 3.
    // Sliding window: Page 1 is the latest 2 logs (Matches 3, 4).
    $this->getJson("/api/logs?date={$this->testDate}&per_page=2&page=1")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.message', 'Fifth log matching query')
        ->assertJsonPath('data.1.message', 'Fourth log');

    // Page 2 is matches 1, 2.
    $this->getJson("/api/logs?date={$this->testDate}&per_page=2&page=2")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.message', 'Third log')
        ->assertJsonPath('data.1.message', 'Second log');

    // Page 3 is match 0.
    $this->getJson("/api/logs?date={$this->testDate}&per_page=2&page=3")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.message', 'First log');
});

it('registers new logs on page 1 with sliding window', function () {
    /** @var TestCase $this */
    // Currently 5 logs. Page 1 (per_page=2) is [Fifth log, Fourth log].
    $this->getJson("/api/logs?date={$this->testDate}&per_page=2&page=1")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.message', 'Fifth log matching query')
        ->assertJsonPath('data.1.message', 'Fourth log');

    // Append a new log
    $newLog = json_encode(['datetime' => '2020-01-01 04:00:00', 'level_name' => 'INFO', 'channel' => 'local', 'message' => 'Sixth log appended', 'context' => null]);
    $handle = fopen($this->logPath, 'a');
    fwrite($handle, "\n".$newLog);
    fclose($handle);

    // Now 6 logs. Page 1 (per_page=2) should be [Sixth log, Fifth log].
    $this->getJson("/api/logs?date={$this->testDate}&per_page=2&page=1")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.message', 'Sixth log appended')
        ->assertJsonPath('data.1.message', 'Fifth log matching query');

    // Page 2 should now be [Fourth log, Third log].
    $this->getJson("/api/logs?date={$this->testDate}&per_page=2&page=2")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.message', 'Fourth log')
        ->assertJsonPath('data.1.message', 'Third log');
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

it('uses the configured daily channel path for date listing and log reading', function () {
    /** @var TestCase $this */
    $tenantDate = '2020-01-02';
    $tenantLogPath = storage_path("tenants/777/logs/laravel-{$tenantDate}.log");

    config()->set('logging.channels.daily.path', storage_path('tenants/777/logs/laravel.log'));

    File::ensureDirectoryExists(dirname($tenantLogPath));
    File::put($tenantLogPath, json_encode([
        'datetime' => '2020-01-02 08:00:00',
        'level_name' => 'INFO',
        'channel' => 'local',
        'message' => 'Tenant log entry',
        'context' => null,
    ]));

    $this->getJson('/api/logs/dates')
        ->assertSuccessful()
        ->assertJsonFragment([$tenantDate])
        ->assertJsonMissing([$this->testDate]);

    $this->getJson("/api/logs?date={$tenantDate}")
        ->assertSuccessful()
        ->assertJsonPath('data.0.message', 'Tenant log entry');

    File::delete($tenantLogPath);
    File::deleteDirectory(storage_path('tenants/777'));
});

it('performs well with 100k entries', function () {
    /** @var TestCase $this */
    $count = 100000;
    $date = '2020-02-02';
    $path = storage_path("logs/laravel-{$date}.log");

    $handle = fopen($path, 'w');
    for ($i = 0; $i < $count; $i++) {
        $hour = str_pad(rand(0, 23), 2, '0', STR_PAD_LEFT);
        fwrite($handle, json_encode([
            'datetime' => "2020-02-02 {$hour}:00:00",
            'level_name' => 'INFO',
            'channel' => 'local',
            'message' => "Stress log {$i}",
            'context' => null,
        ])."\n");
    }
    fclose($handle);

    $start1 = microtime(true);
    $response1 = $this->getJson("/api/logs?date={$date}&per_page=20&page=1");
    $end1 = microtime(true);
    $response1->assertStatus(200);

    $start2 = microtime(true);
    $response2 = $this->getJson("/api/logs?date={$date}&per_page=20&page=1");
    $end2 = microtime(true);
    $response2->assertStatus(200);

    $duration1 = ($end1 - $start1) * 1000;
    $duration2 = ($end2 - $start2) * 1000;
    dump("100k - Run 1 (Uncached): {$duration1}ms");
    dump("100k - Run 2 (Cached): {$duration2}ms");

    if (File::exists($path)) {
        File::delete($path);
    }
})->group('stress');
