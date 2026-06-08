<?php

use Veloquent\Core\Domain\Collections\Actions\CreateCollectionAction;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\SchemaManagement\Services\SchemaTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('excludes system collections from options list', function () {
    $service = app(SchemaTransferService::class);
    $createAction = app(CreateCollectionAction::class);

    // 1. Create a user collection
    $createAction->execute([
        'name' => 'posts',
        'type' => 'base',
        'fields' => [],
    ]);

    // 2. Create a system collection
    $createAction->execute([
        'name' => 'system_logs',
        'type' => 'base',
        'fields' => [],
        'is_system' => true,
    ]);

    $options = $service->options();

    $collections = collect($options['collections']);
    
    expect($collections->firstWhere('name', 'posts'))->not->toBeNull();
    expect($collections->firstWhere('name', 'system_logs'))->toBeNull();
});

it('exports collection metadata including relation fields', function () {
    $service = app(SchemaTransferService::class);
    $createAction = app(CreateCollectionAction::class);

    $posts = $createAction->execute([
        'name' => 'posts',
        'type' => 'base',
        'fields' => [],
        'api_rules' => [
            'list' => 'id = id',
            'create' => 'id = id',
            'view' => 'id = id',
            'update' => 'id = id',
            'delete' => 'id = id',
        ],
    ]);

    $createAction->execute([
        'name' => 'comments',
        'type' => 'base',
        'fields' => [
            [
                'name' => 'post',
                'type' => 'relation',
                'target_collection_id' => $posts->id,
            ],
        ],
        'api_rules' => [
            'list' => 'id = id',
            'create' => 'id = id',
            'view' => 'id = id',
            'update' => 'id = id',
            'delete' => 'id = id',
        ],
    ]);

    $export = $service->export(['comments'], [], false);
    $exportedComments = collect($export['metadata']['collections'])->firstWhere('name', 'comments');

    $relationField = collect($exportedComments['fields'])->firstWhere('name', 'post');
    expect($relationField)->toHaveKey('target_collection_id', $posts->id);
    expect($relationField)->not->toHaveKey('target_collection_name');
});

it('imports collection with relation fields and returns a warning', function () {
    $service = app(SchemaTransferService::class);

    $payload = [
        'version' => 1,
        'metadata' => [
            'collections' => [
                [
                    'name' => 'comments',
                    'type' => 'base',
                    'fields' => [
                        [
                            'name' => 'post',
                            'type' => 'relation',
                            'target_collection_id' => 'some-uuid-from-other-env',
                        ],
                    ],
                    'api_rules' => [
                        'list' => 'id = id',
                        'create' => 'id = id',
                        'view' => 'id = id',
                        'update' => 'id = id',
                        'delete' => 'id = id',
                    ],
                ],
            ],
        ],
    ];

    $result = $service->import($payload);
    $metadataRow = $result['metadata'][0];

    expect($metadataRow['action'])->toBe('created');
    expect($metadataRow)->toHaveKey('warning');
    expect($metadataRow['warning'])->toContain('relation fields');
});

it('merges existing reserved fields in overwrite mode for auth collections', function () {
    $service = app(SchemaTransferService::class);
    $createAction = app(CreateCollectionAction::class);

    // 1. Create a custom auth collection
    $authCol = $createAction->execute([
        'name' => 'custom_auth',
        'type' => 'auth',
        'fields' => [],
        'api_rules' => [
            'list' => 'id = id',
            'create' => 'id = id',
            'view' => 'id = id',
            'update' => 'id = id',
            'delete' => 'id = id',
            'manage' => 'id = id',
        ],
    ]);

    // 2. Payload with a new field but MISSING the reserved auth fields
    $payload = [
        'version' => 1,
        'metadata' => [
            'collections' => [
                [
                    'name' => 'custom_auth',
                    'type' => 'auth',
                    'fields' => [
                        ['name' => 'bio', 'type' => 'text'],
                    ],
                    'api_rules' => [
                        'list' => 'id = id',
                        'create' => 'id = id',
                        'view' => 'id = id',
                        'update' => 'id = id',
                        'delete' => 'id = id',
                        'manage' => 'id = id',
                    ],
                ],
            ],
        ],
    ];

    $service->import($payload, 'overwrite');

    $authCol->refresh();
    $fieldNames = collect($authCol->fields)->pluck('name');

    // Should have both the new field and the reserved ones
    expect($fieldNames)->toContain('bio');
    expect($fieldNames)->toContain('email');
    expect($fieldNames)->toContain('password');
});

