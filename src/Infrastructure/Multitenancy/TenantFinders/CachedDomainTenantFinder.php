<?php

namespace Veloquent\Core\Infrastructure\Multitenancy\TenantFinders;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Veloquent\Core\Infrastructure\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class CachedDomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        $host = $request->getHost();

        return Tenant::findByDomainCached($host);
    }
}
