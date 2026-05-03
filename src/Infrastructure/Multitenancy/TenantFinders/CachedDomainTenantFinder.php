<?php

namespace Veloquent\Core\Infrastructure\Multitenancy\TenantFinders;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class CachedDomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        $host = $request->getHost();

        $tenantId = Cache::rememberForever("tenant_id_domain_{$host}", function () use ($host) {
            return app(IsTenant::class)::whereDomain($host)->value('id');
        });

        if (! $tenantId) {
            return null;
        }

        return app(IsTenant::class)::find($tenantId);
    }
}
