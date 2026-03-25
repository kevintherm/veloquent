<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates an auth collection with default options', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_default',
        'type' => CollectionType::Auth->value,
        'fields' => [],
    ]);

    expect($collection->options)->toMatchArray([
        'auth_methods' => [
            'standard' => [
                'enabled' => true,
                'identity_fields' => ['email'],
            ],
            'oauth' => [
                'enabled' => false,
            ],
        ],
    ]);
});

it('creates an auth collection with custom identity fields', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'customers_custom',
        'type' => CollectionType::Auth->value,
        'fields' => [
            ['name' => 'username', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ],
        'options' => [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'identity_fields' => ['username', 'email'],
                ],
            ],
        ],
    ]);

    expect($collection->options['auth_methods']['standard']['identity_fields'])->toBe(['username', 'email']);
});

it('fails creating an auth collection with invalid identity field', function () {
    app(CreateCollectionAction::class)->execute([
        'name' => 'staff_invalid',
        'type' => CollectionType::Auth->value,
        'fields' => [],
        'options' => [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'identity_fields' => ['invalid_field'],
                ],
            ],
        ],
    ]);
})->throws(ValidationException::class);

it('updates an auth collection options', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_update',
        'type' => CollectionType::Auth->value,
        'fields' => [
            ['name' => 'username', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ],
    ]);

    $updated = app(UpdateCollectionAction::class)->execute($collection, [
        'options' => [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'identity_fields' => ['username'],
                ],
                'oauth' => [
                    'enabled' => false,
                ],
            ],
        ],
    ]);

    expect($updated->options['auth_methods']['standard']['identity_fields'])->toBe(['username']);
});

it('allows arbitrary options for base collections', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'posts_base',
        'type' => CollectionType::Base->value,
        'fields' => [],
        'options' => [
            'some_custom_option' => 'value',
        ],
    ]);

    expect($collection->options)->toMatchArray([
        'some_custom_option' => 'value',
    ]);

    $updated = app(UpdateCollectionAction::class)->execute($collection, [
        'options' => [
            'another_option' => 'foo',
        ],
    ]);

    expect($updated->options)->toMatchArray([
        'another_option' => 'foo',
    ]);
});

it('accepts the new auth options schema via controller', function () {
    Gate::shouldReceive('authorize')->andReturnNull();

    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_controller',
        'type' => CollectionType::Auth->value,
        'fields' => [
            ['name' => 'username', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ],
    ]);

    $response = $this->patchJson(route('collections.update', $collection), [
        'name' => 'users_controller',
        'options' => [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'identity_fields' => ['username'],
                ],
                'oauth' => [
                    'enabled' => false,
                ],
            ],
        ],
    ]);

    $response->assertOk();
    $collection->refresh();
    expect($collection->options['auth_methods']['standard']['identity_fields'])->toBe(['username']);
});

it('logs in with dynamic identity field', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_dynamic',
        'type' => CollectionType::Auth->value,
        'fields' => [
            ['name' => 'username', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ],
        'options' => [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'identity_fields' => ['username', 'email'],
                ],
            ],
        ],
    ]);

    Record::of($collection)->create([
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    // Test login with username
    $response = $this->postJson("/api/collections/{$collection->id}/auth/login", [
        'identity' => 'testuser',
        'password' => 'password',
    ]);
    $response->assertOk()->assertJsonStructure(['data' => ['token', 'expires_in']]);

    // Test login with email
    $response = $this->postJson("/api/collections/{$collection->id}/auth/login", [
        'identity' => 'test@example.com',
        'password' => 'password',
    ]);
    $response->assertOk()->assertJsonStructure(['data' => ['token', 'expires_in']]);
});
