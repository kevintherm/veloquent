<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Enums\IndexType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Requests\StoreCollectionRequest;
use App\Domain\Collections\Requests\UpdateCollectionRequest;
use App\Domain\Collections\Validators\CollectionFieldValidator;
use App\Domain\Collections\ValueObjects\Index;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('syncs declared single, composite, and unique indexes on create', function () {
    $collection = Collection::create([
        'name' => 'articles',
        'type' => CollectionType::Base,
        'description' => 'Articles',
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
            ['id' => 'bb22bb22', 'name' => 'slug', 'type' => CollectionFieldType::Text->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null],
            ['id' => 'cc33cc33', 'name' => 'status', 'type' => CollectionFieldType::Text->value, 'order' => 2, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
        'indexes' => [
            ['columns' => ['title'], 'type' => IndexType::Index->value],
            ['columns' => ['slug', 'status'], 'type' => IndexType::Index->value],
            ['columns' => ['slug'], 'type' => IndexType::Unique->value],
        ],
    ]);

    $table = $collection->getPhysicalTableName();
    $indexNames = collect(Schema::getIndexes($table))->pluck('name')->all();

    expect($indexNames)->toContain(Index::generateIndexName($table, ['title'], IndexType::Index->value));
    expect($indexNames)->toContain(Index::generateIndexName($table, ['slug', 'status'], IndexType::Index->value));
    expect($indexNames)->toContain(Index::generateIndexName($table, ['slug'], IndexType::Unique->value));
});

it('renames and drops index metadata when fields are renamed or dropped', function () {
    $collection = Collection::create([
        'name' => 'tickets',
        'type' => CollectionType::Base,
        'description' => 'Tickets',
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'code', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
            ['id' => 'bb22bb22', 'name' => 'state', 'type' => CollectionFieldType::Text->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
        'indexes' => [
            ['columns' => ['code'], 'type' => IndexType::Index->value],
            ['columns' => ['code', 'state'], 'type' => IndexType::Index->value],
        ],
    ]);

    $collection->update([
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'ticket_code', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
    ]);

    $collection->refresh();

    expect($collection->indexes)->toHaveCount(1);
    expect($collection->indexes[0]['columns'])->toBe(['ticket_code']);

    $table = $collection->getPhysicalTableName();
    $indexNames = collect(Schema::getIndexes($table))->pluck('name')->all();

    expect($indexNames)->toContain(Index::generateIndexName($table, ['ticket_code'], IndexType::Index->value));
    expect($indexNames)->not->toContain(Index::generateIndexName($table, ['code'], IndexType::Index->value));
    expect($indexNames)->not->toContain(Index::generateIndexName($table, ['code', 'state'], IndexType::Index->value));
});

it('keeps existing composite index when only column order changes in payload', function () {
    $collection = Collection::create([
        'name' => 'audit_trails',
        'type' => CollectionType::Base,
        'description' => 'Audit trails',
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'user_identifier', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
            ['id' => 'bb22bb22', 'name' => 'event_identifier', 'type' => CollectionFieldType::Text->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null],
            ['id' => 'cc33cc33', 'name' => 'session_identifier', 'type' => CollectionFieldType::Text->value, 'order' => 2, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
        'indexes' => [
            ['columns' => ['user_identifier', 'event_identifier', 'session_identifier'], 'type' => IndexType::Index->value],
        ],
    ]);

    $table = $collection->getPhysicalTableName();
    $originalName = Index::generateIndexName($table, ['user_identifier', 'event_identifier', 'session_identifier'], IndexType::Index->value);

    $collection->update([
        'indexes' => [
            ['columns' => ['session_identifier', 'event_identifier', 'user_identifier'], 'type' => IndexType::Index->value],
        ],
    ]);

    $indexNames = collect(Schema::getIndexes($table))
        ->pluck('name')
        ->filter(fn (mixed $name): bool => is_string($name) && str_starts_with($name, $table.'_'))
        ->values()
        ->all();

    expect($indexNames)->toContain($originalName)
        ->and($indexNames)->toHaveCount(1);
});

it('rejects non-indexable field types in semantic validator', function () {
    $validator = app(CollectionFieldValidator::class);

    $payloadFields = [
        ['name' => 'payload', 'type' => CollectionFieldType::Json->value],
    ];

    $payloadIndexes = [
        ['columns' => ['payload'], 'type' => IndexType::Index->value],
    ];

    expect(fn () => $validator->validateForCreate($payloadFields, $payloadIndexes, false))
        ->toThrow(ValidationException::class);
});

it('rejects providing index name in store request', function () {
    $payload = [
        'name' => 'products',
        'type' => CollectionType::Base->value,
        'description' => 'Products',
        'fields' => [
            ['name' => 'sku', 'type' => CollectionFieldType::Text->value],
        ],
        'indexes' => [
            ['name' => 'idx_sku', 'columns' => ['sku'], 'type' => IndexType::Index->value],
        ],
    ];

    $request = StoreCollectionRequest::create('/api/collections', 'POST', $payload);

    $validator = Validator::make($payload, $request->rules());
    $request->setValidator($validator);
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('indexes.0.name'))->toBeTrue();
});

it('accepts but ignores fields unique flag in store request', function () {
    $payload = [
        'name' => 'profiles_unique_flag',
        'type' => CollectionType::Base->value,
        'description' => 'Profiles',
        'fields' => [
            ['name' => 'email', 'type' => CollectionFieldType::Email->value, 'unique' => true],
        ],
        'indexes' => [],
    ];

    $request = StoreCollectionRequest::create('/api/collections', 'POST', $payload);

    $validator = Validator::make($payload, $request->rules());
    $request->setValidator($validator);
    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();

    $fields = $request->getFields();

    expect($fields[0])->not->toHaveKey('unique');
});

it('rejects unknown index columns in semantic validator', function () {
    $validator = app(CollectionFieldValidator::class);

    $incomingFields = [
        ['id' => 'aa11aa11', 'name' => 'title', 'type' => CollectionFieldType::Text->value],
    ];

    $incomingIndexes = [
        ['columns' => ['missing'], 'type' => IndexType::Index->value],
    ];

    expect(fn () => $validator->validateForUpdate($incomingFields, $incomingFields, $incomingIndexes, false))
        ->toThrow(ValidationException::class);
});

it('allows extra field options for non-relation fields', function () {
    $payload = [
        'name' => 'notes',
        'type' => CollectionType::Base->value,
        'description' => 'Notes',
        'fields' => [
            [
                'name' => 'title',
                'type' => CollectionFieldType::Text->value,
                'target_collection_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ],
        ],
        'indexes' => [],
    ];

    $request = StoreCollectionRequest::create('/api/collections', 'POST', $payload);

    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();
});

it('rejects changing existing field type in semantic validator', function () {
    $storedField = ['id' => 'aa11aa11', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null];

    $collection = Collection::create([
        'name' => 'events',
        'type' => CollectionType::Base,
        'description' => 'Events',
        'fields' => [
            $storedField,
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

    $incomingFields = [
        ['id' => 'aa11aa11', 'name' => 'title', 'type' => CollectionFieldType::Number->value],
    ];

    $storedFields = [$storedField];

    $validator = app(CollectionFieldValidator::class);

    expect(fn () => $validator->validateForUpdate($incomingFields, $storedFields, [], false))
        ->toThrow(ValidationException::class);
});

it('allows recreating a deleted field with the same name and a different type', function () {
    $collection = Collection::create([
        'name' => 'events_archive',
        'type' => CollectionType::Base,
        'description' => 'Events archive',
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

    $reservedDefinitions = SchemaChangePlan::getReservedFieldDefinitions();
    $idField = collect($reservedDefinitions['id'])->except(['unique'])->all();
    $createdAtField = collect($reservedDefinitions['created_at'])->except(['unique'])->all();
    $updatedAtField = collect($reservedDefinitions['updated_at'])->except(['unique'])->all();

    $payload = [
        'fields' => [
            $idField,
            ['name' => 'title', 'type' => CollectionFieldType::Number->value],
            $createdAtField,
            $updatedAtField,
        ],
    ];

    $request = UpdateCollectionRequest::create('/api/collections/events_archive', 'PATCH', $payload);
    $matchedRoute = Route::getRoutes()->match($request);
    $matchedRoute->setParameter('collection', $collection);
    $request->setRouteResolver(fn () => $matchedRoute);

    $validator = Validator::make($payload, $request->rules());
    $request->setValidator($validator);
    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();
});

it('accepts but ignores fields unique flag in update request', function () {
    $collection = Collection::create([
        'name' => 'people_unique_flag',
        'type' => CollectionType::Base,
        'description' => 'People',
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'email', 'type' => CollectionFieldType::Email->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
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

    $payload = [
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'email', 'type' => CollectionFieldType::Email->value, 'unique' => true],
        ],
    ];

    $request = UpdateCollectionRequest::create('/api/collections/people_unique_flag', 'PATCH', $payload);
    $matchedRoute = Route::getRoutes()->match($request);
    $matchedRoute->setParameter('collection', $collection);
    $request->setRouteResolver(fn () => $matchedRoute);

    $validator = Validator::make($payload, $request->rules());
    $request->setValidator($validator);
    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();

    $fields = $request->getFields();

    expect($fields[0])->not->toHaveKey('unique');
});

it('syncs fields unique metadata from declared unique indexes', function () {
    $collection = Collection::create([
        'name' => 'profiles',
        'type' => CollectionType::Base,
        'description' => 'Profiles',
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'username', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
            ['id' => 'bb22bb22', 'name' => 'bio', 'type' => CollectionFieldType::Text->value, 'order' => 1, 'nullable' => false, 'unique' => true, 'default' => null],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
        'indexes' => [
            ['columns' => ['username'], 'type' => IndexType::Unique->value],
        ],
    ]);

    $collection->refresh();
    $fieldMap = collect($collection->fields)->keyBy('name');

    expect($fieldMap['username']['unique'])->toBeTrue();
    expect($fieldMap['bio']['unique'])->toBeFalse();

    $collection->update([
        'indexes' => [],
    ]);

    $collection->refresh();
    $fieldMap = collect($collection->fields)->keyBy('name');

    expect($fieldMap['username']['unique'])->toBeFalse();
});

it('does not mark fields unique from composite unique indexes', function () {
    $collection = Collection::create([
        'name' => 'user_handles',
        'type' => CollectionType::Base,
        'description' => 'User handles',
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'tenant', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
            ['id' => 'bb22bb22', 'name' => 'handle', 'type' => CollectionFieldType::Text->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
        'indexes' => [
            ['columns' => ['tenant', 'handle'], 'type' => IndexType::Unique->value],
        ],
    ]);

    $collection->refresh();
    $fieldMap = collect($collection->fields)->keyBy('name');

    expect($fieldMap['tenant']['unique'])->toBeFalse();
    expect($fieldMap['handle']['unique'])->toBeFalse();
});

it('syncs field unique metadata on index-only updates', function () {
    $collection = Collection::create([
        'name' => 'subscribers',
        'type' => CollectionType::Base,
        'description' => 'Subscribers',
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'email', 'type' => CollectionFieldType::Email->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
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

    $collection->update([
        'indexes' => [
            ['columns' => ['email'], 'type' => IndexType::Unique->value],
        ],
    ]);

    $collection->refresh();
    $fieldMap = collect($collection->fields)->keyBy('name');

    expect($fieldMap['email']['unique'])->toBeTrue();
});

it('syncs auth reserved email unique metadata from explicit unique index', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'members_auth',
        'type' => CollectionType::Auth,
        'description' => 'Members auth',
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'display_name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
        'api_rules' => [
            'list' => '',
            'view' => '',
            'create' => '',
            'update' => '',
            'delete' => '',
        ],
        'indexes' => [
            ['columns' => ['email'], 'type' => IndexType::Unique->value],
        ],
    ]);

    $collection->refresh();

    $storedIndexes = collect($collection->indexes)
        ->map(fn (mixed $index): array => is_array($index) ? $index : $index->toArray())
        ->values()
        ->all();

    expect($storedIndexes)->toContain([
        'columns' => ['email'],
        'type' => IndexType::Unique->value,
    ]);

    $table = $collection->getPhysicalTableName();
    $indexNames = collect(Schema::getIndexes($table))->pluck('name')->all();

    expect($indexNames)->toContain(Index::generateIndexName($table, ['email'], IndexType::Unique->value));
    expect($indexNames)->not->toContain(Index::generateIndexName($table, ['id'], IndexType::Unique->value));

    $fieldMap = collect($collection->fields)->keyBy('name');
    expect($fieldMap['email']['unique'])->toBeTrue();
});

it('keeps id field unique metadata always true', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'id_unique_lock',
        'type' => CollectionType::Base,
        'description' => 'ID unique lock',
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

    $collection->refresh();

    $updatedFields = collect($collection->fields)
        ->map(fn (mixed $field): array => is_array($field) ? $field : $field->toArray())
        ->map(fn (array $field): array => $field['name'] === 'id' ? [...$field, 'unique' => false] : $field)
        ->values()
        ->all();

    $collection->update([
        'fields' => $updatedFields,
    ]);

    $collection->refresh();
    $idField = collect($collection->fields)
        ->map(fn (mixed $field): array => is_array($field) ? $field : $field->toArray())
        ->firstWhere('name', 'id');

    expect($idField)->not->toBeNull();
    expect($idField['unique'] ?? null)->toBeTrue();
});
