<?php

namespace App\Domain\Auth\Services;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Exceptions\JwtException;
use App\Models\RefreshToken;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;

class JwtAuthService
{
    private string $secret;

    private string $algorithm;

    private int $ttl;

    public function __construct()
    {
        $this->secret = config('jwt.secret');
        $this->algorithm = config('jwt.algorithm', 'HS256');
        $this->ttl = config('jwt.ttl', 60);
    }

    /**
     * Generate a JWT for a Record user scoped to a collection.
     *
     * @return array{token: string, refresh_token: string, expires_in: int, collection_name: string}
     */
    public function generateToken(Record $user): array
    {
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + ($this->ttl * 60),
            'collection_name' => $user->collection->name,
            'token_key' => $user->token_key,
        ];

        $token = JWT::encode($payload, $this->secret, $this->algorithm);

        $refreshTTL = config('auth.refresh_token_ttl', 30); // days
        $refresh = bin2hex(random_bytes(32));
        $refreshToken = RefreshToken::create([
            'collection_name' => $user->collection->name,
            'record_id' => $user->id,
            'token' => hash('sha256', $refresh),
            'expires_at' => now()->addDays($refreshTTL),
        ]);

        return [
            'token' => $token,
            'refresh_token' => $refresh,
            'expires_in' => $this->ttl * 60,
            'refresh_token_expires_in' => $refreshToken->expires_at->timestamp,
            'collection_name' => $user->collection->name,
        ];
    }

    /**
     * Validate and decode a JWT, returning the payload as an array.
     *
     * @return array<string, mixed>
     */
    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

            return (array) $decoded;
        } catch (ExpiredException) {
            throw new JwtException('Token has expired');
        } catch (BeforeValidException) {
            throw new JwtException('Token is not valid yet');
        } catch (SignatureInvalidException) {
            throw new JwtException('Token signature is invalid');
        } catch (\Exception) {
            throw new JwtException('Invalid token');
        }
    }

    /**
     * Authenticate a token and return the corresponding Record.
     * Only works for auth-type collections.
     */
    public function authenticate(string $token): ?Record
    {
        $payload = $this->validateToken($token);

        if (! isset($payload['sub'], $payload['collection_name'], $payload['token_key'])) {
            return null;
        }

        $collection = Collection::where('name', $payload['collection_name'])
            ->where('type', CollectionType::Auth)
            ->first();

        if (! $collection) {
            return null;
        }

        return Record::forCollection($collection)->where('token_key', $payload['token_key'])->where('id', $payload['sub'])->first();
    }

    /**
     * Refresh a token by issuing a new one for the same user.
     *
     * @return array{token: string, expires_in: int, collection_name: string}
     */
    public function refresh(string $token): array
    {
        $hashedToken = hash('sha256', $token);

        $refreshToken = RefreshToken::where('token', $hashedToken)
            ->where('expires_at', '>', now())
            ->first();

        if (! $refreshToken) {
            throw new JwtException('Invalid or expired refresh token');
        }

        $collection = Collection::where('name', $refreshToken->collection_name)
            ->where('type', CollectionType::Auth)
            ->firstOrFail();

        $user = Record::forCollection($collection)->findOrFail($refreshToken->record_id);

        $refreshToken->delete();

        return $this->generateToken($user);
    }

    /**
     * Extract the bearer token from a request.
     */
    public function extractTokenFromRequest(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $request->input('token');
    }
}
