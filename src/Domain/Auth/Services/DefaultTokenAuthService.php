<?php

namespace Veloquent\Core\Domain\Auth\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Veloquent\Core\Support\Models\Tenant;
use Veloquent\Core\Domain\Auth\Models\AuthToken;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Auth\ValueObjects\TokenData;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Auth\Contracts\TokenAuthService;
use Veloquent\Core\Domain\Auth\ValueObjects\RequestMetadata;

class DefaultTokenAuthService implements TokenAuthService
{
    public function extractTokenFromRequest(Request $request): ?string
    {
        return $request->bearerToken() ?? $request->input('token');
    }

    public function generateToken(Record $user, ?int $expiresIn = null, ?RequestMetadata $metadata = null): TokenData
    {
        /**
         * @var Collection|null
         */
        $collection = $user->collection;

        if (! $collection) {
            throw new \RuntimeException('Cannot issue token without collection context.');
        }

        $expiresIn = $expiresIn ?? (int) config('velo.auth.token.expiration', 3600);
        $token = bin2hex(random_bytes(32));

        AuthToken::create([
            'collection_name' => $collection->name,
            'collection_id' => $collection->id,
            'record_id' => (string) $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addSeconds($expiresIn),
            'ip_address' => $metadata?->ipAddress,
            'user_agent' => $metadata?->userAgent,
            'fingerprint' => $metadata?->fingerprint,
        ]);

        $this->enforceMaxTokens($user);

        return new TokenData(
            token: $token,
            expires_in: $expiresIn,
            collection_name: (string) $collection->name,
            collection_id: (string) $collection->id,
            record_id: (string) $user->id,
        );
    }

    public function authenticate(string $token): ?Record
    {
        if (! app(Tenant::class)::current()) {
            return null;
        }

        $hashedToken = hash('sha256', $token);
        $authData = $this->resolveAuthData($hashedToken);

        if (! $authData) {
            return null;
        }

        $user = $authData['user'];
        $collection = $authData['collection'];
        $expiresAt = $authData['expires_at'];
        $expiresIn = $authData['expires_in'];
        $fromCache = $authData['from_cache'];

        $secondsRemaining = now()->diffInSeconds($expiresAt, false);
        if ($secondsRemaining <= 0) {
            Cache::forget("velo:auth:{$hashedToken}");
            return null;
        }

        $slide = config('velo.auth.token.slide', true);
        $slideRatio = (float) config('velo.auth.token.slide_ratio', 0.5);

        if ($slide && $secondsRemaining < ($expiresIn * $slideRatio)) {
            $newExpiresAt = now()->addSeconds($expiresIn);
            $this->registerTerminatingUpdate($hashedToken, $newExpiresAt, $collection->id, $user->getAttributes(), $expiresIn);
        } else {
            if (! $fromCache) {
                $this->registerTerminatingUpdate($hashedToken, null, $collection->id, $user->getAttributes(), $expiresIn, $expiresAt->timestamp, $secondsRemaining);
            } else {
                $this->registerTerminatingUpdate($hashedToken);
            }
        }

        return $user;
    }

    protected function resolveAuthData(string $hashedToken): ?array
    {
        $cacheKey = "velo:auth:{$hashedToken}";
        $cachedData = Cache::get($cacheKey);

        if (is_array($cachedData)) {
            $collectionId = $cachedData['collection_id'] ?? null;
            $attributes = $cachedData['attributes'] ?? null;
            $expiresAtTimestamp = $cachedData['expires_at'] ?? null;
            $expiresIn = $cachedData['expires_in'] ?? null;

            if ($expiresAtTimestamp && now()->timestamp >= $expiresAtTimestamp) {
                Cache::forget($cacheKey);
                return null;
            }

            if ($collectionId && is_array($attributes) && $expiresAtTimestamp && $expiresIn) {
                $collection = Collection::findByIdCached($collectionId);
                if ($collection) {
                    $user = Record::of($collection)->newFromBuilder($attributes);
                    return [
                        'user' => $user,
                        'collection' => $collection,
                        'expires_at' => Carbon::createFromTimestamp($expiresAtTimestamp),
                        'expires_in' => $expiresIn,
                        'from_cache' => true,
                    ];
                }
            }
        }

        $authToken = AuthToken::query()
            ->where('token_hash', $hashedToken)
            ->active()
            ->first();

        if (! $authToken) {
            return null;
        }

        $collection = Collection::findByIdCached($authToken->collection_id);
        if (! $collection) {
            return null;
        }

        $user = Record::of($collection)
            ->where('id', $authToken->record_id)
            ->first();

        if (! $user) {
            return null;
        }

        $user->setAttribute('collection_id', $collection->id);
        $user->setAttribute('collection_name', $collection->name);

        $expiresAt = $authToken->expires_at;
        if (! $expiresAt) {
            return null;
        }

        $expiresIn = $authToken->created_at 
            ? $authToken->expires_at->diffInSeconds($authToken->created_at) 
            : (int) config('velo.auth.token.expiration', 3600);
        if ($expiresIn <= 0) {
            $expiresIn = (int) config('velo.auth.token.expiration', 3600);
        }

        return [
            'user' => $user,
            'collection' => $collection,
            'expires_at' => $expiresAt,
            'expires_in' => $expiresIn,
            'from_cache' => false,
        ];
    }

