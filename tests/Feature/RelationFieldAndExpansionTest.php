<?php

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Requests\StoreCollectionRequest;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function relationApiRules(): array
{
    return [
        'list' => '',
        'view' => '',
        'create' => '',
        'update' => '',
        'delete' => '',
    ];
}

function createRelationCollection(string $name, array $fields, bool $isSystem = false): Collection
{
    return Collection::create([
        'name' => $name,
        'type' => CollectionType::Base,
        'is_system' => $isSystem,
        'description' => ucfirst($name),
        'fields' => $fields,
        'api_rules' => relationApiRules(),
        'indexes' => [],
    ]);
}

it('requires relation field options when defining schema', function () {
    $payload = [
        'name' => 'articles',
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'author', 'type' => CollectionFieldType::Relation->value],
        ],
    ];

    $request = StoreCollectionRequest::create('/api/collections', 'POST', $payload);
    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('fields.0.target_collection_id'))->toBeTrue();
    // max_select is no longer required or supported
    expect($validator->errors()->has('fields.0.max_select'))->toBeFalse();
});

it('rejects relation targets that are system collections', function () {
    $systemTarget = createRelationCollection(
        'system_profiles',
        [
            ['id' => 'aa11aa11', 'name' => 'label', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ],
        true,
    );

    $payload = [
        'name' => 'articles',
        'type' => CollectionType::Base->value,
        'fields' => [
            [
                'name' => 'author',
                'type' => CollectionFieldType::Relation->value,
                'target_collection_id' => $systemTarget->id,
            ],
        ],
    ];

    $request = StoreCollectionRequest::create('/api/collections', 'POST', $payload);
    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('fields.0.target_collection_id'))->toBeTrue();
});

it('accepts single relation ID string on create', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'cascade_on_delete' => false],
    ]);

    $profile = Record::of($profiles)->create(['name' => 'Jane']);

    $response = postJson("/api/collections/{$articles->id}/records", [
        'title' => 'Hello',
        'author' => $profile->id,
    ])->assertCreated();

    $response->assertJsonPath('data.author', $profile->id);

    $stored = Record::of($articles)->newQuery()->firstOrFail();

    expect($stored->getAttribute('author'))->toBe($profile->id);
});

it('rejects create when related records do not exist', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'cascade_on_delete' => false],
    ]);

    postJson("/api/collections/{$articles->id}/records", [
        'title' => 'Hello',
        'author' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'The selected related record does not exist.');
});

it('rejects update when related records do not exist', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'cascade_on_delete' => false],
    ]);

    $profile = Record::of($profiles)->create(['name' => 'Jane']);
    $article = Record::of($articles)->create(['title' => 'Hello', 'author' => $profile->id]);

    patchJson("/api/collections/{$articles->id}/records/{$article->id}", [
        'title' => 'Hello',
        'author' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'The selected related record does not exist.');
});

it('expands single relation as object', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'cascade_on_delete' => false],
    ]);

    $profile = Record::of($profiles)->create(['name' => 'Jane']);
    Record::of($articles)->create(['title' => 'Hello', 'author' => $profile->id]);

    $response = getJson("/api/collections/{$articles->id}/records?expand=author");
    $response->assertSuccessful()
        ->assertJsonPath('data.0.expand.author.id', $profile->id)
        ->assertJsonPath('data.0.expand.author.name', 'Jane');
});

it('blocks nested expansion with a safe 501 error', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'cascade_on_delete' => false],
    ]);

    getJson("/api/collections/{$articles->id}/records?expand=author.profile")
        ->assertStatus(501)
        ->assertJsonPath('message', 'Nested relation expansion is not implemented.');
});

it('allows relation filtering with dot notation', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'cascade_on_delete' => false],
    ]);

    $jane = Record::of($profiles)->create(['name' => 'Jane']);
    $john = Record::of($profiles)->create(['name' => 'John']);

    Record::of($articles)->create(['title' => 'Article by Jane', 'author' => $jane->id]);
    Record::of($articles)->create(['title' => 'Article by John', 'author' => $john->id]);

    // Test dot-notation filtering
    getJson("/api/collections/{$articles->id}/records?filter=author.name%20=%20%22Jane%22")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Article by Jane');

    // Test direct relation filtering (which is now plain FK comparison)
    getJson("/api/collections/{$articles->id}/records?filter=author%20=%20%22{$jane->id}%22")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Article by Jane');
});

it('allows nested relation filtering with multiple dots', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'p1', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'p2', 'name' => 'category', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => 'CAT_ID_PLACEHOLDER'],
    ]);

    $categories = createRelationCollection('categories', [
        ['id' => 'c1', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    // Fix the placeholder
    $profiles->update([
        'fields' => collect($profiles->fields)->map(function ($f) use ($categories) {
            if ($f['name'] === 'category') {
                $f['target_collection_id'] = $categories->id;
            }

            return $f;
        })->all(),
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'a1', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'a2', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id],
    ]);

    $catNews = Record::of($categories)->create(['title' => 'News']);
    $catTech = Record::of($categories)->create(['title' => 'Tech']);

    $jane = Record::of($profiles)->create(['name' => 'Jane', 'category' => $catNews->id]);
    $john = Record::of($profiles)->create(['name' => 'John', 'category' => $catTech->id]);

    Record::of($articles)->create(['title' => 'Jane News', 'author' => $jane->id]);
    Record::of($articles)->create(['title' => 'John Tech', 'author' => $john->id]);

    // Test nested filtering: author.category.title
    getJson("/api/collections/{$articles->id}/records?filter=author.category.title%20=%20%22News%22")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Jane News');

    getJson("/api/collections/{$articles->id}/records?filter=author.category.title%20=%20%22Tech%22")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'John Tech');
});

it('avoids n plus one relation expansion for record lists', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'cascade_on_delete' => false],
    ]);

    $profileA = Record::of($profiles)->create(['name' => 'Jane']);
    $profileB = Record::of($profiles)->create(['name' => 'John']);

    Record::of($articles)->create(['title' => 'One', 'author' => $profileA->id]);
    Record::of($articles)->create(['title' => 'Two', 'author' => $profileB->id]);
    Record::of($articles)->create(['title' => 'Three', 'author' => $profileA->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    getJson("/api/collections/{$articles->id}/records?expand=author")
        ->assertSuccessful();

    $targetTable = $profiles->getPhysicalTableName();

    $targetQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, $targetTable))
        ->count();

    expect($targetQueries)->toBe(1);
});
