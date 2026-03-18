<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Services\TokenAuthService;
use App\Infrastructure\Guards\TokenGuard;
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
        $guard = Auth::guard('api');

        if ($guard instanceof TokenGuard) {
            $guard->setRequest($request);
        }

        $token = $this->tokenService->extractTokenFromRequest($request);

        if (! $token) {
            return $next($request);
        }

        if ($user = $this->tokenService->authenticate($token)) {
            $guard->setUser($user);
        }

        return $next($request);
    }
}
