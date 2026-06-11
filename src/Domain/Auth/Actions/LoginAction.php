<?php

namespace Veloquent\Core\Domain\Auth\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\AuthenticationException;
use Veloquent\Core\Domain\Auth\Models\AuthToken;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Veloquent\Core\Domain\Hooks\Contracts\HookRunner;
use Veloquent\Core\Domain\Auth\Jobs\SendLoginEmailJob;
use Veloquent\Core\Domain\Auth\ValueObjects\TokenData;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\Auth\Contracts\TokenAuthService;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Auth\ValueObjects\RequestMetadata;

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
    public function execute(
        Collection $collection,
        array $payload,
        ?RequestMetadata $metadata = null,
        ?Request $request = null
    ): TokenData {
        $result = null;
        DB::transaction(function () use (&$result, $collection, $payload, $metadata, $request) {
            $payload = $this->hookRunner->run(new HookPayload(
                event: 'auth.logging_in',
                collection: $collection,
                data: $payload,
                request: $request,
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

            $fingerprint = $metadata?->fingerprint;

            $hasActiveToken = $fingerprint ? AuthToken::query()
                ->forRecord($collection->id, (string) $user->id)
                ->active()
                ->where('fingerprint', $fingerprint)
                ->exists() : false;

            $isNewSource = $fingerprint ? ! $hasActiveToken : false;

            $tokenData = $this->tokenService->generateToken($user, null, $metadata);

            Log::info('USER_LOGIN', [
                'user_id' => (string) $user->id,
                'collection' => $collection->name,
                'ip_address' => $metadata?->ipAddress,
                'user_agent' => $metadata?->userAgent,
                'is_new_source' => $isNewSource,
            ]);

            if ($isNewSource && $user->email) {
                dispatch(new SendLoginEmailJob(
                    $user->email,
                    $collection,
                    now()->toDateTimeString(),
                    $metadata?->ipAddress ?? ''
                ));
            }

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
            request: $request,
        ));

        return $result['tokenData'];
    }
}
