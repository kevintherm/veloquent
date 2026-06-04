<?php

namespace Veloquent\Core\Domain\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Veloquent\Core\Domain\Hooks\Contracts\HookRunner;
use Illuminate\Auth\AuthenticationException;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\Otp\Enums\OtpAction;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Veloquent\Core\Domain\Otp\Services\OtpService;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;

class ConfirmEmailVerificationAction
{
    public function __construct(
        private OtpService $otpService,
        private HookRunner $hookRunner,
    ) {}

    public function execute(Collection $collection, Record $user, array $payload): void
    {
        if ($collection->type !== CollectionType::Auth) {
            throw new AuthorizationException('This collection does not support authentication.');
        }

        if (! $user || $user->collection?->id !== $collection->id) {
            throw new AuthenticationException('User not authenticated.');
        }

        $payload = DB::transaction(function () use ($collection, $user, $payload) {
            $payload = $this->hookRunner->run(new HookPayload(
                event: 'auth.email_verifying',
                collection: $collection,
                record: $user,
                data: $payload,
                request: request(),
            ))->data;

            $this->otpService->consume(
                $payload['token'],
                OtpAction::EmailVerification,
                $collection,
                (string) $user->id,
            );

            Record::of($collection)
                ->where('id', $user->id)
                ->update(['verified' => true]);

            return $payload;
        });

        $this->hookRunner->run(new HookPayload(
            event: 'auth.email_verified',
            collection: $collection,
            record: $user,
            data: $payload,
            request: request(),
        ));
    }
}
