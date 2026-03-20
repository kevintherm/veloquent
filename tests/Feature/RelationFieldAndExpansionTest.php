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
    expect($validator->errors()->has('fields.0.max_select'))->toBeTrue();
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
                'max_select' => 1,
            ],
        ],
    ];

    $request = StoreCollectionRequest::create('/api/collections', 'POST', $payload);
    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('fields.0.target_collection_id'))->toBeTrue();
});

it('normalizes single relation value to json array on create', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 1, 'cascade_on_delete' => false],
    ]);

    $profile = Record::of($profiles)->create(['name' => 'Jane']);

    $response = postJson("/api/collections/{$articles->id}/records", [
        'title' => 'Hello',
        'author' => $profile->id,
    ])->assertCreated();

    $response->assertJsonPath('data.author', $profile->id);

    $stored = Record::of($articles)->newQuery()->firstOrFail();

    expect($stored->getAttribute('author'))->toBe([$profile->id]);
});

it('returns multi relation values as array and accepts array requests', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'authors', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 2, 'cascade_on_delete' => false],
    ]);

    $profileA = Record::of($profiles)->create(['name' => 'Jane']);
    $profileB = Record::of($profiles)->create(['name' => 'John']);

    postJson("/api/collections/{$articles->id}/records", [
        'title' => 'Hello',
        'authors' => [$profileA->id, $profileB->id],
    ])->assertCreated()
        ->assertJsonPath('data.authors.0', $profileA->id)
        ->assertJsonPath('data.authors.1', $profileB->id);
});

it('rejects scalar request for multi relation fields', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'authors', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 2, 'cascade_on_delete' => false],
    ]);

    $profile = Record::of($profiles)->create(['name' => 'Jane']);

    postJson("/api/collections/{$articles->id}/records", [
        'title' => 'Hello',
        'authors' => $profile->id,
    ])->assertUnprocessable();
});

it('rejects create when related records do not exist', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 1, 'cascade_on_delete' => false],
    ]);

    postJson("/api/collections/{$articles->id}/records", [
        'title' => 'Hello',
        'author' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Validation error');
});

it('rejects multiple ids when max_select is one', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 1, 'cascade_on_delete' => false],
    ]);

    $profileA = Record::of($profiles)->create(['name' => 'Jane']);
    $profileB = Record::of($profiles)->create(['name' => 'John']);

    postJson("/api/collections/{$articles->id}/records", [
        'title' => 'Hello',
        'author' => [$profileA->id, $profileB->id],
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Validation error')
        ->assertJsonPath('errors.author.0', 'This relation only allows one ID.');
});

it('rejects update when related records do not exist', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 1, 'cascade_on_delete' => false],
    ]);

    $profile = Record::of($profiles)->create(['name' => 'Jane']);
    $article = Record::of($articles)->create(['title' => 'Hello', 'author' => [$profile->id]]);

    patchJson("/api/collections/{$articles->id}/records/{$article->id}", [
        'title' => 'Hello',
        'author' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Validation error');
});

it('expands single relation as object', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 1, 'cascade_on_delete' => false],
    ]);

    $profile = Record::of($profiles)->create(['name' => 'Jane']);
    Record::of($articles)->create(['title' => 'Hello', 'author' => [$profile->id]]);

    getJson("/api/collections/{$articles->id}/records?expand=author")
        ->assertSuccessful()
        ->assertJsonPath('data.0.author.id', $profile->id)
        ->assertJsonPath('data.0.author.name', 'Jane');
});

it('expands multiple relation as array', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'authors', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 2, 'cascade_on_delete' => false],
    ]);

    $profileA = Record::of($profiles)->create(['name' => 'Jane']);
    $profileB = Record::of($profiles)->create(['name' => 'John']);
    $article = Record::of($articles)->create(['title' => 'Hello', 'authors' => [$profileA->id, $profileB->id]]);

    $response = getJson("/api/collections/{$articles->id}/records/{$article->id}?expand=authors")
        ->assertSuccessful();

    $authors = $response->json('data.authors');

    expect($authors)->toBeArray();
    expect(count($authors))->toBe(2);
    expect($authors[0]['id'])->toBe($profileA->id);
    expect($authors[1]['id'])->toBe($profileB->id);
});

it('blocks nested expansion with a safe 501 error', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 1, 'cascade_on_delete' => false],
    ]);

    getJson("/api/collections/{$articles->id}/records?expand=author.profile")
        ->assertStatus(501)
        ->assertJsonPath('message', 'Nested relation expansion is not implemented.');
});

it('blocks relation filtering with a safe 501 error', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 1, 'cascade_on_delete' => false],
    ]);

    getJson("/api/collections/{$articles->id}/records?filter=author.name%20=%20%22Jane%22")
        ->assertStatus(501)
        ->assertJsonPath('message', 'Filtering nested relation fields is not implemented.');

    getJson("/api/collections/{$articles->id}/records?filter=author%20=%20%22id%22")
        ->assertStatus(501)
        ->assertJsonPath('message', 'Filtering relation fields is not implemented.');
});

it('avoids n plus one relation expansion for record lists', function () {
    $profiles = createRelationCollection('profiles', [
        ['id' => 'aa11aa11', 'name' => 'name', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
    ]);

    $articles = createRelationCollection('articles', [
        ['id' => 'bb22bb22', 'name' => 'title', 'type' => CollectionFieldType::Text->value, 'order' => 0, 'nullable' => false, 'unique' => false, 'default' => null],
        ['id' => 'cc33cc33', 'name' => 'author', 'type' => CollectionFieldType::Relation->value, 'order' => 1, 'nullable' => false, 'unique' => false, 'default' => null, 'target_collection_id' => $profiles->id, 'max_select' => 1, 'cascade_on_delete' => false],
    ]);

    $profileA = Record::of($profiles)->create(['name' => 'Jane']);
    $profileB = Record::of($profiles)->create(['name' => 'John']);

    Record::of($articles)->create(['title' => 'One', 'author' => [$profileA->id]]);
    Record::of($articles)->create(['title' => 'Two', 'author' => [$profileB->id]]);
    Record::of($articles)->create(['title' => 'Three', 'author' => [$profileA->id]]);

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
