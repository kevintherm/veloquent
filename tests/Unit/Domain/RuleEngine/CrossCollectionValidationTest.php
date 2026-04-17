<?php

namespace Tests\Unit\Domain\RuleEngine;

use App\Domain\Collections\Models\Collection;
use App\Domain\RuleEngine\RuleEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Drop it if exists before continuing just in case our manual schema clashes
    // Wait, the observer actually doesn't seem to create standard tables fully during basic factory create in tests, let's just make sure we drop it.

    // We create an actual collection so the evaluator can resolve it
    $this->rolesCollection = Collection::factory()->create([
        'name' => 'roles',
        'is_system' => false,
    ]);

    // Create a physical table for it, dropping it first if the observer created it
    DB::statement('DROP TABLE IF EXISTS '.$this->rolesCollection->getPhysicalTableName());
    DB::statement('CREATE TABLE '.$this->rolesCollection->getPhysicalTableName().' (id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT)');

    DB::table($this->rolesCollection->getPhysicalTableName())->insert([
        ['id' => 1, 'user_id' => 10, 'name' => 'admin'],
        ['id' => 2, 'user_id' => 20, 'name' => 'editor'],
    ]);
});

it('evaluates cross collection validation using UnifiedInMemoryEvaluator correctly', function () {
    $engine = new RuleEngine;

    // Context contains user id 10
    $context = [
        'id' => 10,
    ];

    expect($engine->evaluate('@collection.roles.user_id = id', $context))->toBeTrue();

    // Test false case
    $context2 = [
        'id' => 99,
    ];
    expect($engine->evaluate('@collection.roles.user_id = id', $context2))->toBeFalse();
});

it('evaluates cross collection validation in DB query using UnifiedEvaluator correctly', function () {
    // Setup a dummy table we are querying
    DB::statement('DROP TABLE IF EXISTS _velo_posts');
    DB::statement('CREATE TABLE _velo_posts (id INTEGER PRIMARY KEY, author_id INTEGER, title TEXT)');
    DB::table('_velo_posts')->insert([
        ['id' => 1, 'author_id' => 10, 'title' => 'Post by Admin'],
        ['id' => 2, 'author_id' => 99, 'title' => 'Post by Unknown'],
    ]);

    // We want to query posts where the author_id is in the roles table as user_id.
    // The expression: `@collection.roles.user_id = author_id`

    $model = new class extends Model
    {
        protected $table = '_velo_posts';
    };

    $query = $model->newQuery();

    $engine = RuleEngine::for($query);
    $engine->run('@collection.roles.user_id = author_id');

    $results = $query->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe(1);
});

it('evaluates cross collection validation with nested request body sysvars', function () {
    $engine = new RuleEngine;

    // Context contains nested request body
    $context = [
        'request' => [
            'body' => [
                'userId' => 10,
            ],
        ],
    ];

    expect($engine->evaluate('@collection.roles.user_id = @request.body.userId', $context))->toBeTrue();

    // Test false case
    $context['request']['body']['userId'] = 99;
    expect($engine->evaluate('@collection.roles.user_id = @request.body.userId', $context))->toBeFalse();
});

it('evaluates cross collection validation with request body sysvars in DB query', function () {
    DB::statement('DROP TABLE IF EXISTS _velo_posts');
    DB::statement('CREATE TABLE _velo_posts (id INTEGER PRIMARY KEY, author_id INTEGER, title TEXT)');
    DB::table('_velo_posts')->insert([
        ['id' => 1, 'author_id' => 10, 'title' => 'Post by Admin'],
    ]);

    $model = new class extends Model
    {
        protected $table = '_velo_posts';
    };

    $query = $model->newQuery();

    $context = [
        'request' => [
            'body' => [
                'adminId' => 10,
            ],
        ],
    ];

    $engine = RuleEngine::for($query);
    $engine->run('@collection.roles.user_id = @request.body.adminId', $context);

    expect($query->count())->toBe(1);

    // Test false case
    $query2 = $model->newQuery();
    $context2 = [
        'request' => [
            'body' => [
                'adminId' => 99,
            ],
        ],
    ];
    RuleEngine::for($query2)->run('@collection.roles.user_id = @request.body.adminId', $context2);
    expect($query2->count())->toBe(0);
});

it('evaluates flipped cross collection validation with nested request body sysvars', function () {
    $engine = new RuleEngine;

    $context = [
        'request' => [
            'body' => [
                'userId' => 10,
            ],
        ],
    ];

    // Flipped: @request.body.userId = @collection.roles.user_id
    expect($engine->evaluate('@request.body.userId = @collection.roles.user_id', $context))->toBeTrue();

    $context['request']['body']['userId'] = 99;
    expect($engine->evaluate('@request.body.userId = @collection.roles.user_id', $context))->toBeFalse();
});

it('evaluates flipped cross collection validation with request body sysvars in DB query', function () {
    DB::statement('DROP TABLE IF EXISTS _velo_posts');
    DB::statement('CREATE TABLE _velo_posts (id INTEGER PRIMARY KEY, author_id INTEGER, title TEXT)');
    DB::table('_velo_posts')->insert([
        ['id' => 1, 'author_id' => 10, 'title' => 'Post by Admin'],
    ]);

    $model = new class extends Model
    {
        protected $table = '_velo_posts';
    };

    $query = $model->newQuery();
    $context = ['request' => ['body' => ['adminId' => 10]]];

    // Flipped: @request.body.adminId = @collection.roles.user_id
    RuleEngine::for($query)->run('@request.body.adminId = @collection.roles.user_id', $context);
    expect($query->count())->toBe(1);

    $query2 = $model->newQuery();
    $context2 = ['request' => ['body' => ['adminId' => 99]]];
    RuleEngine::for($query2)->run('@request.body.adminId = @collection.roles.user_id', $context2);
    expect($query2->count())->toBe(0);
});