    protected function registerTerminatingUpdate(
        string $hashedToken,
        ?Carbon $newExpiresAt = null,
        ?string $collectionId = null,
        ?array $attributes = null,
        ?int $expiresIn = null,
        ?int $expiresAtTimestamp = null,
        ?int $secondsRemaining = null
    ): void {
        $executed = false;
        $cacheKey = "velo:auth:{$hashedToken}";

        app()->terminating(function () use (
            &$executed,
            $hashedToken,
            $newExpiresAt,
            $collectionId,
            $attributes,
            $expiresIn,
            $expiresAtTimestamp,
            $secondsRemaining,
            $cacheKey
        ) {
            if ($executed) {
                return;
            }
            $executed = true;

            try {
                if ($newExpiresAt) {
                    $updated = AuthToken::where('token_hash', $hashedToken)
                        ->whereNull('revoked_at')
                        ->update([
                            'expires_at' => $newExpiresAt,
                            'last_used_at' => now(),
                        ]);

                    if ($updated && $collectionId && $attributes && $expiresIn) {
                        Cache::put($cacheKey, [
                            'collection_id' => $collectionId,
                            'attributes' => $attributes,
                            'expires_at' => $newExpiresAt->timestamp,
                            'expires_in' => $expiresIn,
                        ], $expiresIn);
                    } else {
                        Cache::forget($cacheKey);
                    }
                } else {
                    $updated = AuthToken::where('token_hash', $hashedToken)
                        ->whereNull('revoked_at')
                        ->update(['last_used_at' => now()]);

                    if ($updated) {
                        if ($collectionId && $attributes && $expiresAtTimestamp && $expiresIn && $secondsRemaining) {
                            Cache::put($cacheKey, [
                                'collection_id' => $collectionId,
                                'attributes' => $attributes,
                                'expires_at' => $expiresAtTimestamp,
                                'expires_in' => $expiresIn,
                            ], min($secondsRemaining, (int) config('velo.auth.token.expiration', 3600)));
                        }
                    } else {
                        Cache::forget($cacheKey);
                    }
                }
            } catch (\Exception $e) {}
        });
    }



    public function revokeToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);

        Cache::forget("velo:auth:{$hashedToken}");

        return (bool) AuthToken::where('token_hash', $hashedToken)->update(['revoked_at' => now()]);
    }

    public function revokeRecordTokens(string $collectionId, string $recordId, ?string $tokenHash = null): bool
    {
        $query = AuthToken::query()->forRecord($collectionId, $recordId);

        if ($tokenHash) {
            $query->where('token_hash', $tokenHash);
            Cache::forget("velo:auth:{$tokenHash}");
        } else {
            $hashes = AuthToken::query()
                ->forRecord($collectionId, $recordId)
                ->pluck('token_hash')
                ->toArray();
            foreach ($hashes as $hash) {
                Cache::forget("velo:auth:{$hash}");
            }
        }

        return (bool) $query->update(['revoked_at' => now()]);
    }

    protected function enforceMaxTokens(Record $user): void
    {
        $maxTokens = (int) config('velo.auth.token.max_active_tokens', 0);

        if ($maxTokens <= 0 || ! $user->collection) {
            return;
        }

        $tokens = AuthToken::query()
            ->forRecord($user->collection->id, (string) $user->id)
            ->active()
            ->orderBy('id', 'desc')
            ->get();

        if ($tokens->count() <= $maxTokens) {
            return;
        }

        $tokensToDelete = $tokens->slice($maxTokens);

        foreach ($tokensToDelete as $t) {
            Cache::forget("velo:auth:{$t->token_hash}");
        }

        AuthToken::whereIn('id', $tokensToDelete->pluck('id'))->update(['revoked_at' => now()]);
    }
}