it('applies API rules even in skip mode', function () {
    $service = app(SchemaTransferService::class);
    $createAction = app(CreateCollectionAction::class);

    $test = $createAction->execute([
        'name' => 'test',
        'type' => 'base',
        'fields' => [],
        'api_rules' => [
            'list' => 'id != id',
            'create' => 'id != id',
            'view' => 'id != id',
            'update' => 'id != id',
            'delete' => 'id != id',
        ],
    ]);

    $payload = [
        'version' => 1,
        'metadata' => [
            'collections' => [
                [
                    'name' => 'test',
                    'type' => 'base',
                    'fields' => [],
                    'api_rules' => [
                        'list' => 'id = id',
                        'create' => 'id = id',
                        'view' => 'id = id',
                        'update' => 'id = id',
                        'delete' => 'id = id',
                    ],
                ],
            ],
        ],
    ];

    // Skip mode should still update API rules
    $service->import($payload, 'skip');

    $test->refresh();
    expect($test->api_rules['list'])->toBe('id = id');
});

it('applies API rules without linting, even if they reference unknown fields', function () {
    $service = app(SchemaTransferService::class);

    $payload = [
        'version' => 1,
        'metadata' => [
            'collections' => [
                [
                    'name' => 'articles',
                    'type' => 'base',
                    'fields' => [],
                    'api_rules' => [
                        // Rules that would fail linting (unknown field from another env)
                        'list' => 'author_id = @user.id',
                        'create' => 'id = id',
                        'view' => 'id = id',
                        'update' => 'id = id',
                        'delete' => 'id = id',
                    ],
                ],
            ],
        ],
    ];

    // Should not throw, rules are saved directly bypassing the linter
    $service->import($payload);

    $articles = Collection::query()->where('name', 'articles')->first();
    expect($articles)->not->toBeNull();
    expect($articles->api_rules['list'])->toBe('author_id = @user.id');
});

it('updates target_collection_id during import when target collection is also imported', function () {
    $service = app(SchemaTransferService::class);
    $createAction = app(CreateCollectionAction::class);

    // 1. Create source environment state
    $posts = $createAction->execute([
        'name' => 'posts',
        'type' => 'base',
        'fields' => [],
    ]);

    $comments = $createAction->execute([
        'name' => 'comments',
        'type' => 'base',
        'fields' => [
            [
                'name' => 'post',
                'type' => 'relation',
                'target_collection_id' => $posts->id,
            ],
        ],
    ]);

    // 2. Export
    $export = $service->export(['posts', 'comments'], [], false);
    
    // Clear database to simulate fresh import
    $comments->delete();
    $posts->delete();
    
    // 3. Import into "new" environment
    // In the new environment, the IDs will be different
    $service->import($export);
    
    $newPosts = Collection::query()->where('name', 'posts')->first();
    $newComments = Collection::query()->where('name', 'comments')->first();
    
    expect($newPosts->id)->not->toBe($posts->id);
    
    $relationField = collect($newComments->fields)->firstWhere('name', 'post');
    expect($relationField['target_collection_id'])->toBe($newPosts->id);
});

it('updates target_collection_id for relation_many fields during import when target collection is also imported', function () {
    $service = app(SchemaTransferService::class);
    $createAction = app(CreateCollectionAction::class);

    // 1. Create source environment state
    $posts = $createAction->execute([
        'name' => 'posts',
        'type' => 'base',
        'fields' => [],
    ]);

    $comments = $createAction->execute([
        'name' => 'comments',
        'type' => 'base',
        'fields' => [
            [
                'name' => 'post_list',
                'type' => 'relation_many',
                'target_collection_id' => $posts->id,
            ],
        ],
    ]);

    // 2. Export
    $export = $service->export(['posts', 'comments'], [], false);
    
    // Clear database to simulate fresh import
    $comments->delete();
    $posts->delete();
    
    // 3. Import into "new" environment
    // In the new environment, the IDs will be different
    $service->import($export);
    
    $newPosts = Collection::query()->where('name', 'posts')->first();
    $newComments = Collection::query()->where('name', 'comments')->first();
    
    expect($newPosts->id)->not->toBe($posts->id);
    
    $relationField = collect($newComments->fields)->firstWhere('name', 'post_list');
    expect($relationField['target_collection_id'])->toBe($newPosts->id);
});

