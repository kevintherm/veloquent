<?php

namespace Veloquent\Core\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Veloquent\Core\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\SchemaManagement\Support\PivotTableName;
use Veloquent\Core\Domain\SchemaManagement\Services\CollectionSyncService;

class ManyToManyTypeChangeTest extends TestCase
{
    public function test_it_creates_pivot_table_when_field_type_changes_to_relation_many()
    {
        Gate::before(fn () => true);

        $syncService = app(CollectionSyncService::class);

        // 1. Create a target collection
        $targetCollection = $syncService->create([
            'name' => 'tags',
            'api_rules' => ['create' => ''],
            'fields' => [
                ['name' => 'title', 'type' => 'text']
            ]
        ]);

        // 2. Create a source collection with a regular text field
        $sourceCollection = $syncService->create([
            'name' => 'posts',
            'api_rules' => ['create' => ''],
            'fields' => [
                ['id' => 'field_1', 'name' => 'tags_list', 'type' => 'text']
            ]
        ]);

        // 3. Update the collection to change the field type to relation_many
        $syncService->update($sourceCollection, [
            'fields' => [
                [
                    'id' => 'field_1',
                    'name' => 'tags', // Also rename to make it more realistic
                    'type' => 'relation_many',
                    'target_collection_id' => $targetCollection->id
                ]
            ]
        ]);

        // 4. Verify pivot table exists and has correct columns
        $pivotTable = PivotTableName::for($sourceCollection->getPhysicalTableName(), $targetCollection->getPhysicalTableName(), 'tags');
        
        $this->assertTrue(Schema::hasTable($pivotTable), "Pivot table '{$pivotTable}' should exist.");
        $this->assertTrue(Schema::hasColumn($pivotTable, 'source_id'), "Pivot table should have 'source_id' column.");
        $this->assertTrue(Schema::hasColumn($pivotTable, 'target_id'), "Pivot table should have 'target_id' column.");

        // 5. Verify record creation with many-to-many data
        $createAction = app(\Veloquent\Core\Domain\Records\Actions\CreateRecordAction::class);
        
        $tag1 = Record::of($targetCollection)->create(['title' => 'Tag 1']);
        $tag2 = Record::of($targetCollection)->create(['title' => 'Tag 2']);

        $post = $createAction->execute($sourceCollection, [
            'tags' => [$tag1->id, $tag2->id]
        ]);

        $this->assertCount(2, DB::table($pivotTable)->where('source_id', $post->id)->get());
    }

    public function test_it_repairs_pivot_table_if_columns_are_missing()
    {
        Gate::before(fn () => true);
        $syncService = app(CollectionSyncService::class);

        $targetCollection = $syncService->create([
            'name' => 'categories',
            'api_rules' => ['create' => ''],
            'fields' => [['name' => 'title', 'type' => 'text']]
        ]);

        $sourceCollection = $syncService->create([
            'name' => 'products',
            'api_rules' => ['create' => ''],
            'fields' => [['name' => 'name', 'type' => 'text']]
        ]);

        // Manually create a "broken" pivot table with just an ID
        $pivotTable = PivotTableName::for($sourceCollection->getPhysicalTableName(), $targetCollection->getPhysicalTableName(), 'categories');
        Schema::create($pivotTable, function ($blueprint) {
            $blueprint->ulid('id')->primary();
        });

        $this->assertTrue(Schema::hasTable($pivotTable));
        $this->assertFalse(Schema::hasColumn($pivotTable, 'source_id'));

        // Update collection to add the relation many field
        $syncService->update($sourceCollection, [
            'fields' => [
                ['name' => 'name', 'type' => 'text'],
                [
                    'name' => 'categories',
                    'type' => 'relation_many',
                    'target_collection_id' => $targetCollection->id
                ]
            ]
        ]);

        // Verify pivot table is repaired
        $this->assertTrue(Schema::hasColumn($pivotTable, 'source_id'), "Pivot table should have been repaired with 'source_id'.");
        $this->assertTrue(Schema::hasColumn($pivotTable, 'target_id'), "Pivot table should have been repaired with 'target_id'.");
        
        // Verify it works
        $cat = Record::of($targetCollection)->create(['title' => 'Cat 1']);
        $prod = app(\Veloquent\Core\Domain\Records\Actions\CreateRecordAction::class)->execute($sourceCollection, [
            'name' => 'Prod 1',
            'categories' => [$cat->id]
        ]);

        $this->assertCount(1, DB::table($pivotTable)->where('source_id', $prod->id)->get());
    }

