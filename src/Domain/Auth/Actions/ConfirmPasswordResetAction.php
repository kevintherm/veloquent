<?php

namespace Veloquent\Core\Domain\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Veloquent\Core\Domain\Hooks\HookRunner;
use Illuminate\Auth\AuthenticationException;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\Otp\Enums\OtpAction;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Veloquent\Core\Domain\Otp\Services\OtpService;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Auth\Services\TokenAuthService;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;

class ConfirmPasswordResetAction
{
    public function __construct(
        private OtpService $otpService,
        private TokenAuthService $tokenService,
        private HookRunner $hookRunner,
    ) {}

    /**
     * Verify the OTP and reset the user's password inside a transaction.
     */
    public function execute(Collection $collection, array $payload): array
    {
        $newPassword = $payload['new_password'] ?? null;

        if ($collection->type !== CollectionType::Auth) {
            throw new AuthorizationException('This collection does not support authentication.');
        }

        $user = Record::of($collection)->where('email', $payload['email'])->first();

        if (! $user) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $payload = DB::transaction(function () use ($payload, $collection, $user, $newPassword) {
            $payload = $this->hookRunner->run(new HookPayload(
                event: 'auth.password_resetting',
                collection: $collection,
                record: $user,
                data: $payload,
                request: request(),
            ))->data;

            $newPassword = $payload['new_password'] ?? $newPassword;

            $this->otpService->consume(
                $payload['token'],
                OtpAction::PasswordReset,
                $collection,
                (string) $user->id,
            );

            Record::of($collection)
                ->where('id', $user->id)
                ->update(['password' => Hash::make($newPassword)]);

            $this->tokenService->revokeRecordTokens($collection->id, (string) $user->id);

            return $payload;
        });

        $this->hookRunner->run(new HookPayload(
            event: 'auth.password_reset',
            collection: $collection,
            record: $user,
            data: $payload,
            request: request(),
        ));

        return ['status' => 'ok'];
    }
}
