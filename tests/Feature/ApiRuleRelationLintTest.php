<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
