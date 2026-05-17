<?php

use Veloquent\Core\Support\Models\Tenant;
use Veloquent\Core\Tests\TestCase;

uses(TestCase::class)->in('Feature');

beforeEach(function () {
    if (! app()->has('currentTenant')) {
        Tenant::withoutEvents(function () {
            $tenant = Tenant::create([
                'name' => 'test-tenant',
                'domain' => 'test.local',
                'database' => ':memory:',
            ]);

            $tenant->makeCurrent();
        });
    }
});
