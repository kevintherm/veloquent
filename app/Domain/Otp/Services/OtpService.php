<?php

namespace App\Domain\Otp\Services;

use App\Domain\Collections\Models\Collection;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Jobs\SendOtpJob;
use App\Domain\Otp\Models\OtpToken;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /**
     * Invalidate any existing unused token for that user+action, create a new one,
     * and dispatch a queued job to send the OTP email.
     */
    public function issue(Record $user, OtpAction $action, Collection $collection): void
    {
        DB::transaction(function () use ($user, $action, $collection) {
            OtpToken::query()
                ->forRecord($collection->id, $user->id)
                ->where('action', $action->value)
                ->unused()
                ->delete();

            $code = $this->generateCode();

            OtpToken::create([
                'collection_id' => $collection->id,
                'record_id' => (string) $user->id,
                'token_hash' => $this->hashCode($code),
                'action' => $action->value,
                'expires_at' => now()->addMinutes($this->ttlMinutes()),
            ]);

            SendOtpJob::dispatch(
                $user->email,
                $code,
                $action,
                $collection,
            );
        });
    }

    /**
     * Validate + mark token as used, return the associated user record.
     *
     * @throws ValidationException
     */
    public function consume(string $rawCode, OtpAction $action, Collection $collection, string $recordId): Record
    {
        return DB::transaction(function () use ($rawCode, $action, $collection, $recordId) {
            $token = OtpToken::query()
                ->forRecord($collection->id, $recordId)
                ->where('action', $action)
                ->where('token_hash', $this->hashCode($rawCode))
                ->unused()
                ->active()
                ->lockForUpdate()
                ->first();

            if (! $token) {
                throw ValidationException::withMessages([
                    'token' => 'The OTP code is invalid or has expired.',
                ]);
            }

            $token->update(['used_at' => now()]);

            return Record::of($collection)->findOrFail($recordId);
        });
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
        $numeric = (bool) config('velo.otp.numeric', false);

        $alphabet = $numeric
            ? '0123456789'
            : 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no ambiguous chars

        return implode('', array_map(
            fn () => $alphabet[random_int(0, strlen($alphabet) - 1)],
            range(1, $length)
        ));
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
