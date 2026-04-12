<?php

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('succeeds to update partial fields when other fields are required', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'posts_'.Str::random(5),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'nullable' => false],
            ['name' => 'content', 'type' => 'text', 'nullable' => false],
        ],
        'api_rules' => [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => 'id != ""', // Allow update
            'delete' => '',
        ],
    ]);

    $record = Record::of($collection)->create([
        'title' => 'Original Title',
        'content' => 'Original Content',
    ]);

    // Try to update only the title. This should NOW succeed because of 'sometimes' rule.
    $response = $this->patchJson(route('records.update', [$collection->id, $record->id]), [
        'title' => 'Updated Title',
    ]);

    $response->assertStatus(200);

    $record->refresh();
    expect($record->title)->toBe('Updated Title');
    expect($record->content)->toBe('Original Content');
});

it('still validates required fields if they are present but null', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'posts_'.Str::random(5),
        'type' => CollectionType::Base->value,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'nullable' => false],
        ],
        'api_rules' => [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => 'id != ""', // Allow update
            'delete' => '',
        ],
    ]);

    $record = Record::of($collection)->create([
        'title' => 'Original Title',
    ]);

    $response = $this->patchJson(route('records.update', [$collection->id, $record->id]), [
        'title' => null,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('allows auth updates when password is omitted or empty', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_'.Str::random(5),
        'type' => CollectionType::Auth->value,
        'fields' => [
            ['name' => 'name', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ],
        'api_rules' => [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => 'id != ""',
            'delete' => '',
            'manage' => '',
        ],
    ]);

    $record = Record::of($collection)->create([
        'name' => 'Original Name',
        'email' => 'user_'.Str::lower(Str::random(6)).'@example.test',
        'password' => 'password123',
    ]);

    $this->patchJson(route('records.update', [$collection->id, $record->id]), [
        'name' => 'Updated Name',
    ])->assertOk();

    $this->patchJson(route('records.update', [$collection->id, $record->id]), [
        'password' => '',
    ])->assertOk();
});

it('still validates auth password length when provided on update', function () {
    $collection = app(CreateCollectionAction::class)->execute([
        'name' => 'users_'.Str::random(5),
        'type' => CollectionType::Auth->value,
        'fields' => [
            ['name' => 'name', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ],
        'api_rules' => [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => 'id != ""',
            'delete' => '',
            'manage' => '',
        ],
    ]);

    $record = Record::of($collection)->create([
        'name' => 'Original Name',
        'email' => 'user_'.Str::lower(Str::random(6)).'@example.test',
        'password' => 'password123',
    ]);

    $this->patchJson(route('records.update', [$collection->id, $record->id]), [
        'password' => 'short',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
