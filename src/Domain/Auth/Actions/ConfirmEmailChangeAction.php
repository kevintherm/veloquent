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

class ConfirmEmailChangeAction
{
    public function __construct(
        private OtpService $otpService,
        private HookRunner $hookRunner,
    ) {}

    public function execute(Collection $collection, Record $user, array $payload): array
    {
        $token = $payload['token'] ?? null;
        $newEmail = $payload['new_email'] ?? null;

        if ($collection->type !== CollectionType::Auth) {
            throw new AuthorizationException('This collection does not support authentication.');
        }

        if (! $user || $user->collection?->id !== $collection->id) {
            throw new AuthenticationException('User not authenticated.');
        }

        $payload = DB::transaction(function () use ($collection, $user, $payload, $newEmail, $token) {
            $payload = $this->hookRunner->run(new HookPayload(
                event: 'auth.email_changing',
                collection: $collection,
                record: $user,
                data: $payload,
                request: request(),
            ))->data;

            $newEmail = $payload['new_email'] ?? $newEmail;
            $token = $payload['token'] ?? $token;

            $this->otpService->consume(
                $token,
                OtpAction::EmailChange,
                $collection,
                (string) $user->id,
            );

            Record::of($collection)
                ->where('id', $user->id)
                ->update(['email' => $newEmail]);

            return $payload;
        });

        $this->hookRunner->run(new HookPayload(
            event: 'auth.email_changed',
            collection: $collection,
            record: $user,
            data: $payload,
            request: request(),
        ));

        return ['status' => 'ok'];
    }
}
