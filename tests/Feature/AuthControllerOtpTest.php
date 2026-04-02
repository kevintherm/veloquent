<?php

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Otp\Jobs\SendOtpJob;
use App\Domain\Otp\Models\OtpToken;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
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
            'manage' => null,
        ],
    ]);
    $this->user = Record::of($this->collection)->create([
        'email' => 'test@example.com',
        'password' => Hash::make('old_password'),
    ]);
});

it('requests a password reset and pushes a job', function () {
    Queue::fake();

    $response = $this->postJson("/api/collections/{$this->collection->id}/auth/password-reset/request", [
        'email' => 'test@example.com',
    ]);

    $response->assertOk();
    $response->assertJsonPath('message', 'If the email exists, a reset code has been sent.');

    $this->assertDatabaseHas('otp_tokens', [
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'action' => OtpAction::PasswordReset->value,
    ]);

    Queue::assertPushed(fn (SendOtpJob $job) => $job->collection->id === $this->collection->id);
});

it('confirms password reset with valid OTP', function () {
    $code = '123456';
    OtpToken::create([
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', $code),
        'action' => OtpAction::PasswordReset->value,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson("/api/collections/{$this->collection->id}/auth/password-reset/confirm", [
        'email' => 'test@example.com',
        'token' => $code,
        'new_password' => 'new_password',
    ]);

    $response->assertOk();
    $response->assertJsonPath('message', 'Password has been reset successfully.');

    // Verify password changed
    $this->user->refresh();
    expect(Hash::check('new_password', $this->user->password))->toBeTrue();

    // Verify OTP marked as used
    expect(OtpToken::where('record_id', (string) $this->user->id)->first()->used_at)->not->toBeNull();
});

it('requests email verification when authenticated', function () {
    Queue::fake();

    $tokenData = app(TokenAuthService::class)->generateToken($this->user);

    $response = $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->postJson("/api/collections/{$this->collection->id}/auth/email-verification/request");

    $response->assertOk();
    $this->assertDatabaseHas('otp_tokens', [
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'action' => OtpAction::EmailVerification->value,
    ]);
});

it('confirms email verification with valid OTP', function () {
    $tokenData = app(TokenAuthService::class)->generateToken($this->user);
    $code = '123456';
    OtpToken::create([
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', $code),
        'action' => OtpAction::EmailVerification->value,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->postJson("/api/collections/{$this->collection->id}/auth/email-verification/confirm", [
            'token' => $code,
        ]);

    $response->assertOk();
    $response->assertJsonPath('message', 'Email verified successfully.');

    $this->user->refresh();
    expect($this->user->verified)->toBeTrue();
});

// ─── Email Change ─────────────────────────────────────────────────────────────

it('requests an email change and dispatches OTP job to the new email', function () {
    Queue::fake();

    $tokenData = app(TokenAuthService::class)->generateToken($this->user);

    $response = $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->postJson("/api/collections/{$this->collection->id}/auth/email-change/request", [
            'new_email' => 'new@example.com',
        ]);

    $response->assertOk();
    $response->assertJsonPath('message', 'A verification code has been sent to your new email address.');

    $this->assertDatabaseHas('otp_tokens', [
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'action' => OtpAction::EmailChange->value,
    ]);

    Queue::assertPushed(fn (SendOtpJob $job) => $job->email === 'new@example.com');
});

it('rejects email change request when new_email is already taken', function () {
    $tokenData = app(TokenAuthService::class)->generateToken($this->user);

    Record::of($this->collection)->create([
        'email' => 'taken@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->postJson("/api/collections/{$this->collection->id}/auth/email-change/request", [
            'new_email' => 'taken@example.com',
        ]);

    $response->assertStatus(422);
});

it('confirms email change with valid OTP and updates the email', function () {
    $tokenData = app(TokenAuthService::class)->generateToken($this->user);
    $code = '123456';

    OtpToken::create([
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', $code),
        'action' => OtpAction::EmailChange->value,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->postJson("/api/collections/{$this->collection->id}/auth/email-change/confirm", [
            'token' => $code,
            'new_email' => 'new@example.com',
        ]);

    $response->assertOk();
    $response->assertJsonPath('message', 'Email address updated successfully.');

    $this->user->refresh();
    expect($this->user->email)->toBe('new@example.com');
    expect(OtpToken::where('record_id', (string) $this->user->id)->where('action', OtpAction::EmailChange->value)->first()->used_at)->not->toBeNull();
});

it('rejects email change confirm with invalid OTP', function () {
    $tokenData = app(TokenAuthService::class)->generateToken($this->user);

    $response = $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->postJson("/api/collections/{$this->collection->id}/auth/email-change/confirm", [
            'token' => 'WRONG1',
            'new_email' => 'new@example.com',
        ]);

    $response->assertStatus(422);
});

it('rejects email change when unauthenticated', function () {
    $response = $this->postJson("/api/collections/{$this->collection->id}/auth/email-change/request", [
        'new_email' => 'new@example.com',
    ]);

    $response->assertUnauthorized();
});

// ─── Direct Update Guards ─────────────────────────────────────────────────────

it('blocks direct email update via records API on auth collection', function () {
    $tokenData = app(TokenAuthService::class)->generateToken($this->user);

    $response = $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->patchJson("/api/collections/{$this->collection->id}/records/{$this->user->id}", [
            'email' => 'hacked@example.com',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);

    $this->user->refresh();
    expect($this->user->email)->toBe('test@example.com');
});

it('blocks direct password update via records API on auth collection', function () {
    $tokenData = app(TokenAuthService::class)->generateToken($this->user);

    $response = $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->patchJson("/api/collections/{$this->collection->id}/records/{$this->user->id}", [
            'password' => 'newpassword',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['password']);
});
