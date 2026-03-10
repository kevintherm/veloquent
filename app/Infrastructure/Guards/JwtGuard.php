<?php

namespace App\Infrastructure\Guards;

use App\Domain\Records\Models\Record;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class JwtGuard implements Guard
{
    protected Authenticatable|Record|null $user = null;

    public function check(): bool
    {
        return ! is_null($this->user);
    }

    public function guest(): bool
    {
        return is_null($this->user);
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function hasUser(): bool
    {
        return ! is_null($this->user);
    }

    public function logout(): void
    {
        if (! $this->check()) {
            return;
        }

        $this->user->token_key = Str::random(64);
        $this->user->save();

        Event::dispatch(new Logout('api', $this->user));

        $this->user = null;
    }
}
