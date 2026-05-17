<?php

use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('enforces view rules on expanded records', function () {
    $targetCollection = Collection::create([
        'name' => 'targets',
        'type' => CollectionType::Base,
        'fields' => [
            ['name' => 'secret', 'type' => CollectionFieldType::Text->value],
            ['name' => 'visible', 'type' => CollectionFieldType::Text->value],
        ],
        'api_rules' => [
            'view' => 'visible = "yes"',
            'list' => 'visible = "yes"',
        ],
    ]);

    $sourceCollection = Collection::create([
        'name' => 'sources',
        'type' => CollectionType::Base,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            [
                'name' => 'target',
                'type' => CollectionFieldType::Relation->value,
                'target_collection_id' => $targetCollection->id,
            ],
        ],
        'api_rules' => ['view' => '', 'list' => ''],
    ]);

    $allowed = Record::of($targetCollection)->create(['visible' => 'yes', 'secret' => 'top secret']);
    $denied = Record::of($targetCollection)->create(['visible' => 'no', 'secret' => 'hidden']);

    Record::of($sourceCollection)->create(['title' => 'Allowed', 'target' => $allowed->id]);
    Record::of($sourceCollection)->create(['title' => 'Denied', 'target' => $denied->id]);

    getJson("/api/collections/{$sourceCollection->id}/records?expand=target&sort=title")
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'Allowed')
        ->assertJsonPath('data.0.target', $allowed->id)
        ->assertJsonPath('data.0.expand.target.id', $allowed->id)
        ->assertJsonPath('data.1.title', 'Denied')
        ->assertJsonPath('data.1.target', $denied->id)
        ->assertJsonPath('data.1.expand.target', null);
});

it('redacts sensitive fields in expanded auth collections', function () {
    $authCollection = Collection::create([
        'name' => 'auth_users',
        'type' => CollectionType::Auth,
        'fields' => [
            ['name' => 'email', 'type' => CollectionFieldType::Email->value],
            ['name' => 'email_visibility', 'type' => CollectionFieldType::Boolean->value],
        ],
        'api_rules' => ['view' => '', 'list' => ''],
    ]);

    $sourceCollection = Collection::create([
        'name' => 'auth_sources',
        'type' => CollectionType::Base,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            [
                'name' => 'target',
                'type' => CollectionFieldType::Relation->value,
                'target_collection_id' => $authCollection->id,
            ],
        ],
        'api_rules' => ['view' => '', 'list' => ''],
    ]);

    $user = Record::of($authCollection)->create([
        'email' => 'private@example.com',
        'email_visibility' => false,
    ]);

    Record::of($sourceCollection)->create(['title' => 'With User', 'target' => $user->id]);

    getJson("/api/collections/{$sourceCollection->id}/records?expand=target")
        ->assertSuccessful()
        ->assertJsonPath('data.0.target', $user->id)
        ->assertJsonPath('data.0.expand.target.id', $user->id)
        ->assertJsonMissing(['data.0.expand.target.email']);
});

it('logs a warning and returns null when expansion target collection is missing', function () {
    $sourceCollection = Collection::create([
        'name' => 'missing_target_sources',
        'type' => CollectionType::Base,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            [
                'name' => 'target',
                'type' => CollectionFieldType::Relation->value,
                'target_collection_id' => 'non-existent-id',
            ],
        ],
        'api_rules' => ['view' => '', 'list' => ''],
    ]);

    Log::shouldReceive('forgetChannel')->zeroOrMoreTimes();
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('warning')
        ->once()
        ->with('EXPANSION_TARGET_COLLECTION_MISSING', Mockery::on(function ($data) use ($sourceCollection) {
            return $data['source_collection'] === $sourceCollection->id
                && $data['field'] === 'target'
                && $data['target_collection_id'] === 'non-existent-id';
        }));

    Record::of($sourceCollection)->create(['title' => 'Broken', 'target' => 'some-id']);

    getJson("/api/collections/{$sourceCollection->id}/records?expand=target")
        ->assertSuccessful()
        ->assertJsonPath('data.0.target', 'some-id')
        ->assertJsonPath('data.0.expand.target', null);
});

