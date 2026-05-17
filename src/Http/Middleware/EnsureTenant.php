<?php

namespace Veloquent\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Veloquent\Core\Support\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('velo.tenancy_enabled', true)) {
            if (! Tenant::current()) {
                Tenant::getVirtualTenant()->makeCurrent();
            }

            return $next($request);
        }

        if (! Tenant::current()) {
            abort(404, 'Tenant not found');
        }

        return $next($request);
    }
}
