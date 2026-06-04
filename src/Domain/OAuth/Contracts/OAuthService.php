<?php

namespace Veloquent\Core\Domain\OAuth\Contracts;

use Veloquent\Core\Domain\Auth\ValueObjects\TokenData;
use Veloquent\Core\Domain\Collections\Models\Collection;

interface OAuthService
{
    /**
     * Build the redirect URL for the given provider.
     */
    public function getRedirectUrl(Collection $collection, string $provider): string;

    /**
     * Handle the OAuth callback: exchange provider code for user, find-or-create account.
     */
    public function handleCallback(string $state, bool $isNative = false): array;

    /**
     * Exchange a short-lived code for the final authentication token data.
     */
    public function exchangeCode(string $code): TokenData;
}
