<?php

namespace Veloquent\Core\Domain\Auth\Contracts;

use Illuminate\Http\Request;
use Veloquent\Core\Domain\Auth\ValueObjects\TokenData;
use Veloquent\Core\Domain\Records\Models\Record;

interface TokenAuthService
{
    /**
     * Extract bearer token or request input token from the request.
     */
    public function extractTokenFromRequest(Request $request): ?string;

    /**
     * Generate an authentication token for the given user record.
     */
    public function generateToken(Record $user, ?int $expiresIn = null): TokenData;

    /**
     * Authenticate and resolve a user record by their token.
     */
    public function authenticate(string $token): ?Record;

    /**
     * Revoke a single token.
     */
    public function revokeToken(string $token): bool;

    /**
     * Revoke all tokens for a specific record.
     */
    public function revokeRecordTokens(string $collectionId, string $recordId, ?string $tokenHash = null): bool;
}
