<?php

namespace Tests\Feature;

use Veloquent\Core\Support\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Veloquent\Core\Tests\TestCase;
use Spatie\Multitenancy\Jobs\TenantAware;

uses(RefreshDatabase::class);

// Define a test job that records its runtime context
class TestIsolationJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public static array $executionLog = [];

    public function __construct(public string $jobIdentifier) {}

    public function handle(): void
    {
        $currentTenant = app()->has('currentTenant') ? app('currentTenant') : null;

        self::$executionLog[] = [
            'identifier' => $this->jobIdentifier,
            'tenant_id' => $currentTenant?->id,
            'tenant_name' => $currentTenant?->name,
            'redis_prefix' => config('database.redis.options.prefix'),
            'filesystem_default' => config('filesystems.default'),
            'filesystem_root' => config('filesystems.disks.tenant.root'),
            'log_path' => config('logging.channels.daily.path'),
            'cache_prefix' => config('cache.prefix'),
        ];
    }
}

beforeEach(function (): void {
    // Clear the execution log before each test run
    TestIsolationJob::$executionLog = [];

    // Ensure clean container and state
    Tenant::forgetCurrent();

    // Delete existing tenant to avoid any unique constraint issues with :memory:
    Tenant::query()->delete();

    // Create Tenant A
    $this->tenantA = Tenant::withoutEvents(fn () => Tenant::create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.test',
        'database' => null,
    ]));

    // Create Tenant B
    $this->tenantB = Tenant::withoutEvents(fn () => Tenant::create([
        'name' => 'Tenant B',
        'domain' => 'tenant-b.test',
        'database' => null,
    ]));
});

afterEach(function (): void {
    // Forget current tenant to restore baseline state
    Tenant::forgetCurrent();
});

it('isolates database, redis, filesystem, and log contexts sequentially during queued job executions', function () {
    // 1. Dispatch first job under Tenant A's active context
    $this->tenantA->makeCurrent();
    expect(Tenant::current()->id)->toBe($this->tenantA->id);
    TestIsolationJob::dispatch('job_a');
    Tenant::forgetCurrent();

    // 2. Dispatch second job under Tenant B's active context
    $this->tenantB->makeCurrent();
    expect(Tenant::current()->id)->toBe($this->tenantB->id);
    TestIsolationJob::dispatch('job_b');
    Tenant::forgetCurrent();

    // Verify both jobs executed sequentially via Laravel's sync queue
    expect(TestIsolationJob::$executionLog)->toHaveCount(2);

    $logA = TestIsolationJob::$executionLog[0];
    $logB = TestIsolationJob::$executionLog[1];

    // Assert Job A executed strictly in Tenant A's isolated environment
    expect($logA['identifier'])->toBe('job_a');
    expect($logA['tenant_id'])->toBe($this->tenantA->id);
    expect($logA['tenant_name'])->toBe('Tenant A');
    expect($logA['redis_prefix'])->toContain('tenant_' . $this->tenantA->id);
    expect($logA['filesystem_default'])->toBe('tenant');
    expect($logA['filesystem_root'])->toContain('tenants/' . $this->tenantA->id);
    expect($logA['log_path'])->toContain('tenants/' . $this->tenantA->id);
    expect($logA['cache_prefix'])->toContain('tenant_id_' . $this->tenantA->id);

    // Assert Job B executed strictly in Tenant B's isolated environment
    expect($logB['identifier'])->toBe('job_b');
    expect($logB['tenant_id'])->toBe($this->tenantB->id);
    expect($logB['tenant_name'])->toBe('Tenant B');
    expect($logB['redis_prefix'])->toContain('tenant_' . $this->tenantB->id);
    expect($logB['filesystem_default'])->toBe('tenant');
    expect($logB['filesystem_root'])->toContain('tenants/' . $this->tenantB->id);
    expect($logB['log_path'])->toContain('tenants/' . $this->tenantB->id);
    expect($logB['cache_prefix'])->toContain('tenant_id_' . $this->tenantB->id);

    // Assert complete lack of leakage (environments differ fully)
    expect($logA['redis_prefix'])->not->toBe($logB['redis_prefix']);
    expect($logA['filesystem_root'])->not->toBe($logB['filesystem_root']);
    expect($logA['log_path'])->not->toBe($logB['log_path']);
    expect($logA['cache_prefix'])->not->toBe($logB['cache_prefix']);
});

it('restores baseline landlord context after job completion', function () {
    // Get landlord database file before tenant switching
    $landlordRedisPrefix = config('database.redis.options.prefix');
    $landlordFilesystemDefault = config('filesystems.default');
    $landlordLogPath = config('logging.channels.daily.path');

    // Run job under Tenant A's context
    $this->tenantA->makeCurrent();
    TestIsolationJob::dispatch('job_a_restorer');
    Tenant::forgetCurrent();

    // Verify after job completes, baseline/landlord settings are perfectly restored
    expect(app()->has('currentTenant'))->toBeFalse();
    expect(Tenant::current())->toBeNull();
    expect(config('database.redis.options.prefix'))->toBe($landlordRedisPrefix);
    expect(config('filesystems.default'))->toBe($landlordFilesystemDefault);
    expect(config('logging.channels.daily.path'))->toBe($landlordLogPath);
});
