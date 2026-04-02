<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Services\OtpService;
use App\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class RequestEmailChangeAction
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

        $emailAlreadyExists = Record::of($collection)
            ->where('email', $payload['new_email'])
            ->exists();

        if ($emailAlreadyExists) {
            throw new UnprocessableEntityHttpException('The provided email address is already in use.');
        }

        $this->otpService->issueToAddress($user, OtpAction::EmailChange, $collection, $payload['new_email']);
    }
}