it('verifies that each collection rules applies to expansion filtering', function () {
    // 1. Create a "categories" collection with view rules requiring "status = 'active'"
    $categoriesCollection = Collection::create([
        'name' => 'categories',
        'type' => CollectionType::Base,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            ['name' => 'status', 'type' => CollectionFieldType::Text->value],
        ],
        'api_rules' => [
            'view' => 'status = "active"',
            'list' => 'status = "active"',
        ],
    ]);

    // 2. Create a "tags" collection with view rules requiring "allowed = true"
    $tagsCollection = Collection::create([
        'name' => 'tags',
        'type' => CollectionType::Base,
        'fields' => [
            ['name' => 'name', 'type' => CollectionFieldType::Text->value],
            ['name' => 'allowed', 'type' => CollectionFieldType::Boolean->value],
        ],
        'api_rules' => [
            'view' => 'allowed = true',
            'list' => 'allowed = true',
        ],
    ]);

    // 3. Create a "posts" collection containing BOTH category (relation) and tag (relation)
    $postsCollection = Collection::create([
        'name' => 'posts_multi_rules',
        'type' => CollectionType::Base,
        'fields' => [
            ['name' => 'title', 'type' => CollectionFieldType::Text->value],
            [
                'name' => 'category',
                'type' => CollectionFieldType::Relation->value,
                'target_collection_id' => $categoriesCollection->id,
            ],
            [
                'name' => 'tag',
                'type' => CollectionFieldType::Relation->value,
                'target_collection_id' => $tagsCollection->id,
            ],
        ],
        'api_rules' => ['view' => '', 'list' => ''],
    ]);

    // 4. Create Category records (one matching rules, one failing rules)
    $activeCategory = Record::of($categoriesCollection)->create(['title' => 'Tech', 'status' => 'active']);
    $inactiveCategory = Record::of($categoriesCollection)->create(['title' => 'Secret', 'status' => 'inactive']);

    // 5. Create Tag records (one matching rules, one failing rules)
    $allowedTag = Record::of($tagsCollection)->create(['name' => 'OpenSource', 'allowed' => true]);
    $disallowedTag = Record::of($tagsCollection)->create(['name' => 'Malware', 'allowed' => false]);

    // 6. Create Post records linking various combinations
    // Post A: Category is active (visible), Tag is allowed (visible)
    Record::of($postsCollection)->create([
        'title' => 'Post A',
        'category' => $activeCategory->id,
        'tag' => $allowedTag->id,
    ]);

    // Post B: Category is inactive (hidden), Tag is allowed (visible)
    Record::of($postsCollection)->create([
        'title' => 'Post B',
        'category' => $inactiveCategory->id,
        'tag' => $allowedTag->id,
    ]);

    // Post C: Category is active (visible), Tag is disallowed (hidden)
    Record::of($postsCollection)->create([
        'title' => 'Post C',
        'category' => $activeCategory->id,
        'tag' => $disallowedTag->id,
    ]);

    // 7. Request records with expansion on both category and tag
    getJson("/api/collections/{$postsCollection->id}/records?expand=category,tag&sort=title")
        ->assertSuccessful()
        // Post A: category and tag are BOTH visible
        ->assertJsonPath('data.0.title', 'Post A')
        ->assertJsonPath('data.0.expand.category.id', $activeCategory->id)
        ->assertJsonPath('data.0.expand.tag.id', $allowedTag->id)
        
        // Post B: category is null (failed categories rule), tag is visible
        ->assertJsonPath('data.1.title', 'Post B')
        ->assertJsonPath('data.1.expand.category', null)
        ->assertJsonPath('data.1.expand.tag.id', $allowedTag->id)

        // Post C: category is visible, tag is null (failed tags rule)
        ->assertJsonPath('data.2.title', 'Post C')
        ->assertJsonPath('data.2.expand.category.id', $activeCategory->id)
        ->assertJsonPath('data.2.expand.tag', null);
});
