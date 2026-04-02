<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Services\OtpService;
use App\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;

class ConfirmEmailChangeAction
{
    public function __construct(private OtpService $otpService) {}

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

        return DB::transaction(function () use ($collection, $user, $newEmail, $token) {
            $this->otpService->consume(
                $token,
                OtpAction::EmailChange,
                $collection,
                (string) $user->id,
            );

            Record::of($collection)
                ->where('id', $user->id)
                ->update(['email' => $newEmail]);

            return ['status' => 'ok'];
        });
    }
}
