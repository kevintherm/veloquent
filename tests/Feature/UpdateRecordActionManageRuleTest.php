<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Records\Actions\UpdateRecordAction;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('prevents direct update of email and password when manage rule is null', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_'.Str::random(5),
        'type' => CollectionType::Auth->value,
        'fields' => [],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
            'manage' => null,
        ],
    ]);

    $record = Record::of($collection)->create([
        'email' => 'test@example.com',
        'password' => 'secret123',
        'email_visibility' => true,
        'verified' => true,
    ]);

    Auth::setUser($record);

    expect(fn () => resolve(UpdateRecordAction::class)->execute($collection, $record->id, ['email' => 'new@example.com']))
        ->toThrow(ValidationException::class, 'Email cannot be changed directly. Use the email change flow.');

    expect(fn () => resolve(UpdateRecordAction::class)->execute($collection, $record->id, ['password' => 'newsecret']))
        ->toThrow(ValidationException::class, 'Password cannot be changed directly. Use the password reset flow.');
});

it('allows direct update of email and password when manage rule is empty string', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_'.Str::random(5),
        'type' => CollectionType::Auth->value,
        'fields' => [],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
            'manage' => '',
        ],
    ]);

    $record = Record::of($collection)->create([
        'email' => 'test@example.com',
        'password' => 'secret123',
        'email_visibility' => true,
        'verified' => true,
    ]);

    Auth::setUser($record);

    $updatedRecord = resolve(UpdateRecordAction::class)->execute($collection, $record->id, [
        'email' => 'new@example.com',
        'password' => 'newsecret',
    ]);

    expect($updatedRecord->email)->toBe('new@example.com');
});

it('evaluates manage rule correctly to allow or deny update', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_'.Str::random(5),
        'type' => CollectionType::Auth->value,
        'fields' => [],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
            'manage' => 'id = @request.auth.id',
        ],
    ]);

    $record1 = Record::of($collection)->create([
        'email' => 'user1@example.com',
        'password' => 'secret123',
        'email_visibility' => true,
        'verified' => true,
    ]);

    $record2 = Record::of($collection)->create([
        'email' => 'user2@example.com',
        'password' => 'secret123',
        'email_visibility' => true,
        'verified' => true,
    ]);

    // Authenticate as record1
    Auth::setUser($record1);

    // Should succeed for record1
    $updated1 = resolve(UpdateRecordAction::class)->execute($collection, $record1->id, [
        'email' => 'user1_new@example.com',
    ]);
    expect($updated1->email)->toBe('user1_new@example.com');

    // Should fail for record2 because id != @request.auth.id
    expect(fn () => resolve(UpdateRecordAction::class)->execute($collection, $record2->id, ['email' => 'hacked@example.com']))
        ->toThrow(ValidationException::class);
});
