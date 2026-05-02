<?php

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
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

    getJson("/api/collections/{$sourceCollection->id}/records?expand=target")
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
