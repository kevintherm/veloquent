<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Services\JwtAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function __construct(private JwtAuthService $jwtService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->jwtService->extractTokenFromRequest($request);

        if (! $token) {
            return $next($request);
        }

        if ($user = $this->jwtService->authenticate($token)) {
            Auth::setUser($user);
        }

        return $next($request);
    }
}
