<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Controllers\CollectionController;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Events\CollectionTruncated;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

use function Pest\Laravel\deleteJson;

uses(RefreshDatabase::class);

function createManageCollection(string $name = 'articles'): Collection
{
    return app(CreateCollectionAction::class)->execute([
        'name' => $name,
        'type' => CollectionType::Base->value,
        'description' => ucfirst($name).' collection',
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false],
        ],
        'api_rules' => [
            'list' => 'id = @request.auth.id',
            'view' => 'id = @request.auth.id',
            'create' => 'id = @request.auth.id',
            'update' => 'id = @request.auth.id',
            'delete' => 'id = @request.auth.id',
        ],
    ]);
}

it('persists api rules with list and view keys on create and update', function () {
    $collection = createManageCollection('articles');

    expect($collection->api_rules)->toMatchArray([
        'list' => 'id = @request.auth.id',
        'view' => 'id = @request.auth.id',
        'create' => 'id = @request.auth.id',
        'update' => 'id = @request.auth.id',
        'delete' => 'id = @request.auth.id',
    ]);

    $updated = app(UpdateCollectionAction::class)->execute($collection, [
        'api_rules' => [
            'list' => 'id = @request.auth.id && title = "hello"',
            'view' => 'id = @request.auth.id && title = "hello"',
            'create' => 'id = @request.auth.id && title = "hello"',
            'update' => 'id = @request.auth.id && title = "hello"',
            'delete' => 'id = @request.auth.id && title = "hello"',
        ],
    ]);

    expect($updated->api_rules)->toMatchArray([
        'list' => 'id = @request.auth.id && title = "hello"',
        'view' => 'id = @request.auth.id && title = "hello"',
        'create' => 'id = @request.auth.id && title = "hello"',
        'update' => 'id = @request.auth.id && title = "hello"',
        'delete' => 'id = @request.auth.id && title = "hello"',
    ]);
});

it('truncates records in a collection', function () {
    Event::fake([CollectionTruncated::class]);

    $collection = createManageCollection('logs');

    Record::of($collection)->create([
        'title' => 'First entry',
    ]);

    Record::of($collection)->create([
        'title' => 'Second entry',
    ]);

    Gate::shouldReceive('authorize')
        ->once()
        ->andReturnNull();

    deleteJson("/api/collections/{$collection->id}/truncate")
        ->assertSuccessful()
        ->assertJsonPath('data.deleted', 2);

    expect(Record::of($collection)->count())->toBe(0);

    Event::assertDispatched(CollectionTruncated::class, function ($event) use ($collection) {
        return $event->collection->id === $collection->id && $event->deletedCount === 2;
    });
});

it('prevents truncating the default auth collection', function () {
    $collection = new Collection;
    $collection->forceFill([
        'name' => config('velo.default_auth_collection'),
        'is_system' => false,
        'type' => CollectionType::Base,
    ]);

    Gate::shouldReceive('authorize')
        ->once()
        ->andReturnNull();

    $response = app(CollectionController::class)->truncate($collection);

    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['message'])->toBe('Cannot truncate default auth collection');
});

it('cascades deletion of referencing records if enabled', function () {
    $authors = app(CreateCollectionAction::class)->execute([
        'name' => 'authors',
        'type' => CollectionType::Base->value,
        'fields' => [['name' => 'name', 'type' => CollectionFieldType::Text->value]],
    ]);

    $books = app(CreateCollectionAction::class)->execute([
        'name' => 'books',
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            ['name' => 'author_id', 'type' => CollectionFieldType::Relation->value, 'target_collection_id' => $authors->id, 'cascade_on_delete' => true],
        ],
    ]);

    $authorId = (string) Str::ulid();
    Record::of($authors)->fill(['id' => $authorId, 'name' => 'John Doe'])->save();

    $bookId = (string) Str::ulid();
    Record::of($books)->fill(['id' => $bookId, 'title' => 'My Book', 'author_id' => $authorId])->save();

    // Verify records exist
    expect(Record::of($authors)->where('id', $authorId)->exists())->toBeTrue();
    expect(Record::of($books)->where('id', $bookId)->exists())->toBeTrue();

    // Delete author
    Record::of($authors)->find($authorId)->delete();

    // Verify both are deleted
    expect(Record::of($authors)->where('id', $authorId)->exists())->toBeFalse();
    expect(Record::of($books)->where('id', $bookId)->exists())->toBeFalse();
});

it('blocks deletion of referenced records if cascade is disabled', function () {
    $authors = app(CreateCollectionAction::class)->execute([
        'name' => 'authors_no_cascade',
        'type' => CollectionType::Base->value,
        'fields' => [['name' => 'name', 'type' => CollectionFieldType::Text->value]],
    ]);

    $books = app(CreateCollectionAction::class)->execute([
        'name' => 'books_no_cascade',
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            ['name' => 'author_id', 'type' => CollectionFieldType::Relation->value, 'target_collection_id' => $authors->id, 'cascade_on_delete' => false],
        ],
    ]);

    $authorId = (string) Str::ulid();
    Record::of($authors)->fill(['id' => $authorId, 'name' => 'John Doe'])->save();

    $bookId = (string) Str::ulid();
    Record::of($books)->fill(['id' => $bookId, 'title' => 'My Book', 'author_id' => $authorId])->save();

    try {
        Record::of($authors)->find($authorId)->delete();
        $this->fail('Expected InvalidArgumentException was not thrown');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain("Cannot delete this record because it is referenced by collection 'books_no_cascade'");
    }

    // Verify records still exist
    expect(Record::of($authors)->where('id', $authorId)->exists())->toBeTrue();
    expect(Record::of($books)->where('id', $bookId)->exists())->toBeTrue();
});
