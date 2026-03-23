<?php

use App\Domain\Auth\Models\Superuser;
use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Domain\SchemaManagement\Enums\SchemaOperation;
use App\Domain\SchemaManagement\Models\SchemaJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\mock;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Authenticate as a superuser by mocking the TokenAuthService
    $superuser = Superuser::factory()->create();

    // Create a Record that acts as the superuser
    $record = Record::fromTable('superusers');
    $record->forceFill($superuser->toArray());
    $record->exists = true;

    $mock = mock(TokenAuthService::class);
    $mock->shouldReceive('authenticate')->andReturn($record);
    $mock->shouldReceive('extractTokenFromRequest')->andReturn('test-token');

    $this->withHeaders(['Authorization' => 'Bearer test-token']);
});

function createTestCollection(string $name = 'test_collection'): Collection
{
    return app(CreateCollectionAction::class)->execute([
        'name' => $name,
        'type' => CollectionType::Base->value,
        'description' => 'Test collection',
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false],
        ],
    ]);
}

it('detects corrupt schema on update and returns 409', function () {
    $collection = createTestCollection();

    // Manually create a corrupt state (leftover schema job)
    SchemaJob::create([
        'collection_id' => $collection->id,
        'operation' => SchemaOperation::Update,
        'table_name' => $collection->getPhysicalTableName(),
        'started_at' => now(),
    ]);

    putJson("/api/collections/{$collection->id}", [
        'name' => 'updated_name',
        'fields' => $collection->fields,
    ])
        ->assertStatus(409)
        ->assertJson([
            'error_type' => 'SCHEMA_CORRUPT',
            'activity' => 'update',
            'collection_id' => $collection->id,
        ]);
});

it('recovers from failed update by rebuilding the table', function () {
    $collection = createTestCollection();
    $tableName = $collection->getPhysicalTableName();

    // Simulate corruption: job exists but table is missing or mismatched
    SchemaJob::create([
        'collection_id' => $collection->id,
        'operation' => SchemaOperation::Update,
        'table_name' => $tableName,
        'started_at' => now(),
    ]);

    Schema::drop($tableName);
    expect(Schema::hasTable($tableName))->toBeFalse();

    postJson("/api/collections/{$collection->id}/recover")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Collection schema recovered successfully.');

    expect(Schema::hasTable($tableName))->toBeTrue();
    expect(SchemaJob::where('collection_id', $collection->id)->exists())->toBeFalse();
});

it('recovers from failed create by dropping the table', function () {
    $collectionId = 'test-id';
    $tableName = '_velo_failed_create';

    // Create a temporary collection record first (without corruption yet)
    $collection = new Collection;
    $collection->id = $collectionId;
    $collection->name = 'failed_create';
    $collection->table_name = 'failed_create';
    $collection->type = CollectionType::Base;
    $collection->save();

    // NOW simulate corruption: job exists for a create operation
    SchemaJob::create([
        'collection_id' => $collectionId,
        'operation' => SchemaOperation::Create,
        'table_name' => $tableName,
        'started_at' => now(),
    ]);

    Schema::create($tableName, function ($table) {
        $table->id();
    });

    postJson("/api/collections/{$collection->id}/recover")
        ->assertSuccessful();

    expect(Schema::hasTable($tableName))->toBeFalse();
    expect(SchemaJob::where('collection_id', $collectionId)->exists())->toBeFalse();
    expect(Collection::find($collectionId))->toBeNull();
});

it('lists orphan tables', function () {
    $prefix = config('velo.collection_prefix', '_velo_');
    $orphanTable = "{$prefix}orphan_table";

    Schema::create($orphanTable, function ($table) {
        $table->id();
    });

    getJson('/api/schema/orphans')
        ->assertSuccessful()
        ->assertJsonFragment([$orphanTable]);
});

it('drops a specific orphan table', function () {
    $prefix = config('velo.collection_prefix', '_velo_');
    $orphanTable = "{$prefix}orphan_to_drop";

    Schema::create($orphanTable, function ($table) {
        $table->id();
    });

    deleteJson("/api/schema/orphans/{$orphanTable}")
        ->assertSuccessful();

    expect(Schema::hasTable($orphanTable))->toBeFalse();
});

it('drops all orphan tables', function () {
    $prefix = config('velo.collection_prefix', '_velo_');
    $orphan1 = "{$prefix}orphan1";
    $orphan2 = "{$prefix}orphan2";

    Schema::create($orphan1, function ($table) {
        $table->id();
    });
    Schema::create($orphan2, function ($table) {
        $table->id();
    });

    deleteJson('/api/schema/orphans')
        ->assertSuccessful();

    expect(Schema::hasTable($orphan1))->toBeFalse();
    expect(Schema::hasTable($orphan2))->toBeFalse();
});