    public function test_it_removes_old_pivot_table_when_retargeting()
    {
        Gate::before(fn () => true);
        $syncService = app(CollectionSyncService::class);

        $targetA = $syncService->create([
            'name' => 'target_a',
            'api_rules' => ['create' => ''],
            'fields' => [['name' => 'title', 'type' => 'text']]
        ]);

        $targetB = $syncService->create([
            'name' => 'target_b',
            'api_rules' => ['create' => ''],
            'fields' => [['name' => 'title', 'type' => 'text']]
        ]);

        $source = $syncService->create([
            'name' => 'source_col',
            'api_rules' => ['create' => ''],
            'fields' => [
                [
                    'id' => 'field_id',
                    'name' => 'relation',
                    'type' => 'relation_many',
                    'target_collection_id' => $targetA->id
                ]
            ]
        ]);

        $oldPivotTable = PivotTableName::for($source->getPhysicalTableName(), $targetA->getPhysicalTableName(), 'relation');
        $this->assertTrue(Schema::hasTable($oldPivotTable));

        // Retarget to B
        $syncService->update($source, [
            'fields' => [
                [
                    'id' => 'field_id',
                    'name' => 'relation',
                    'type' => 'relation_many',
                    'target_collection_id' => $targetB->id
                ]
            ]
        ]);

        $newPivotTable = PivotTableName::for($source->getPhysicalTableName(), $targetB->getPhysicalTableName(), 'relation');

        $this->assertFalse(Schema::hasTable($oldPivotTable), "Old pivot table should be dropped.");
        $this->assertTrue(Schema::hasTable($newPivotTable), "New pivot table should be created.");
    }

    public function test_it_syncs_extra_pivot_columns_on_update()
    {
        Gate::before(fn () => true);
        $syncService = app(CollectionSyncService::class);

        $target = $syncService->create([
            'name' => 'tags',
            'api_rules' => ['create' => ''],
            'fields' => [['name' => 'title', 'type' => 'text']]
        ]);

        $source = $syncService->create([
            'name' => 'posts',
            'api_rules' => ['create' => ''],
            'fields' => [
                [
                    'id' => 'field_id',
                    'name' => 'tags',
                    'type' => 'relation_many',
                    'target_collection_id' => $target->id
                ]
            ]
        ]);

        $pivotTable = PivotTableName::for($source->getPhysicalTableName(), $target->getPhysicalTableName(), 'tags');
        $this->assertFalse(Schema::hasColumn($pivotTable, 'notes'));

        // Update to add extra column
        $syncService->update($source, [
            'fields' => [
                [
                    'id' => 'field_id',
                    'name' => 'tags',
                    'type' => 'relation_many',
                    'target_collection_id' => $target->id,
                    'pivot_fields' => [
                        ['name' => 'notes', 'type' => 'text']
                    ]
                ]
            ]
        ]);

        $this->assertTrue(Schema::hasColumn($pivotTable, 'notes'), "Extra pivot column 'notes' should be created.");
    }

    public function test_it_validates_reserved_pivot_field_names()
    {
        $validator = app(\Veloquent\Core\Domain\Collections\Validators\CollectionValidator::class);

        $result = $validator->validateCreate([
            [
                'name' => 'tags',
                'type' => 'relation_many',
                'target_collection_id' => 'some-id',
                'pivot_fields' => [
                    ['name' => 'source_id', 'type' => 'text']
                ]
            ]
        ], false);

        $this->assertArrayHasKey('fields.0.pivot_fields.0.name', $result->getErrors());
        $this->assertEquals("The pivot field name 'source_id' is reserved.", $result->getErrors()['fields.0.pivot_fields.0.name'][0]);
    }
}
