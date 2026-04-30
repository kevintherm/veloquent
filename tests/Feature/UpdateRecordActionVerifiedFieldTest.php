<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Actions\UpdateRecordAction;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('ignores verified field but allows email_visibility when changed by non-superuser without manage permission', function () {
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
        'verified' => false,
    ]);

    Auth::setUser($record);

    // Try to update verified and email_visibility
    $updatedRecord = resolve(UpdateRecordAction::class)->execute($collection, $record->id, [
        'verified' => true,
        'email_visibility' => false,
    ]);

    // verified should not have changed, but email_visibility should have
    expect($updatedRecord->verified)->toBe(false);
    expect($updatedRecord->email_visibility)->toBe(false);
});

it('allows superuser to change verified and email_visibility fields', function () {
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
        'verified' => false,
    ]);

    // Create a superuser
    $superuserCollection = Collection::where('name', 'superusers')->first();
    if (! $superuserCollection) {
        $superuserCollection = app(CreateCollectionAction::class)->execute([
            'name' => 'superusers',
            'type' => CollectionType::Auth->value,
            'fields' => [
                ['name' => 'name', 'type' => CollectionFieldType::Text->value],
            ],
            'is_system' => true,
        ]);
    }

    $superuser = Record::of($superuserCollection)->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'admin123',
    ]);

    Auth::setUser($superuser);

    // Try to update verified and email_visibility
    $updatedRecord = resolve(UpdateRecordAction::class)->execute($collection, $record->id, [
        'verified' => true,
        'email_visibility' => false,
    ]);

    // fields should have changed
    expect($updatedRecord->verified)->toBe(true);
    expect($updatedRecord->email_visibility)->toBe(false);
});

it('allows user with manage permission to change verified and email_visibility fields', function () {
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
            'manage' => 'id = @request.auth.id', // Allow user to manage their own auth fields
        ],
    ]);

    $record = Record::of($collection)->create([
        'email' => 'test@example.com',
        'password' => 'secret123',
        'email_visibility' => true,
        'verified' => false,
    ]);

    Auth::setUser($record);

    // Try to update verified and email_visibility
    $updatedRecord = resolve(UpdateRecordAction::class)->execute($collection, $record->id, [
        'verified' => true,
        'email_visibility' => false,
    ]);

    // fields should have changed because manage rule evaluated to true
    expect($updatedRecord->verified)->toBe(true);
    expect($updatedRecord->email_visibility)->toBe(false);
});
