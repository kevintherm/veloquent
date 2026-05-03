<?php

namespace Veloquent\Core\Domain\Auth\Actions;

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Otp\Enums\OtpAction;
use Veloquent\Core\Domain\Otp\Services\OtpService;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;

class ConfirmEmailVerificationAction
{
    public function __construct(private OtpService $otpService) {}

    public function execute(Collection $collection, Record $user, array $payload): void
    {
        if ($collection->type !== CollectionType::Auth) {
            throw new AuthorizationException('This collection does not support authentication.');
        }

        if (! $user || $user->collection?->id !== $collection->id) {
            throw new AuthenticationException('User not authenticated.');
        }

        $this->otpService->consume(
            $payload['token'],
            OtpAction::EmailVerification,
            $collection,
            (string) $user->id,
        );

        Record::of($collection)
            ->where('id', $user->id)
            ->update(['verified' => true]);
    }
}
