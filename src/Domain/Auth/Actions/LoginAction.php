<?php

namespace Veloquent\Core\Domain\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Veloquent\Core\Domain\Hooks\Contracts\HookRunner;
use Illuminate\Auth\AuthenticationException;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Veloquent\Core\Domain\Auth\ValueObjects\TokenData;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Auth\Contracts\TokenAuthService;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;

class LoginAction
{
    public function __construct(
        private TokenAuthService $tokenService,
        private HookRunner $hookRunner,
    ) {}

    /**
     * Handle standard login for an auth collection and return the token data.
     * Throws HTTP exceptions on invalid collection or credentials.
     */
    public function execute(Collection $collection, array $payload): TokenData
    {
        $result = null;
        DB::transaction(function () use (&$result, $collection, $payload) {
            $payload = $this->hookRunner->run(new HookPayload(
                event: 'auth.logging_in',
                collection: $collection,
                data: $payload,
                request: request(),
            ))->data;

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

            /** @var Record|null */
            $user = Record::of($collection)->where(function ($query) use ($identityFields, $identity) {
                foreach ($identityFields as $field) {
                    $query->orWhere($field, $identity);
                }
            })->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                throw new AuthenticationException('Invalid credentials.');
            }

            $tokenData = $this->tokenService->generateToken($user);

            $result = [
                'tokenData' => $tokenData,
                'user' => $user,
                'payload' => $payload,
            ];
        });

        $this->hookRunner->run(new HookPayload(
            event: 'auth.logged_in',
            collection: $collection,
            record: $result['user'],
            data: $result['payload'],
            request: request(),
        ));

        return $result['tokenData'];
    }
}
