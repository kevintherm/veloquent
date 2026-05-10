<?php

namespace Veloquent\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Veloquent\Core\Infrastructure\Models\Tenant;
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
        if (! Tenant::current()) {
            abort(404, 'Tenant not found');
        }

        return $next($request);
    }
}
