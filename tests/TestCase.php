<?php

namespace Tests;

use App\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetDatabase();

        Tenant::withoutEvents(function () {
            $tenant = Tenant::create([
                'name' => 'test-tenant',
                'domain' => 'test.local',
                'database' => ':memory:',
            ]);

            app()->instance('currentTenant', $tenant);
            $tenant->makeCurrent();
        });
    }

    protected function resetDatabase(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/landlord', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
    }

    protected function tearDown(): void
    {
        if (app()->has('currentTenant')) {
            app()->forgetInstance('currentTenant');
            Tenant::forgetCurrent();
        }

        parent::tearDown();
    }
}
