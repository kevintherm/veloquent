<?php

namespace Veloquent\Core\Domain\Auth\ValueObjects;

use Illuminate\Http\Request;
use Veloquent\Core\Domain\Auth\Support\Fingerprint;

readonly class RequestMetadata
{
    public function __construct(
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $fingerprint = null,
    ) {}

    /**
     * Create a new instance from an HTTP Request.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            fingerprint: Fingerprint::generate($request)
        );
    }

    /**
     * Create an empty instance.
     */
    public static function empty(): self
    {
        return new self();
    }
}
