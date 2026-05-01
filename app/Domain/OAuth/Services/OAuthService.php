<?php

namespace App\Domain\OAuth\Services;

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Auth\ValueObjects\TokenData;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\OAuth\Factory\OAuthDriverFactory;
use App\Domain\OAuth\Models\OAuthAccount;
use App\Domain\OAuth\Models\OAuthProvider;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Socialite\Two\User as SocialiteUser;

class OAuthService
{
    public function __construct(
        private OAuthDriverFactory $driverFactory,
        private TokenAuthService $tokenService,
    ) {}

    /**
     * Build the redirect URL for the given provider.
     */
    public function getRedirectUrl(Collection $collection, string $provider): string
    {
        $this->ensureOAuthEnabled($collection);

        $driver = $this->driverFactory->make($collection->id, $provider);

        $state = Str::random(40);
        Cache::put("oauth_state:{$state}", [
            'provider' => $provider,
            'collection' => $collection->id,
        ], now()->addSeconds(config('velo.oauth.state_ttl', 300)));

        return $driver->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * Handle the OAuth callback: exchange provider code for user, find-or-create account.
     * Returns a short-lived exchange code for the client to trade for a token.
     *
     * @return array{code: string}
     */
    public function handleCallback(string $state): array
    {
        $payload = Cache::pull("oauth_state:{$state}");

        if (! $payload) {
            throw new InvalidArgumentException('Invalid or expired state.');
        }

        $collectionId = $payload['collection'] ?? null;
        $provider = $payload['provider'] ?? null;

        $collection = Collection::findOrFail($collectionId);
        $this->ensureOAuthEnabled($collection);

        $lockKey = 'oauth_callback_lock:'.md5($state);
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            throw new InvalidArgumentException('Authentication already in progress.');
        }

        try {
            $driver = $this->driverFactory->make($collection->id, $provider);

            /** @var SocialiteUser $socialiteUser */
            $socialiteUser = $driver->stateless()->user();

            $record = DB::transaction(function () use ($collection, $provider, $socialiteUser) {
                return $this->findOrCreateRecord($collection, $provider, $socialiteUser);
            });

            $tokenData = $this->tokenService->generateToken($record);
            $exchangeCode = Str::random(60);
            Cache::put("oauth_exchange:{$exchangeCode}", $tokenData, now()->addSeconds(config('velo.oauth.exchange_ttl', 90)));

            $oauthProvider = OAuthProvider::query()
                ->where('collection_id', $collection->id)
                ->where('provider', $provider)
                ->first();

            return [
                'code' => $exchangeCode,
                'redirect_uri' => $oauthProvider?->redirect_uri,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Exchange a short-lived code for the final authentication token data.
     */
    public function exchangeCode(string $code): TokenData
    {
        $tokenData = Cache::pull("oauth_exchange:{$code}");

        if (! $tokenData instanceof TokenData) {
            throw new InvalidArgumentException('Invalid or expired exchange code.');
        }

        $collection = Collection::findOrFail($tokenData->collection_id);
        $record = Record::of($collection)->findOrFail($tokenData->record_id);

        return TokenData::fromArray([
            ...$tokenData->toArray(),
            'record' => $record,
        ]);
    }

    /**
     * Find an existing record linked via OAuthAccount, or create a new one.
     */
    private function findOrCreateRecord(Collection $collection, string $provider, SocialiteUser $socialiteUser): Record
    {
        $oauthAccount = OAuthAccount::query()
            ->forProvider($provider, $socialiteUser->getId(), $collection->id)
            ->first();

        if ($oauthAccount) {
            $record = Record::of($collection)
                ->where('id', $oauthAccount->record_id)
                ->first();

            if ($record) {
                return $record;
            }
        }

        $nameFieldCandidates = config('velo.oauth.name_field_candidates', []);
        $hasNameField = Arr::first(
            $collection->fields,
            fn ($field) => in_array($field['name'], $nameFieldCandidates) && ($field['type'] === CollectionFieldType::Text->value || $field['type'] === CollectionFieldType::LongText->value)
        );

        $email = $socialiteUser->getEmail();
        $record = null;

        if ($email) {
            $record = Record::of($collection)->where('email', $email)->first();
        }

        if (! $record) {
            $record = Record::of($collection);
            $record->forceFill([
                'email' => $email ?? "{$provider}_{$socialiteUser->getId()}@oauth.local",
                'password' => Hash::make(Str::random(16)),
                'email_visibility' => true,
                'verified' => (bool) $email,
                ...($hasNameField ? [$hasNameField['name'] => $socialiteUser->getName() ?? $socialiteUser->getNickname() ?? ''] : []),
            ]);

            $record->save();
        }

        OAuthAccount::updateOrCreate(
            [
                'provider' => $provider,
                'provider_user_id' => $socialiteUser->getId(),
                'collection_id' => $collection->id,
            ],
            [
                'record_id' => (string) $record->id,
                'email' => $email,
            ]
        );

        return $record;
    }

    private function ensureOAuthEnabled(Collection $collection): void
    {
        if ($collection->type !== CollectionType::Auth) {
            throw new InvalidArgumentException('This collection does not support authentication.');
        }

        if (data_get($collection->options, 'auth_methods.oauth.enabled') !== true) {
            throw new InvalidArgumentException('OAuth is not enabled for this collection.');
        }
    }
}
