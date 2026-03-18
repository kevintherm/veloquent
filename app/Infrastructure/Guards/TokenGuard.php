<?php

namespace App\Infrastructure\Guards;

use App\Domain\Auth\Services\TokenAuthService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class TokenGuard implements Guard
{
    use GuardHelpers;

    public function __construct(
        private TokenAuthService $tokenService,
        private Request $request,
    ) {}

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if (! $token) {
            return null;
        }

        return $this->user = $this->tokenService->authenticate($token);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function setRequest(Request $request): static
    {
        $this->request = $request;
        $this->forgetUser();

        return $this;
    }

    public function logout(): void
    {
        $this->forgetUser();
    }
}
