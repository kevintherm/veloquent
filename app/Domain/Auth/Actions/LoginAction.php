<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Auth\ValueObjects\TokenData;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    public function __construct(private TokenAuthService $tokenService) {}

    /**
     * Handle standard login for an auth collection and return the token data.
     * Throws HTTP exceptions on invalid collection or credentials.
     */
    public function execute(Collection $collection, array $payload): TokenData
    {
        $identity = $payload['identity'] ?? null;
        $password = $payload['password'] ?? null;

        if ($collection->type !== CollectionType::Auth) {
            throw new AuthorizationException('This collection does not support authentication.');
        }

        if ($collection->is_system === false && data_get($collection->options, 'auth_methods.standard.enabled') !== true) {
            throw new AuthorizationException('Standard authentication is not enabled for this collection.');
        }

        $identityFields = data_get($collection->options, 'auth_methods.standard.identity_fields', ['email']);
        $identityFields = is_array($identityFields) ? $identityFields : ['email'];

        $user = Record::of($collection)->where(function ($query) use ($identityFields, $identity) {
            foreach ($identityFields as $field) {
                $query->orWhere($field, $identity);
            }
        })->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $this->tokenService->generateToken($user);
    }
}
