<?php

namespace Veloquent\Core\Domain\Auth\Services;

use Veloquent\Core\Domain\Auth\Models\AuthToken;
use Veloquent\Core\Domain\Auth\ValueObjects\TokenData;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Infrastructure\Models\Tenant;
use Illuminate\Http\Request;

class TokenAuthService
{
    public function extractTokenFromRequest(Request $request): ?string
    {
        return $request->bearerToken() ?? $request->input('token');
    }

    public function generateToken(Record $user, ?int $expiresIn = null): TokenData
    {
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

        try {
            $authToken = AuthToken::query()
                ->where('token_hash', $hashedToken)
                ->active()
                ->first();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'no such table: auth_tokens')) {
                return null;
            }
            throw $e;
        }

        if (! $authToken) {
            return null;
        }

        $collection = Collection::find($authToken->collection_id);

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

    public function revokeToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);

        return (bool) AuthToken::where('token_hash', $hashedToken)->delete();
    }

    public function revokeRecordTokens(string $collectionId, string $recordId, ?string $tokenHash = null): bool
    {
        $query = AuthToken::query()->forRecord($collectionId, $recordId);

        if ($tokenHash) {
            $query->where('token_hash', $tokenHash);
        }

        return (bool) $query->delete();
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

        AuthToken::whereIn('id', $tokens->slice($maxTokens)->pluck('id'))->delete();
    }
}
