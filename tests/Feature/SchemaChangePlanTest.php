<?php

use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaChange;

it('assigns unique ids to fields via mergeWithSystemFields', function () {
    $userFields = [
        ['name' => 'title', 'type' => CollectionFieldType::Text->value],
        ['name' => 'body', 'type' => CollectionFieldType::LongText->value],
    ];

    $merged = SchemaChange::mergeWithSystemFields($userFields, false);

    foreach ($merged as $field) {
        expect($field)->toHaveKey('id')
            ->and($field['id'])->toBeString()->toHaveLength(8);
    }

    $ids = array_column($merged, 'id');
    expect(array_unique($ids))->toHaveCount(count($ids));
});

it('preserves existing ids on re-merge', function () {
    $userFields = [
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'id' => 'abc12345'],
    ];

    $merged = SchemaChange::mergeWithSystemFields($userFields, false);
    $titleField = collect($merged)->firstWhere('name', 'title');

    expect($titleField['id'])->toBe('abc12345');
});

it('treats duplicate names as independent additions in pure diff', function () {
    $before = [];
    $after = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'bbb22222', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];

    $plan = SchemaChange::diff($before, $after);

    expect($plan->adds)->toHaveCount(2);
});

it('builds modify entries for type changes and defers validation to caller', function () {
    $before = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];
    $after = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::LongText->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];

    $plan = SchemaChange::diff($before, $after);

    expect($plan->modifies)->toHaveCount(1);
});

it('detects renames via same id different name', function () {
    $before = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];
    $after = [
        ['id' => 'aaa11111', 'name' => 'heading', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];

    $plan = SchemaChange::diff($before, $after);

    expect($plan->renames)->toHaveCount(1)
        ->and($plan->renames[0])->toBe(['title', 'heading']);
});

it('detects attribute-only modifications', function () {
    $before = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];
    $after = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => true, 'unique' => false, 'default' => null],
    ];

    $plan = SchemaChange::diff($before, $after);

    expect($plan->modifies)->toHaveCount(1)
        ->and($plan->renames)->toBeEmpty()
        ->and($plan->adds)->toBeEmpty()
        ->and($plan->drops)->toBeEmpty();
});


it('allows reordering without error in builds', function () {
    $fieldA = ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null, 'order' => 0];
    $fieldB = ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null, 'order' => 5];

    $plan = SchemaChange::diff([$fieldA], [$fieldB]);

    expect($plan->modifies)->toBeEmpty()
        ->and($plan->adds)->toBeEmpty()
        ->and($plan->drops)->toBeEmpty()
        ->and($plan->renames)->toBeEmpty();
});

it('strips order from fields in stripForDDL but preserves id', function () {
    $fields = [
        ['id' => 'abc12345', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null, 'order' => 2],
    ];

    $stripped = SchemaChange::stripForDDL($fields);

    expect($stripped[0])->toHaveKey('id')
        ->and($stripped[0])->not->toHaveKey('order');
});

it('assigns unique ids to pivot fields via mergeWithSystemFields', function () {
    $userFields = [
        [
            'name' => 'posts',
            'type' => CollectionFieldType::RelationMany->value,
            'target_collection_id' => 'abc',
            'pivot_fields' => [
                ['name' => 'role', 'type' => 'text'],
            ]
        ],
    ];

    $merged = SchemaChange::mergeWithSystemFields($userFields, false);
    $pivotField = collect($merged)->firstWhere('name', 'posts');

    expect($pivotField['pivot_fields'][0])->toHaveKey('id')
        ->and($pivotField['pivot_fields'][0]['id'])->toBeString()->toHaveLength(8);
});

it('detects renames in pivot fields via diffColumns', function () {
    $before = [
        [
            'id' => 'p1',
            'name' => 'posts',
            'type' => CollectionFieldType::RelationMany->value,
            'pivot_fields' => [
                ['id' => 'pf1', 'name' => 'role', 'type' => 'text'],
            ]
        ],
    ];
    $after = [
        [
            'id' => 'p1',
            'name' => 'posts',
            'type' => CollectionFieldType::RelationMany->value,
            'pivot_fields' => [
                ['id' => 'pf1', 'name' => 'position', 'type' => 'text'],
            ]
        ],
    ];

    $plan = SchemaChange::diff($before, $after);

    expect($plan->pivotModifies)->toHaveCount(1);
    $mod = $plan->pivotModifies[0];
    expect($mod['changes']->renames)->toHaveCount(1)
        ->and($mod['changes']->renames[0])->toBe(['role', 'position']);
});
