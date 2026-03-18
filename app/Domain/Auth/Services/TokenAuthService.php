<?php

namespace App\Domain\Auth\Services;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Models\AuthToken;
use Illuminate\Http\Request;

class TokenAuthService
{
    /**
     * Issue a persisted opaque bearer token for an auth-collection record.
     *
     * @return array{token: string, expires_in: int, collection_name: string}
     */
    public function generateToken(Record $user): array
    {
        $ttlSeconds = $this->ttlSeconds();
        $collectionName = $user->collection?->name;
        $collectionId = $user->collection?->id;

        if ($collectionName === null || $collectionId === null) {
            throw new \RuntimeException('Cannot issue token without collection context.');
        }

        $this->enforceMaxActiveTokens($collectionId, $user->id);

        $token = bin2hex(random_bytes(32));

        AuthToken::create([
            'collection_name' => $collectionName,
            'collection_id' => $collectionId,
            'record_id' => (string) $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);

        return [
            'token' => $token,
            'expires_in' => $ttlSeconds,
            'collection_name' => $collectionName,
        ];
    }

    public function authenticate(string $token): ?Record
    {
        $hashedToken = hash('sha256', $token);

        $authToken = AuthToken::query()
            ->where('token_hash', $hashedToken)
            ->active()
            ->first();

        if (! $authToken) {
            return null;
        }

        $collection = Collection::query()
            ->where('id', $authToken->collection_id)
            ->where('type', CollectionType::Auth)
            ->first();

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

        $authToken->update(['last_used_at' => now()]);

        return $user;
    }

    public function revokeRecordTokens(string $collectionId, string $recordId): int
    {
        return AuthToken::query()
            ->forRecord($collectionId, $recordId)
            ->delete();
    }

    public function extractTokenFromRequest(Request $request): ?string
    {
        return $request->bearerToken() ?: $request->input('token');
    }

    private function enforceMaxActiveTokens(string $collectionId, string $recordId): void
    {
        $maxActiveTokens = (int) config('token_auth.max_active_tokens', 0);

        if ($maxActiveTokens <= 0) {
            return;
        }

        $keepExisting = max(0, $maxActiveTokens - 1);

        if ($keepExisting === 0) {
            AuthToken::query()
                ->forRecord($collectionId, $recordId)
                ->active()
                ->delete();

            return;
        }

        $idsToKeep = AuthToken::query()
            ->forRecord($collectionId, $recordId)
            ->active()
            ->latest('created_at')
            ->take($keepExisting)
            ->pluck('id');

        if ($idsToKeep->isEmpty()) {
            AuthToken::query()
                ->forRecord($collectionId, $recordId)
                ->active()
                ->delete();

            return;
        }

        $tokenIdsToPrune = AuthToken::query()
            ->forRecord($collectionId, $recordId)
            ->active()
            ->whereNotIn('id', $idsToKeep)
            ->pluck('id');

        if ($tokenIdsToPrune->isNotEmpty()) {
            AuthToken::query()->whereIn('id', $tokenIdsToPrune)->delete();
        }
    }

    private function ttlSeconds(): int
    {
        $ttlMinutes = (int) config('token_auth.ttl', 60);

        return max(1, $ttlMinutes) * 60;
    }
}
