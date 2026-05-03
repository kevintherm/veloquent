<?php

namespace Veloquent\Core\Http\Middleware;

use Veloquent\Core\Domain\Auth\Services\TokenAuthService;
use Veloquent\Core\Infrastructure\Guards\TokenGuard;
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
        $token = $this->tokenService->extractTokenFromRequest($request);
        $user = $token ? $this->tokenService->authenticate($token) : null;
        
        if ($user) {
            Auth::guard('api')->setUser($user);
        }

        return $next($request);
    }
}
