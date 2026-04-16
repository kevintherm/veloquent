<?php

namespace App\Infrastructure\Multitenancy\TenantFinders;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class CachedDomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        $host = $request->getHost();

        return Cache::rememberForever("tenant_domain_{$host}", function () use ($host) {
            return app(IsTenant::class)::whereDomain($host)->first();
        });
    }
}
