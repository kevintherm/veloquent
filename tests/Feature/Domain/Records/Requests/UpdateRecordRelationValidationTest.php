<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('allows partial update of relation field by replacing required with sometimes', function () {
    // 1. Create a target collection to relate to
    $targetCollection = app(CreateCollectionAction::class)->execute([
        'name' => 'authors_'.Str::random(5),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'name', 'type' => 'text', 'nullable' => false],
        ],
        'api_rules' => [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => '',
            'delete' => '',
        ],
    ]);

    $author = Record::of($targetCollection)->create(['name' => 'John Doe']);

    // 2. Create the main collection with a relation field
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'posts_'.Str::random(5),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'nullable' => false],
            [
                'name' => 'author_id',
                'type' => 'relation',
                'nullable' => false,
                'target_collection_id' => $targetCollection->id,
            ],
        ],
        'api_rules' => [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => 'id != ""',
            'delete' => '',
        ],
    ]);

    $record = Record::of($collection)->create([
        'title' => 'My Post',
        'author_id' => $author->id,
    ]);

    // 3. Try to update ONLY the title.
    // If "author_id"'s "required" is replaced with "sometimes", this should succeed.
    $response = $this->patchJson(route('records.update', [$collection->id, $record->id]), [
        'title' => 'Updated Title',
    ]);

    $response->assertStatus(200);

    $record->refresh();
    expect($record->title)->toBe('Updated Title');
    expect($record->author_id)->toBe($author->id);
});
