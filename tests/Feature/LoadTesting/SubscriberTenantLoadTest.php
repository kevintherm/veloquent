<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Veloquent\Core\Support\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;
use Veloquent\Core\Domain\Realtime\Models\RealtimeSubscription;
use Veloquent\Core\Domain\Realtime\Services\RealtimeDispatcher;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Lift PHP memory restrictions during high-volume benchmarks
    ini_set('memory_limit', '-1');

    // Clean start
    Tenant::forgetCurrent();
    Tenant::query()->delete();
    RealtimeSubscription::query()->delete();

    // Dynamically resolve scaling parameters from environment variables
    $tenantCount = (int) env('BENCHMARK_TENANT_COUNT', 200);
    $subscriptionsPerTenant = (int) env('BENCHMARK_SUBSCRIPTIONS_PER_TENANT', 50);

    // Create virtual tenants dynamically based on the configuration
    $this->tenants = [];
    for ($i = 1; $i <= $tenantCount; $i++) {
        $this->tenants[] = Tenant::withoutEvents(fn () => Tenant::create([
            'name' => "Load Test Tenant {$i}",
            'domain' => "tenant-{$i}.test",
            'database' => null, // runs in-memory
        ]));
    }

    // Set up a collection in the landlord/tenant database context
    $this->collection = Collection::withoutEvents(fn () => Collection::query()->create([
        'id' => (string) Str::ulid(),
        'name' => 'posts',
        'type' => CollectionType::Base,
        'table_name' => '_velo_posts',
    ]));

    // Seed realtime subscriptions dynamically in batches to maintain a tiny memory footprint
    $batch = [];
    foreach ($this->tenants as $index => $tenant) {
        for ($j = 1; $j <= $subscriptionsPerTenant; $j++) {
            $batch[] = [
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenant->id,
                'collection_id' => $this->collection->id,
                'auth_collection' => 'users',
                'subscriber_id' => (string) Str::ulid(),
                'filter' => 'status = "active"',
                'channel' => "presence-posts-{$index}-{$j}",
                'expired_at' => now()->addHours(2)->toDateTimeString(),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            // Flushes directly to DB in chunks of 100 to avoid array memory growth
            if (count($batch) >= 100) {
                RealtimeSubscription::query()->insert($batch);
                $batch = [];
            }
        }
    }

    // Flush any remaining records
    if (!empty($batch)) {
        RealtimeSubscription::query()->insert($batch);
    }
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

it('benchmarks tenant context-switching and dispatches under high volume with low latency', function () {
    // Dynamically resolve operational variables
    $tenantCount = (int) env('BENCHMARK_TENANT_COUNT', 200);
    $subscriptionsPerTenant = (int) env('BENCHMARK_SUBSCRIPTIONS_PER_TENANT', 50);
    $switchCount = (int) env('BENCHMARK_SWITCH_COUNT', 5000);
    $dispatchLoopCount = (int) env('BENCHMARK_DISPATCH_COUNT', 100);
    $subscriptionsCount = $tenantCount * $subscriptionsPerTenant;

    // ── Benchmark 1: Tenant Context-Switching ─────────────────────────────────
    $switchDurations = [];
    $startSwitchTime = microtime(true);
    $startMemory = memory_get_usage();

    for ($k = 0; $k < $switchCount; $k++) {
        // Pick a random tenant from our pool
        $tenant = $this->tenants[$k % count($this->tenants)];
        
        $start = microtime(true);
        $tenant->makeCurrent();
        $end = microtime(true);
        
        $switchDurations[] = ($end - $start) * 1000; // ms
    }

    $endSwitchTime = microtime(true);
    $endMemory = memory_get_usage();
    Tenant::forgetCurrent();

    $totalSwitchTime = ($endSwitchTime - $startSwitchTime) * 1000; // ms
    $avgSwitchTime = array_sum($switchDurations) / count($switchDurations);
    $minSwitchTime = min($switchDurations);
    $maxSwitchTime = max($switchDurations);
    $memoryDelta = ($endMemory - $startMemory) / 1024 / 1024; // MB

    // ── Benchmark 2: Realtime Dispatcher Scaling ──────────────────────────────
    // Mock broadcasting events so we don't hit external pusher drivers during load testing
    Event::fake([
        \Veloquent\Core\Domain\Records\Events\RecordChanged::class,
    ]);

    $dispatcher = app(RealtimeDispatcher::class);

    // Track query counts during dispatches
    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    $dispatchDurations = [];
    $startDispatchTime = microtime(true);

    for ($d = 0; $d < $dispatchLoopCount; $d++) {
        // Select a random tenant for each event dispatch to test swap + compile + run path
        $testTenant = $this->tenants[$d % count($this->tenants)];
        $testTenant->makeCurrent();

        $event = new RealtimeRecordEvent(
            tenantId: $testTenant->id,
            collectionId: $this->collection->id,
            event: 'create',
            record: ['id' => Str::ulid(), 'status' => 'active', 'title' => "Load Test Post {$d}"]
        );

        $start = microtime(true);
        $dispatcher->dispatch($event);
        $end = microtime(true);

        $dispatchDurations[] = ($end - $start) * 1000; // ms
    }
    
    $endDispatchTime = microtime(true);
    Tenant::forgetCurrent();

    $totalDispatchTime = ($endDispatchTime - $startDispatchTime) * 1000; // ms
    $avgDispatchTime = array_sum($dispatchDurations) / count($dispatchDurations);
    $minDispatchTime = min($dispatchDurations);
    $maxDispatchTime = max($dispatchDurations);

    // ── Print Premium Performance Report ─────────────────────────────────────
    $switchesPerSecond = number_format(1000 / ($avgSwitchTime ?: 1), 0);
    $dispatchesPerSecond = number_format(1000 / ($avgDispatchTime ?: 1), 0);
    
    dump("==========================================================================");
    dump("             VELOQUENT REALTIME DISPATCH & SCALING BENCHMARK             ");
    dump("==========================================================================");
    dump("  Active Virtual Tenants    : " . number_format($tenantCount));
    dump("  Active Subscriptions     : " . number_format($subscriptionsCount));
    dump("--------------------------------------------------------------------------");
    dump("  [BENCHMARK 1] Tenant Context-Switching ($switchCount switches)");
    dump("    - Total Elapsed Time    : " . number_format($totalSwitchTime, 2) . " ms");
    dump("    - Average Swap Latency  : " . number_format($avgSwitchTime, 4) . " ms");
    dump("    - Best Swap Latency     : " . number_format($minSwitchTime, 4) . " ms");
    dump("    - Worst Swap Latency    : " . number_format($maxSwitchTime, 4) . " ms");
    dump("    - Throughput Performance: " . $switchesPerSecond . " swaps/sec");
    dump("    - Memory Delta Footprint: " . number_format($memoryDelta, 4) . " MB");
    dump("--------------------------------------------------------------------------");
    dump("  [BENCHMARK 2] Realtime Dispatcher Scaling (" . number_format($subscriptionsCount) . " active rules)");
    dump("    - Total Event Dispatches: " . $dispatchLoopCount);
    dump("    - Total Processing Time : " . number_format($totalDispatchTime, 2) . " ms");
    dump("    - Average Event Latency : " . number_format($avgDispatchTime, 4) . " ms");
    dump("    - Best Event Latency    : " . number_format($minDispatchTime, 4) . " ms");
    dump("    - Worst Event Latency   : " . number_format($maxDispatchTime, 4) . " ms");
    dump("    - Throughput Performance: " . $dispatchesPerSecond . " events/sec");
    dump("    - Landlord DB Query Count: " . $queryCount);
    dump("==========================================================================");
})->skip(fn () => ! env('RUN_BENCHMARKS'), 'Skipped by default to prevent blocking test suites. Run explicitly with RUN_BENCHMARKS=true');
