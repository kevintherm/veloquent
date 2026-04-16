<?php

use App\Infrastructure\Models\Tenant;
use App\Infrastructure\Multitenancy\TenantFinders\CachedDomainTenantFinder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::clear();
    Tenant::query()->delete();
});

it('can find and cache a tenant by domain', function () {
    $tenant = Tenant::query()->create([
        'name' => 'My Tenant',
        'domain' => 'my-tenant.veloquent.test',
        'database' => 'tenant_db_cache_test_1',
    ]);

    $request = Request::create('http://my-tenant.veloquent.test');

    $finder = new CachedDomainTenantFinder;

    expect(Cache::has('tenant_domain_my-tenant.veloquent.test'))->toBeFalse();

    $foundTenant = $finder->findForRequest($request);

    expect($foundTenant)->not->toBeNull()
        ->and($foundTenant->id)->toBe($tenant->id);

    expect(Cache::has('tenant_domain_my-tenant.veloquent.test'))->toBeTrue();
});

it('clears cache when tenant domain is updated', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Old Tenant',
        'domain' => 'old-domain.veloquent.test',
        'database' => 'tenant_db_cache_test_2',
    ]);

    $request = Request::create('http://old-domain.veloquent.test');
    $finder = new CachedDomainTenantFinder;
    $finder->findForRequest($request);

    expect(Cache::has('tenant_domain_old-domain.veloquent.test'))->toBeTrue();

    $tenant->update(['domain' => 'new-domain.veloquent.test']);

    expect(Cache::has('tenant_domain_old-domain.veloquent.test'))->toBeFalse();
    expect(Cache::has('tenant_domain_new-domain.veloquent.test'))->toBeFalse();
});

it('clears cache when tenant is deleted', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Del Tenant',
        'domain' => 'del-domain.veloquent.test',
        'database' => 'tenant_db_cache_test_3',
    ]);

    $request = Request::create('http://del-domain.veloquent.test');
    $finder = new CachedDomainTenantFinder;
    $finder->findForRequest($request);

    expect(Cache::has('tenant_domain_del-domain.veloquent.test'))->toBeTrue();

    $tenant->delete();

    expect(Cache::has('tenant_domain_del-domain.veloquent.test'))->toBeFalse();
});
