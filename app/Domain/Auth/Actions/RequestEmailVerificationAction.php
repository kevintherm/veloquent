<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Services\OtpService;
use App\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RequestEmailVerificationAction
{
    public function __construct(private OtpService $otpService) {}

    public function execute(Collection $collection, Record $user): void
    {

        if ($collection->type !== CollectionType::Auth) {
            throw new AuthorizationException('This collection does not support authentication.');
        }

        if (! $user || $user->collection?->id !== $collection->id) {
            throw new AuthenticationException('User not authenticated.');
        }

        if ($user->hasAttribute('verified') && $user->getAttribute('verified') == true) {
            throw new BadRequestHttpException('User email is already verified.');
        }

        $this->otpService->issue($user, OtpAction::EmailVerification, $collection);
    }
}
