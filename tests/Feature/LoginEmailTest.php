<?php

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Veloquent\Core\Domain\Auth\Jobs\SendLoginEmailJob;
use Veloquent\Core\Domain\Auth\Support\Fingerprint;
use Veloquent\Core\Domain\Collections\Actions\CreateCollectionAction;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Auth\Models\AuthToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->collection = app(CreateCollectionAction::class)->execute([
        'type' => 'auth',
        'name' => 'login_test_users',
        'options' => [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'identity_fields' => ['email'],
                ],
            ],
        ],
        'fields' => [],
        'indexes' => [],
    ]);

    $this->user = Record::of($this->collection)->create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);
});

it('dispatches login email job and logs USER_LOGIN on first login', function () {
    Queue::fake();
    Log::spy();

    $response = $this->postJson("/api/collections/{$this->collection->id}/auth/login", [
        'identity' => 'user@example.com',
        'password' => 'password',
    ]);

    $response->assertOk();

    Queue::assertPushed(SendLoginEmailJob::class, function ($job) {
        return $job->email === 'user@example.com' && $job->ipAddress === '127.0.0.1';
    });

    Log::shouldHaveReceived('info')
        ->with('USER_LOGIN', Mockery::on(function ($arg) {
            return $arg['user_id'] === (string) $this->user->id && $arg['is_new_source'] === true;
        }));
});

it('does not dispatch login email job on subsequent logins with the same fingerprint', function () {
    AuthToken::create([
        'collection_name' => $this->collection->name,
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', 'some_token'),
        'expires_at' => now()->addHour(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Symfony',
        'fingerprint' => Fingerprint::generate(),
    ]);

    Queue::fake();

    $response = $this->withHeader('User-Agent', 'Symfony')
        ->postJson("/api/collections/{$this->collection->id}/auth/login", [
            'identity' => 'user@example.com',
            'password' => 'password',
        ]);

    $response->assertOk();
    Queue::assertNotPushed(SendLoginEmailJob::class);
});

it('dispatches login email job if the fingerprint differs', function () {
    AuthToken::create([
        'collection_name' => $this->collection->name,
        'collection_id' => $this->collection->id,
        'record_id' => (string) $this->user->id,
        'token_hash' => hash('sha256', 'some_token'),
        'expires_at' => now()->addHour(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Symfony',
        'fingerprint' => Fingerprint::generate(),
    ]);

    Queue::fake();

    $response = $this->withHeader('User-Agent', 'DifferentAgent')
        ->postJson("/api/collections/{$this->collection->id}/auth/login", [
            'identity' => 'user@example.com',
            'password' => 'password',
        ]);

    $response->assertOk();
    Queue::assertPushed(SendLoginEmailJob::class);
});

it('respects client-provided device_id parameter to determine fingerprint', function () {
    Queue::fake();

    $response = $this->postJson("/api/collections/{$this->collection->id}/auth/login", [
        'identity' => 'user@example.com',
        'password' => 'password',
        'device_id' => 'my-unique-device',
    ]);

    $response->assertOk();
    Queue::assertPushed(SendLoginEmailJob::class);

    $this->assertDatabaseHas('auth_tokens', [
        'record_id' => $this->user->id,
        'fingerprint' => hash('sha256', 'my-unique-device'),
    ]);

    Queue::fake();

    $response2 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
        ->postJson("/api/collections/{$this->collection->id}/auth/login", [
            'identity' => 'user@example.com',
            'password' => 'password',
            'device_id' => 'my-unique-device',
        ]);

    $response2->assertOk();
    Queue::assertNotPushed(SendLoginEmailJob::class);
});

it('resolves default templates and handles placeholders correctly in SendLoginEmailJob', function () {
    Mail::fake();
    Log::spy();

    $job = new SendLoginEmailJob(
        'user@example.com',
        $this->collection,
        '2026-06-11 12:00:00',
        '192.168.1.5'
    );

    app()->call([$job, 'handle']);

    Mail::assertSent(\Veloquent\Core\Domain\Emails\Mail\TemplateMail::class, function ($mail) {
        expect($mail->mailSubject)->toBe('New Login Detected');
        $html = $mail->render();
        expect($html)->toContain('New Login Detected');
        expect($html)->toContain('2026-06-11 12:00:00');
        expect($html)->toContain('192.168.1.5');
        return true;
    });

    Log::shouldHaveReceived('info')
        ->with('LOGIN_EMAIL_SENT', Mockery::on(function ($arg) {
            return $arg['to'] === 'user@example.com' && $arg['collection'] === $this->collection->name;
        }));
});
