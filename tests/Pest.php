<?php

use App\Infrastructure\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

beforeEach(function () {
    if (! app()->has('currentTenant')) {
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
});
