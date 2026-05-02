<?php

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Models\Collection;
use App\Domain\Otp\Enums\OtpAction;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->collection = app(CreateCollectionAction::class)->execute([
        'type' => 'auth',
        'name' => 'test_users',
        'fields' => [],
        'indexes' => [],
    ]);

    $this->superusersCollection = Collection::where('name', 'superusers')->first()
        ?? app(CreateCollectionAction::class)->execute([
            'type' => 'auth',
            'name' => 'superusers',
            'fields' => [],
            'indexes' => [],
            'is_system' => true,
        ]);

    $this->superuser = Record::of($this->superusersCollection)->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
});

it('can fetch default email template', function () {
    $tokenData = app(TokenAuthService::class)->generateToken($this->superuser);

    $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->getJson(route('email-templates.show', [$this->collection, OtpAction::PasswordReset->value]))
        ->assertOk()
        ->assertJsonStructure(['data' => ['action', 'label', 'content']]);
});

it('can update email template', function () {
    $tokenData = app(TokenAuthService::class)->generateToken($this->superuser);

    $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->putJson(route('email-templates.update', [$this->collection, OtpAction::PasswordReset->value]), [
            'content' => 'Custom Login Template',
        ])
        ->assertOk();

    $this->assertDatabaseHas('email_templates', [
        'collection_id' => $this->collection->id,
        'action' => OtpAction::PasswordReset->value,
        'content' => 'Custom Login Template',
    ]);
});

it('denies non-superuser access to email templates', function () {
    $user = Record::of($this->collection)->create([
        'email' => 'regular@example.com',
        'password' => 'password',
    ]);

    $tokenData = app(TokenAuthService::class)->generateToken($user);

    $this->withHeader('Authorization', "Bearer {$tokenData->token}")
        ->getJson(route('email-templates.show', [$this->collection, OtpAction::PasswordReset->value]))
        ->assertForbidden();
});
