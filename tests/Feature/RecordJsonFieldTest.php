<?php

use Veloquent\Core\Domain\Collections\Actions\CreateCollectionAction;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/**
 * Helper to create a collection with JSON fields for testing.
 */
function createJsonTestCollection(array $fields): Collection
{
    return app(CreateCollectionAction::class)->execute([
        'name' => 'json_'.Str::lower(Str::random(8)),
        'type' => CollectionType::Base->value,
        'fields' => $fields,
        'api_rules' => [
            'list' => '', 'create' => '', 'view' => '', 'update' => '', 'delete' => '',
        ],
    ]);
}

it('preserves JSON object vs array distinction (Intent Preservation)', function () {
    $collection = createJsonTestCollection([
        ['name' => 'payload', 'type' => CollectionFieldType::Json->value, 'nullable' => true],
    ]);

    // Scenario 1: Empty Array -> remains []
    postJson("/api/collections/{$collection->id}/records", [
        'payload' => []
    ])->assertCreated()->assertJsonPath('data.payload', []);

    // Scenario 2: Associative Array -> remains object
    postJson("/api/collections/{$collection->id}/records", [
        'payload' => ['foo' => 'bar']
    ])->assertCreated()->assertJsonPath('data.payload.foo', 'bar');

    // Scenario 3: Valid JSON string -> decoded and stored
    postJson("/api/collections/{$collection->id}/records", [
        'payload' => '{"baz": "qux"}'
    ])->assertCreated()->assertJsonPath('data.payload.baz', 'qux');

    // Scenario 4: Empty Object String -> preserved as {}
    $response = postJson("/api/collections/{$collection->id}/records", [
        'payload' => '{}'
    ])->assertCreated();
    
    expect($response->getContent())->toContain('"payload":{}');
});

it('validates JSON string format before storage', function () {
    $collection = createJsonTestCollection([
        ['name' => 'payload', 'type' => CollectionFieldType::Json->value, 'nullable' => true],
    ]);

    postJson("/api/collections/{$collection->id}/records", [
        'payload' => '{invalid: json}'
    ])->assertStatus(422);
});

it('allows empty structures in non-nullable JSON fields', function () {
    $collection = createJsonTestCollection([
        ['name' => 'payload', 'type' => CollectionFieldType::Json->value, 'nullable' => false],
    ]);

    // Should allow empty array
    postJson("/api/collections/{$collection->id}/records", [
        'payload' => []
    ])->assertCreated();

    // Should allow empty object string
    postJson("/api/collections/{$collection->id}/records", [
        'payload' => '{}'
    ])->assertCreated();

    // Should still fail on actual null
    postJson("/api/collections/{$collection->id}/records", [
        'payload' => null
    ])->assertStatus(422);
});
