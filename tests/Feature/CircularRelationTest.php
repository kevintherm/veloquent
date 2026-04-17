<?php

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function circularRelationApiRules(): array
{
    return [
        'list' => '',
        'view' => '',
        'create' => '',
        'update' => '',
        'delete' => '',
    ];
}

function circularCreateRelationCollection(string $name, array $fields): Collection
{
    return Collection::create([
        'name' => $name,
        'type' => CollectionType::Base,
        'is_system' => false,
        'description' => ucfirst($name),
        'fields' => $fields,
        'api_rules' => circularRelationApiRules(),
        'indexes' => [],
    ]);
}

it('prevents direct self-referencing on update', function () {
    $comments = circularCreateRelationCollection('comments', [
        ['id' => 'text_f', 'name' => 'text', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'rel_f', 'name' => 'parent_comment', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => true, 'unique' => false, 'default' => null, 'target_collection_id' => 'PLCHLDR', 'cascade_on_delete' => true],
    ]);

    $comments->update([
        'fields' => collect($comments->fields)->map(function ($f) use ($comments) {
            if ($f['name'] === 'parent_comment') {
                $f['target_collection_id'] = $comments->id;
            }

            return $f;
        })->all(),
    ]);

    $comment = Record::of($comments)->create(['text' => 'First comment']);

    patchJson("/api/collections/{$comments->id}/records/{$comment->id}", [
        'parent_comment' => $comment->id,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.parent_comment.0', "Circular reference detected: 'parent_comment' cannot reference the record itself.");
});

it('prevents multi-level circular relation updates (A -> B -> C -> A)', function () {
    $a = circularCreateRelationCollection('collection_a', [
        ['id' => 'f1', 'name' => 'rel_b', 'type' => CollectionFieldType::Relation->value, 'order' => 0, 'nullable' => true, 'unique' => false, 'default' => null, 'target_collection_id' => 'B_ID'],
    ]);

    $b = circularCreateRelationCollection('collection_b', [
        ['id' => 'f2', 'name' => 'rel_c', 'type' => CollectionFieldType::Relation->value, 'order' => 0, 'nullable' => true, 'unique' => false, 'default' => null, 'target_collection_id' => 'C_ID'],
    ]);

    $c = circularCreateRelationCollection('collection_c', [
        ['id' => 'f3', 'name' => 'rel_a', 'type' => CollectionFieldType::Relation->value, 'order' => 0, 'nullable' => true, 'unique' => false, 'default' => null, 'target_collection_id' => $a->id],
    ]);

    $a->update([
        'fields' => [['id' => 'f1', 'name' => 'rel_b', 'type' => CollectionFieldType::Relation->value, 'order' => 0, 'nullable' => true, 'unique' => false, 'default' => null, 'target_collection_id' => $b->id]],
    ]);

    $b->update([
        'fields' => [['id' => 'f2', 'name' => 'rel_c', 'type' => CollectionFieldType::Relation->value, 'order' => 0, 'nullable' => true, 'unique' => false, 'default' => null, 'target_collection_id' => $c->id]],
    ]);

    $recordA = Record::of($a)->create([]);
    $recordB = Record::of($b)->create([]);
    $recordC = Record::of($c)->create(['rel_a' => $recordA->id]);

    $recordB->update(['rel_c' => $recordC->id]);

    patchJson("/api/collections/{$a->id}/records/{$recordA->id}", [
        'rel_b' => $recordB->id,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.rel_b.0', 'Circular reference detected: following this relation creates an infinite loop.');
});

it('prevents creating a record with a pre-defined ID that creates a circular loop', function () {
    $comments = circularCreateRelationCollection('comments', [
        ['id' => 'text_f', 'name' => 'text', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'rel_f', 'name' => 'parent_comment', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => true, 'unique' => false, 'default' => null, 'target_collection_id' => 'PLCHLDR', 'cascade_on_delete' => true],
    ]);

    $comments->update([
        'fields' => collect($comments->fields)->map(function ($f) use ($comments) {
            if ($f['name'] === 'parent_comment') {
                $f['target_collection_id'] = $comments->id;
            }

            return $f;
        })->all(),
    ]);

    // Test a scenario where new record asserts its ID and self-references in creation
    $suppliedId = (string) Str::ulid();

    postJson("/api/collections/{$comments->id}/records", [
        'id' => $suppliedId,
        'text' => 'New comment with self reference',
        'parent_comment' => $suppliedId,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.parent_comment.0', 'The selected related record does not exist.');
});

it('allows standard relations updates to proceed', function () {
    $comments = circularCreateRelationCollection('comments', [
        ['id' => 'text_f', 'name' => 'text', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'rel_f', 'name' => 'parent_comment', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => true, 'unique' => false, 'default' => null, 'target_collection_id' => 'PLCHLDR', 'cascade_on_delete' => true],
    ]);

    $comments->update([
        'fields' => collect($comments->fields)->map(function ($f) use ($comments) {
            if ($f['name'] === 'parent_comment') {
                $f['target_collection_id'] = $comments->id;
            }

            return $f;
        })->all(),
    ]);

    $comment1 = Record::of($comments)->create(['text' => 'First']);
    $comment2 = Record::of($comments)->create(['text' => 'Second']);

    patchJson("/api/collections/{$comments->id}/records/{$comment2->id}", [
        'parent_comment' => $comment1->id,
    ])->assertSuccessful();
});
