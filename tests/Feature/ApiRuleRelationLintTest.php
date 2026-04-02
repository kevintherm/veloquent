<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function alphaSuffix(int $length = 6): string
{
    $letters = 'abcdefghijklmnopqrstuvwxyz';
    $suffix = '';

    for ($i = 0; $i < $length; $i++) {
        $suffix .= $letters[random_int(0, strlen($letters) - 1)];
    }

    return $suffix;
}

it('allows relation field access in api rule lint during collection creation', function () {
    $users = app(CreateCollectionAction::class)->execute([
        'name' => 'users_'.alphaSuffix(),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'verified', 'type' => CollectionFieldType::Boolean->value],
        ],
    ]);

    expect(fn () => app(CreateCollectionAction::class)->execute([
        'name' => 'posts_'.alphaSuffix(),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            ['name' => 'author', 'type' => CollectionFieldType::Relation->value, 'target_collection_id' => $users->id],
        ],
        'api_rules' => [
            'list' => 'author.verified = true',
            'create' => '',
            'view' => '',
            'update' => '',
            'delete' => '',
        ],
    ]))->not->toThrow(InvalidRuleExpressionException::class);
});

it('allows relation field access in api rule lint during collection update', function () {
    $users = app(CreateCollectionAction::class)->execute([
        'name' => 'users_'.alphaSuffix(),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'verified', 'type' => CollectionFieldType::Boolean->value],
        ],
    ]);

    $posts = app(CreateCollectionAction::class)->execute([
        'name' => 'posts_'.alphaSuffix(),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            ['name' => 'author', 'type' => CollectionFieldType::Relation->value, 'target_collection_id' => $users->id],
        ],
    ]);

    expect(fn () => app(UpdateCollectionAction::class)->execute($posts, [
        'api_rules' => [
            'list' => 'author.verified = true',
            'create' => '',
            'view' => '',
            'update' => '',
            'delete' => '',
        ],
    ]))->not->toThrow(InvalidRuleExpressionException::class);
});

it('still rejects unknown relation field access in api rule lint', function () {
    $users = app(CreateCollectionAction::class)->execute([
        'name' => 'users_'.alphaSuffix(),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'verified', 'type' => CollectionFieldType::Boolean->value],
        ],
    ]);

    expect(fn () => app(CreateCollectionAction::class)->execute([
        'name' => 'posts_'.alphaSuffix(),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            ['name' => 'author', 'type' => CollectionFieldType::Relation->value, 'target_collection_id' => $users->id],
        ],
        'api_rules' => [
            'list' => 'author.nonexistent = true',
            'create' => '',
            'view' => '',
            'update' => '',
            'delete' => '',
        ],
    ]))->toThrow(InvalidRuleExpressionException::class);
});

it('requires manage api rule for auth collections and returns array-shaped errors', function () {
    try {
        app(CreateCollectionAction::class)->execute([
            'name' => 'auth_'.alphaSuffix(),
            'type' => CollectionType::Auth->value,
            'fields' => [],
            'api_rules' => [
                'list' => '',
                'create' => '',
                'view' => '',
                'update' => '',
                'delete' => '',
            ],
        ]);

        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('api_rules')
            ->and($exception->errors()['api_rules'])
            ->toBe(['Missing API rules for: manage']);
    }
});

it('allows manage key for base collections', function () {
    expect(fn () => app(CreateCollectionAction::class)->execute([
        'name' => 'base_'.alphaSuffix(),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
        ],
        'api_rules' => [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => '',
            'delete' => '',
            'manage' => '',
        ],
    ]))->not->toThrow(ValidationException::class);
});

it('uses in-memory lint mode for create and update api rules', function () {
    expect(fn () => app(CreateCollectionAction::class)->execute([
        'name' => 'memory_'.alphaSuffix(),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
        ],
        'api_rules' => [
            'list' => '',
            'create' => '@request.auth.id in (1, 2)',
            'view' => '',
            'update' => '@request.auth.id in (1, 2)',
            'delete' => '',
        ],
    ]))->not->toThrow(InvalidRuleExpressionException::class);
});

it('uses in-memory lint mode for auth manage rules', function () {
    expect(fn () => app(CreateCollectionAction::class)->execute([
        'name' => 'auth_manage_'.alphaSuffix(),
        'type' => CollectionType::Auth->value,
        'fields' => [],
        'api_rules' => [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => '',
            'delete' => '',
            'manage' => '@request.auth.id in (1, 2)',
        ],
    ]))->not->toThrow(InvalidRuleExpressionException::class);
});
