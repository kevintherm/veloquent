<?php

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Enums\IndexType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Requests\StoreCollectionRequest;
use App\Domain\Collections\Requests\UpdateCollectionRequest;
use App\Domain\Collections\ValueObjects\Index;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

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

it('rejects non-indexable field types in store request indexes', function () {
    $payload = [
        'name' => 'logs',
        'type' => CollectionType::Base->value,
        'description' => 'Logs',
        'fields' => [
            ['name' => 'payload', 'type' => CollectionFieldType::Json->value],
        ],
        'indexes' => [
            ['columns' => ['payload'], 'type' => IndexType::Index->value],
        ],
    ];

    $request = StoreCollectionRequest::create('/api/collections', 'POST', $payload);

    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('indexes.0.columns.0'))->toBeTrue();
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
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('indexes.0.name'))->toBeTrue();
});

it('uses existing fields when validating update request indexes', function () {
    $collection = new Collection;
    $collection->type = CollectionType::Base;
    $collection->fields = [
        ['id' => 'aa11aa11', 'name' => 'metadata', 'type' => CollectionFieldType::Json->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ];

    $payload = [
        'indexes' => [
            ['name' => 'idx_metadata', 'columns' => ['metadata'], 'type' => IndexType::Index->value],
        ],
    ];

    $request = UpdateCollectionRequest::create('/api/collections/test', 'PATCH', $payload);
    $matchedRoute = Route::getRoutes()->match($request);
    $matchedRoute->setParameter('collection', $collection);

    $request->setRouteResolver(fn () => $matchedRoute);

    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('indexes.0.name'))->toBeTrue();
    expect($validator->errors()->has('indexes.0.columns.0'))->toBeTrue();
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
                'max_select' => 5,
            ],
        ],
        'indexes' => [],
    ];

    $request = StoreCollectionRequest::create('/api/collections', 'POST', $payload);

    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();
});

it('rejects changing existing field type in update request', function () {
    $collection = Collection::create([
        'name' => 'events',
        'type' => CollectionType::Base,
        'description' => 'Events',
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

    $payload = [
        'fields' => [
            ['id' => 'aa11aa11', 'name' => 'title', 'type' => CollectionFieldType::Number->value],
        ],
    ];

    $request = UpdateCollectionRequest::create('/api/collections/events', 'PATCH', $payload);
    $matchedRoute = Route::getRoutes()->match($request);
    $matchedRoute->setParameter('collection', $collection);
    $request->setRouteResolver(fn () => $matchedRoute);

    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('fields.0.type'))->toBeTrue();
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

    $payload = [
        'fields' => [
            $reservedDefinitions['id'],
            ['name' => 'title', 'type' => CollectionFieldType::Number->value],
            $reservedDefinitions['created_at'],
            $reservedDefinitions['updated_at'],
        ],
    ];

    $request = UpdateCollectionRequest::create('/api/collections/events_archive', 'PATCH', $payload);
    $matchedRoute = Route::getRoutes()->match($request);
    $matchedRoute->setParameter('collection', $collection);
    $request->setRouteResolver(fn () => $matchedRoute);

    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();
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
