<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Models\Collection;
use App\Domain\SchemaManagement\Services\SchemaTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
