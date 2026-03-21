<?php

use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('recreates a base collection field with the same name and a different type', function () {
    $collection = Collection::create([
        'name' => 'tasks',
        'type' => CollectionType::Base,
        'description' => 'Tasks',
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
        'indexes' => [],
    ]);

    $oldTitleFieldId = collect($collection->fields)->firstWhere('name', 'title')['id'];

    app(UpdateCollectionAction::class)->execute($collection, [
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Number->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
    ]);

    $collection->refresh();
    $titleField = collect($collection->fields)->firstWhere('name', 'title');

    expect($titleField['type'])->toBe(CollectionFieldType::Number->value)
        ->and($titleField['id'])->not->toBe($oldTitleFieldId)
        ->and($titleField['id'])->toBeString()->not->toBe('');
});

it('recreates field type on auth collections while preserving reserved fields', function () {
    $collection = Collection::create([
        'name' => 'members',
        'type' => CollectionType::Auth,
        'description' => 'Members',
        'fields' => [
            ['name' => 'email', 'type' => CollectionFieldType::Email->value, 'order' => 0, 'nullable' => false, 'unique' => true, 'default' => null],
            ['name' => 'password', 'type' => CollectionFieldType::Text->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null],
            ['name' => 'email_visibility', 'type' => CollectionFieldType::Boolean->value, 'order' => 2, 'nullable' => true, 'unique' => false, 'default' => true],
            ['name' => 'verified', 'type' => CollectionFieldType::Boolean->value, 'order' => 3, 'nullable' => true, 'unique' => false, 'default' => false],
            ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 4, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
        'indexes' => [],
    ]);

    app(UpdateCollectionAction::class)->execute($collection, [
        'fields' => [
            ['name' => 'email', 'type' => CollectionFieldType::Email->value, 'order' => 0, 'nullable' => false, 'unique' => true, 'default' => null],
            ['name' => 'password', 'type' => CollectionFieldType::Text->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null],
            ['name' => 'email_visibility', 'type' => CollectionFieldType::Boolean->value, 'order' => 2, 'nullable' => true, 'unique' => false, 'default' => true],
            ['name' => 'verified', 'type' => CollectionFieldType::Boolean->value, 'order' => 3, 'nullable' => true, 'unique' => false, 'default' => false],
            ['name' => 'title', 'type' => CollectionFieldType::Number->value, 'order' => 4, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
    ]);

    $collection->refresh();

    $fieldMap = collect($collection->fields)->keyBy('name');

    expect($fieldMap['title']['type'])->toBe(CollectionFieldType::Number->value)
        ->and($fieldMap['email_visibility']['type'])->toBe(CollectionFieldType::Boolean->value)
        ->and($fieldMap['email_visibility']['id'])->toBeString()->not->toBe('')
        ->and($fieldMap['verified']['id'])->toBeString()->not->toBe('');
});
