<?php

namespace Veloquent\Core\Support\Multitenancy\TenantFinders;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Veloquent\Core\Support\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class CachedDomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        if (! config('velo.tenancy_enabled', true)) {
            return Tenant::getVirtualTenant();
        }

        $host = $request->getHost();

        return Tenant::findByDomainCached($host);
    }
}
