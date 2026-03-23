<?php

namespace App\Domain\Otp\Services;

use App\Domain\Collections\Models\Collection;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Jobs\SendOtpJob;
use App\Domain\Otp\Models\OtpToken;
use App\Domain\Records\Models\Record;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /**
     * Invalidate any existing unused token for that user+action, create a new one,
     * and dispatch a queued job to send the OTP email.
     */
    public function issue(Record $user, OtpAction $action, string $collectionId): void
    {
        OtpToken::query()
            ->forRecord($collectionId, $user->id)
            ->where('action', $action->value)
            ->unused()
            ->delete();

        $code = $this->generateCode();

        OtpToken::create([
            'collection_id' => $collectionId,
            'record_id' => (string) $user->id,
            'token_hash' => $this->hashCode($code),
            'action' => $action->value,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ]);

        $collection = Collection::query()->find($collectionId);

        SendOtpJob::dispatch(
            $user->email,
            $code,
            $action,
            $collectionId,
            $collection?->name ?? 'unknown',
        );
    }

    /**
     * Validate + mark token as used, return the associated user record.
     *
     * @throws ValidationException
     */
    public function consume(string $rawCode, OtpAction $action, string $collectionId, string $recordId): Record
    {
        $token = OtpToken::query()
            ->forRecord($collectionId, $recordId)
            ->where('action', $action->value)
            ->where('token_hash', $this->hashCode($rawCode))
            ->unused()
            ->active()
            ->first();

        if (! $token) {
            throw ValidationException::withMessages([
                'token' => 'The OTP code is invalid or has expired.',
            ]);
        }

        $token->update(['used_at' => now()]);

        $collection = Collection::query()->findOrFail($collectionId);

        return Record::of($collection)->findOrFail($recordId);
    }

    /**
     * Purge expired and used tokens beyond the grace period.
     */
    public function cleanup(): int
    {
        $gracePeriod = (int) config('velo.otp.cleanup_grace', 60);

        return OtpToken::query()
            ->where(function ($query) use ($gracePeriod) {
                $query->where('expires_at', '<', now()->subMinutes($gracePeriod))
                    ->orWhere(function ($query) use ($gracePeriod) {
                        $query->whereNotNull('used_at')
                            ->where('used_at', '<', now()->subMinutes($gracePeriod));
                    });
            })
            ->delete();
    }

    private function generateCode(): string
    {
        $length = (int) config('velo.otp.length', 6);
        $max = (int) str_repeat('9', $length);
        $min = (int) ('1'.str_repeat('0', $length - 1));

        return (string) random_int($min, $max);
    }

    private function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('velo.otp.ttl', 15));
    }
}