it('exports and imports relation_many fields and their pivot table records successfully', function () {
    $service = app(SchemaTransferService::class);
    $createAction = app(CreateCollectionAction::class);

    // 1. Create collections
    $tags = $createAction->execute([
        'name' => 'tags',
        'type' => 'base',
        'fields' => [
            ['name' => 'title', 'type' => 'text']
        ],
        'api_rules' => [
            'list' => 'id = id',
            'create' => 'id = id',
            'view' => 'id = id',
            'update' => 'id = id',
            'delete' => 'id = id',
        ],
    ]);

    $posts = $createAction->execute([
        'name' => 'posts',
        'type' => 'base',
        'fields' => [
            [
                'name' => 'tag_list',
                'type' => 'relation_many',
                'target_collection_id' => $tags->id,
            ],
        ],
        'api_rules' => [
            'list' => 'id = id',
            'create' => 'id = id',
            'view' => 'id = id',
            'update' => 'id = id',
            'delete' => 'id = id',
        ],
    ]);

    // 2. Create records
    $tagRecord1 = \Veloquent\Core\Domain\Records\Models\Record::of($tags)->create(['title' => 'Laravel']);
    $tagRecord2 = \Veloquent\Core\Domain\Records\Models\Record::of($tags)->create(['title' => 'PHP']);

    $postRecord = app(\Veloquent\Core\Domain\Records\Actions\CreateRecordAction::class)->execute($posts, [
        'tag_list' => [$tagRecord1->id, $tagRecord2->id]
    ]);

    $pivotTable = \Veloquent\Core\Domain\SchemaManagement\Support\PivotTableName::for('posts', 'tags', 'tag_list');
    expect(Illuminate\Support\Facades\Schema::hasTable($pivotTable))->toBeTrue();
    expect(Illuminate\Support\Facades\DB::table($pivotTable)->count())->toBe(2);

    // 3. Export
    $export = $service->export(['posts', 'tags'], [], true);
    
    // Clear database to simulate fresh import
    $postRecord->delete();
    $tagRecord1->delete();
    $tagRecord2->delete();
    $posts->delete();
    $tags->delete();
    
    expect(Illuminate\Support\Facades\Schema::hasTable($pivotTable))->toBeFalse();

    // 4. Import
    $service->import($export);
    
    $newTags = Collection::query()->where('name', 'tags')->first();
    $newPosts = Collection::query()->where('name', 'posts')->first();
    
    expect($newTags->id)->not->toBe($tags->id);
    expect($newPosts->id)->not->toBe($posts->id);
    
    $relationField = collect($newPosts->fields)->firstWhere('name', 'tag_list');
    expect($relationField['target_collection_id'])->toBe($newTags->id);

    $newPivotTable = \Veloquent\Core\Domain\SchemaManagement\Support\PivotTableName::for('posts', 'tags', 'tag_list');
    expect(Illuminate\Support\Facades\Schema::hasTable($newPivotTable))->toBeTrue();
    expect(Illuminate\Support\Facades\DB::table($newPivotTable)->count())->toBe(2);
});

it('imports collection with timestamp field and maps it to datetime', function () {
    $service = app(SchemaTransferService::class);

    $payload = [
        'version' => 1,
        'metadata' => [
            'collections' => [
                [
                    'name' => 'events',
                    'type' => 'base',
                    'fields' => [
                        [
                            'name' => 'occurred_at',
                            'type' => 'timestamp',
                        ],
                    ],
                ],
            ],
        ],
    ];

    $result = $service->import($payload);
    $metadataRow = $result['metadata'][0];

    expect($metadataRow['action'])->toBe('created');

    $collection = Collection::query()->where('name', 'events')->first();
    expect($collection)->not->toBeNull();
    
    $field = collect($collection->fields)->firstWhere('name', 'occurred_at');
    expect($field)->not->toBeNull();
    expect($field['type'])->toBe('datetime');
});



