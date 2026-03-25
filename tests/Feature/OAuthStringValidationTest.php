<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('fails to create auth collection with string as providers', function () {
    try {
        app(CreateCollectionAction::class)->execute([
            'name' => 'users_string_providers',
            'type' => CollectionType::Auth->value,
            'fields' => [],
            'options' => [
                'auth_methods' => [
                    'oauth' => [
                        'enabled' => true,
                        'providers' => ""
                    ]
                ]
            ]
        ]);
        test()->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        // dd($e->errors());
        expect($e->errors())->toHaveKey('auth_methods.oauth.providers');
    }
});

it('fails to update auth collection with string as providers', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_update_string_providers',
        'type' => CollectionType::Auth->value,
        'fields' => [],
    ]);

    try {
        app(UpdateCollectionAction::class)->execute($collection, [
            'options' => [
                'auth_methods' => [
                    'oauth' => [
                        'enabled' => true,
                        'providers' => ""
                    ]
                ]
            ]
        ]);
        test()->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('auth_methods.oauth.providers');
    }
});
