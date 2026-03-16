<?php

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;

it('assigns unique ids to fields via mergeWithSystemFields', function () {
    $userFields = [
        ['name' => 'title', 'type' => CollectionFieldType::Text->value],
        ['name' => 'body', 'type' => CollectionFieldType::LongText->value],
    ];

    $merged = SchemaChangePlan::mergeWithSystemFields($userFields, false);

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

    $merged = SchemaChangePlan::mergeWithSystemFields($userFields, false);
    $titleField = collect($merged)->firstWhere('name', 'title');

    expect($titleField['id'])->toBe('abc12345');
});

it('throws on duplicate field names in buildPlan', function () {
    $before = [];
    $after = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'bbb22222', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];

    SchemaChangePlan::buildPlan($before, $after);
})->throws(LogicException::class, "Duplicate field name 'title' detected.");

it('rejects all type changes', function () {
    $before = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];
    $after = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::LongText->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];

    SchemaChangePlan::buildPlan($before, $after);
})->throws(LogicException::class, 'Field type cannot be changed');

it('detects renames via same id different name', function () {
    $before = [
        ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];
    $after = [
        ['id' => 'aaa11111', 'name' => 'heading', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null],
    ];

    $plan = SchemaChangePlan::buildPlan($before, $after);

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

    $plan = SchemaChangePlan::buildPlan($before, $after);

    expect($plan->modifies)->toHaveCount(1)
        ->and($plan->renames)->toBeEmpty()
        ->and($plan->adds)->toBeEmpty()
        ->and($plan->drops)->toBeEmpty();
});

it('throws when user-submitted fields use reserved names', function () {
    $userFields = [
        ['name' => 'email', 'type' => CollectionFieldType::Email->value],
    ];

    SchemaChangePlan::mergeWithSystemFields($userFields, true);
})->throws(LogicException::class, "Reserved field 'email' cannot be defined manually.");

it('allows reordering without error in builds', function () {
    $fieldA = ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null, 'order' => 0];
    $fieldB = ['id' => 'aaa11111', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null, 'order' => 5];

    $plan = SchemaChangePlan::buildPlan([$fieldA], [$fieldB]);

    expect($plan->modifies)->toBeEmpty()
        ->and($plan->adds)->toBeEmpty()
        ->and($plan->drops)->toBeEmpty()
        ->and($plan->renames)->toBeEmpty();
});

it('strips order from fields in stripForDDL but preserves id', function () {
    $fields = [
        ['id' => 'abc12345', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false, 'default' => null, 'order' => 2],
    ];

    $stripped = SchemaChangePlan::stripForDDL($fields);

    expect($stripped[0])->toHaveKey('id')
        ->and($stripped[0])->not->toHaveKey('order');
});
