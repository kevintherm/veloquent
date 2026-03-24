<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Jobs\SendOtpJob;
use App\Domain\Otp\Models\OtpToken;
use App\Domain\Otp\Services\OtpService;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->otpService = app(OtpService::class);
    $this->collection = app(CreateCollectionAction::class)->execute([
        'type' => CollectionType::Auth,
        'name' => 'test_users',
        'fields' => [],
        'indexes' => [],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
    ]);
    $this->user = Record::of($this->collection)->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
});

it('issues an OTP token and stores hashed code', function () {
    Queue::fake();

    $this->otpService->issue($this->user, OtpAction::PasswordReset, $this->collection);

    $this->assertDatabaseHas('otp_tokens', [
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'action' => OtpAction::PasswordReset->value,
    ]);

    $token = OtpToken::where('record_id', (string) $this->user->id)->first();
    expect($token->token_hash)->not->toBe('123456'); // Should be hashed
    expect($token->used_at)->toBeNull();

    Queue::assertPushed(fn (SendOtpJob $job) => $job->collection->id === $this->collection->id);
});

it('invalidates previous unused tokens on re-issue', function () {
    $this->otpService->issue($this->user, OtpAction::PasswordReset, $this->collection);
    expect(OtpToken::count())->toBe(1);

    $this->otpService->issue($this->user, OtpAction::PasswordReset, $this->collection);
    expect(OtpToken::count())->toBe(1); // Old one deleted
});

it('consumes a valid OTP token', function () {
    Queue::fake();

    // We need the raw code to consume, but issue() generates it internally.
    // For testing, let's manually create one or mock the generation.
    $code = '123456';
    OtpToken::create([
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', $code),
        'action' => OtpAction::PasswordReset->value,
        'expires_at' => now()->addMinutes(15),
    ]);

    $user = $this->otpService->consume($code, OtpAction::PasswordReset, $this->collection, (string) $this->user->id);

    expect($user->id)->toBe($this->user->id);
    expect(OtpToken::where('record_id', (string) $this->user->id)->first()->used_at)->not->toBeNull();
});

it('rejects expired OTP tokens', function () {
    $code = '123456';
    OtpToken::create([
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', $code),
        'action' => OtpAction::PasswordReset->value,
        'expires_at' => now()->subMinutes(1),
    ]);

    $this->otpService->consume($code, OtpAction::PasswordReset, $this->collection, (string) $this->user->id);
})->throws(ValidationException::class);

it('rejects already-used OTP tokens', function () {
    $code = '123456';
    OtpToken::create([
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', $code),
        'action' => OtpAction::PasswordReset->value,
        'expires_at' => now()->addMinutes(15),
        'used_at' => now(),
    ]);

    $this->otpService->consume($code, OtpAction::PasswordReset, $this->collection, (string) $this->user->id);
})->throws(ValidationException::class);

it('cleans up expired and used tokens', function () {
    // Expired
    OtpToken::create([
        'collection_id' => $this->collection->id,
        'record_id' => '1',
        'token_hash' => 'hash1',
        'action' => OtpAction::PasswordReset->value,
        'expires_at' => now()->subMinutes(70),
    ]);

    // Used
    OtpToken::create([
        'collection_id' => $this->collection->id,
        'record_id' => '2',
        'token_hash' => 'hash2',
        'action' => OtpAction::PasswordReset->value,
        'expires_at' => now()->addMinutes(15),
        'used_at' => now()->subMinutes(70),
    ]);

    // Active & Unused (should stay)
    OtpToken::create([
        'collection_id' => $this->collection->id,
        'record_id' => '3',
        'token_hash' => 'hash3',
        'action' => OtpAction::PasswordReset->value,
        'expires_at' => now()->addMinutes(15),
    ]);

    $deleted = $this->otpService->cleanup();

    expect($deleted)->toBe(2);
    expect(OtpToken::count())->toBe(1);
});
