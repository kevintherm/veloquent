<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('fails to create auth collection with invalid providers', function () {
    app(CreateCollectionAction::class)->execute([
        'name' => 'users_invalid_provider',
        'type' => CollectionType::Auth->value,
        'fields' => [],
        'options' => [
            'auth_methods' => [
                'oauth' => [
                    'enabled' => true,
                    'providers' => [
                        'invalid_provider' => [
                            'client_id' => 'id',
                            'client_secret' => 'secret',
                        ],
                    ],
                ],
            ],
        ],
    ]);
})->throws(ValidationException::class, 'The selected provider "invalid_provider" is invalid.');

it('fails to create auth collection with missing client_id for provider', function () {
    app(CreateCollectionAction::class)->execute([
        'name' => 'users_missing_id',
        'type' => CollectionType::Auth->value,
        'fields' => [],
        'options' => [
            'auth_methods' => [
                'oauth' => [
                    'enabled' => true,
                    'providers' => [
                        'google' => [
                            'client_secret' => 'secret',
                        ],
                    ],
                ],
            ],
        ],
    ]);
})->throws(ValidationException::class);

it('fails to create auth collection with missing client_secret for provider', function () {
    app(CreateCollectionAction::class)->execute([
        'name' => 'users_missing_secret',
        'type' => CollectionType::Auth->value,
        'fields' => [],
        'options' => [
            'auth_methods' => [
                'oauth' => [
                    'enabled' => true,
                    'providers' => [
                        'google' => [
                            'client_id' => 'id',
                        ],
                    ],
                ],
            ],
        ],
    ]);
})->throws(ValidationException::class);

it('successfully creates auth collection with valid providers', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_valid_oauth',
        'type' => CollectionType::Auth->value,
        'fields' => [],
        'options' => [
            'auth_methods' => [
                'oauth' => [
                    'enabled' => true,
                    'providers' => [
                        'github' => [
                            'client_id' => 'id',
                            'client_secret' => 'secret',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($collection->options['auth_methods']['oauth']['providers']['github'])->toMatchArray([
        'client_id' => 'id',
        'client_secret' => 'secret',
    ]);
});

it('fails to update auth collection with invalid providers', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_update_invalid',
        'type' => CollectionType::Auth->value,
        'fields' => [],
    ]);

    app(UpdateCollectionAction::class)->execute($collection, [
        'options' => [
            'auth_methods' => [
                'oauth' => [
                    'enabled' => true,
                    'providers' => [
                        'invalid_provider' => [
                            'client_id' => 'id',
                            'client_secret' => 'secret',
                        ],
                    ],
                ],
            ],
        ],
    ]);
})->throws(ValidationException::class);

it('successfully updates auth collection with valid providers', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_update_valid',
        'type' => CollectionType::Auth->value,
        'fields' => [],
    ]);

    $updated = app(UpdateCollectionAction::class)->execute($collection, [
        'options' => [
            'auth_methods' => [
                'oauth' => [
                    'enabled' => true,
                    'providers' => [
                        'github' => [
                            'client_id' => 'updated_id',
                            'client_secret' => 'updated_secret',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($updated->options['auth_methods']['oauth']['providers']['github'])->toMatchArray([
        'client_id' => 'updated_id',
        'client_secret' => 'updated_secret',
    ]);
});
