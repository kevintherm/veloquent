<?php

namespace Veloquent\Core\Support\Http\Middleware;

use Veloquent\Core\Domain\Auth\Contracts\TokenAuthService;
use Veloquent\Core\Support\Guards\TokenGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TokenAuthMiddleware
{
    public function __construct(private TokenAuthService $tokenService) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::guard('api')->user();

        return $next($request);
    }
}
