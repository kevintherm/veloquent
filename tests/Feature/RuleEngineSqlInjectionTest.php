<?php

namespace Tests\Feature;

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\RuleEngine\RuleEngine;
use Veloquent\Core\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Veloquent\Core\Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create the physical table for '_velo_posts' in the test database
    Schema::create('_velo_posts', function ($table) {
        $table->ulid('id')->primary();
        $table->string('title');
        $table->string('status');
        $table->timestamps();
    });

    // Create a posts collection metadata object
    $this->collection = Collection::withoutEvents(fn () => Collection::query()->create([
        'id' => (string) Str::ulid(),
        'name' => 'posts',
        'type' => CollectionType::Base,
        'table_name' => '_velo_posts',
    ]));

    // Insert some initial records
    Record::of($this->collection)->create(['id' => '1', 'title' => 'First Post', 'status' => 'active']);
    Record::of($this->collection)->create(['id' => '2', 'title' => 'Second Post', 'status' => 'draft']);
});

it('sanitizes and parameterizes string values with SQL injection payloads', function () {
    $query = Record::of($this->collection)->newQuery();
    $engine = RuleEngine::for($query, ['status']);

    // Attempting a standard OR injection payload inside a string value
    $rule = 'status = "active\' OR 1=1 --"';
    $engine->run($rule);

    $sql = $query->toSql();
    $bindings = $query->getBindings();

    // Verify that the payload is safely parameterized as a placeholder '?'
    expect($sql)->toContain('"status" = ?');
    expect($bindings)->toBe(["active' OR 1=1 --"]);

    // Run query to ensure it doesn't return the "draft" post (proves 1=1 OR logic didn't break out)
    $results = $query->get();
    expect($results->count())->toBe(0); // None match the exact status literal
});

it('sanitizes and parameterizes string values with UNION and subquery payloads', function () {
    $query = Record::of($this->collection)->newQuery();
    $engine = RuleEngine::for($query, ['status']);

    // Attempting a UNION breakout payload
    $rule = 'status = "active\' UNION SELECT * FROM _velo_posts --"';
    $engine->run($rule);

    $sql = $query->toSql();
    $bindings = $query->getBindings();

    // Should be fully parameterized and safe
    expect($sql)->toContain('"status" = ?');
    expect($bindings)->toBe(["active' UNION SELECT * FROM _velo_posts --"]);

    $results = $query->get();
    expect($results->count())->toBe(0);
});

it('sanitizes stacked query injection payloads', function () {
    $query = Record::of($this->collection)->newQuery();
    $engine = RuleEngine::for($query, ['status']);

    // Attempting stacked queries to drop tables
    $rule = 'status = "active\'; DROP TABLE _velo_posts; --"';
    $engine->run($rule);

    $sql = $query->toSql();
    $bindings = $query->getBindings();

    expect($sql)->toContain('"status" = ?');
    expect($bindings)->toBe(["active'; DROP TABLE _velo_posts; --"]);
    
    // Execute query and verify table is NOT dropped
    $query->get();
    expect(Schema::hasTable('_velo_posts'))->toBeTrue();
});

it('rejects field names containing malicious SQL syntax via AST validation', function () {
    $query = Record::of($this->collection)->newQuery();
    $engine = RuleEngine::for($query, ['status']);

    // Attempting syntax breakout inside field names (outside the quotes)
    // E.g. status) OR 1=1 -- = "active"
    $maliciousRule = 'status) OR 1=1 -- = "active"';

    // The parser/lexer should identify this as a syntax error or malformed identifier and throw an exception
    expect(fn () => $engine->run($maliciousRule))->toThrow(InvalidRuleExpressionException::class);
});

it('rejects malicious cross-collection variable syntax', function () {
    $query = Record::of($this->collection)->newQuery();
    $engine = RuleEngine::for($query, ['status']);

    // Attempting syntax breakout inside cross-collection system variables
    $maliciousRule1 = '@collection.posts.id) OR 1=1 -- = 1';
    expect(fn () => $engine->run($maliciousRule1))->toThrow(InvalidRuleExpressionException::class);

    $maliciousRule2 = '@collection.posts.id = 1 UNION SELECT 1';
    expect(fn () => $engine->run($maliciousRule2))->toThrow(InvalidRuleExpressionException::class);
});

it('in-memory evaluator is safe from SQL injection side effects', function () {
    $engine = RuleEngine::make(['status']);

    $context = [
        'status' => "active' OR 1=1 --",
    ];

    // True evaluation should only occur on exact match, ignoring SQL breakout logic
    expect($engine->evaluate('status = "active"', $context))->toBeFalse();
    expect($engine->evaluate('status = "active\' OR 1=1 --"', $context))->toBeTrue();
});

it('sanitizes dot notation path filters with SQL injection payloads and resolves relation joins safely', function () {
    // 1. Create '_velo_profiles' table and collection metadata
    Schema::create('_velo_profiles', function ($table) {
        $table->ulid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $profilesCollection = Collection::withoutEvents(fn () => Collection::query()->create([
        'id' => (string) Str::ulid(),
        'name' => 'profiles',
        'type' => CollectionType::Base,
        'table_name' => '_velo_profiles',
    ]));

    // Add author column physically to posts table
    Schema::table('_velo_posts', function ($table) {
        $table->string('author')->nullable();
    });

    // 2. Add 'author' relation field to posts collection fields metadata (bypass event-driven migration)
    Collection::withoutEvents(fn () => $this->collection->update([
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
            ['name' => 'status', 'type' => 'text'],
            [
                'name' => 'author',
                'type' => 'relation',
                'target_collection_id' => $profilesCollection->id,
            ],
        ],
    ]));

    // Re-fetch or create a new query instance to pick up the updated fields
    $query = Record::of($this->collection)->newQuery();
    $resolver = new \Veloquent\Core\Domain\Records\Services\RelationJoinResolver($this->collection, $query);
    $engine = RuleEngine::for($query, ['status', 'author'])->withRelationJoinResolver($resolver);

    // Attempting SQL injection via dot notation path string value
    $rule = 'author.name = "Jane\' OR 1=1 --"';
    $engine->run($rule);

    $sql = $query->toSql();
    $bindings = $query->getBindings();

    // Verify it parameterizes and generates LEFT JOIN correctly
    expect($sql)->toContain('left join "_velo_profiles"');
    expect($sql)->toContain('"____velo_posts__author"."name" = ?');
    expect($bindings)->toBe(["Jane' OR 1=1 --"]);
});

it('rejects field names containing malicious SQL syntax inside dot notation path via AST validation', function () {
    $query = Record::of($this->collection)->newQuery();
    $resolver = new \Veloquent\Core\Domain\Records\Services\RelationJoinResolver($this->collection, $query);
    $engine = RuleEngine::for($query, ['status', 'author'])->withRelationJoinResolver($resolver);

    // Attempting syntax breakout inside the dot notation path
    $maliciousRule = 'author.name) OR 1=1 -- = "Jane"';

    expect(fn () => $engine->run($maliciousRule))->toThrow(InvalidRuleExpressionException::class);
});

it('sanitizes and parameterizes system variables when resolved value contains SQL injection payload', function () {
    $query = Record::of($this->collection)->newQuery();
    $engine = RuleEngine::for($query, ['status']);

    // Attempting SQL injection via system variable resolved value
    $rule = 'status = @request.body.name';
    $context = [
        'request' => [
            'body' => [
                'name' => "active' OR 1=1 --",
            ],
        ],
    ];

    $engine->run($rule, $context);

    $sql = $query->toSql();
    $bindings = $query->getBindings();

    // Verify it is parameterized to ?
    expect($sql)->toContain('"status" = ?');
    expect($bindings)->toBe(["active' OR 1=1 --"]);
});

it('sanitizes and parameterizes system variables with stacked query payloads', function () {
    $query = Record::of($this->collection)->newQuery();
    $engine = RuleEngine::for($query, ['status']);

    // Attempting stacked query injection inside system variable resolved value
    $rule = 'status = @user.role';
    $context = [
        'user' => [
            'role' => "active'; DROP TABLE _velo_posts; --",
        ],
    ];

    $engine->run($rule, $context);

    $sql = $query->toSql();
    $bindings = $query->getBindings();

    expect($sql)->toContain('"status" = ?');
    expect($bindings)->toBe(["active'; DROP TABLE _velo_posts; --"]);

    // Execute query and verify table is not dropped
    $query->get();
    expect(Schema::hasTable('_velo_posts'))->toBeTrue();
});

it('rejects field names containing malicious SQL syntax inside @ prefixed system variables via AST validation', function () {
    $query = Record::of($this->collection)->newQuery();
    $engine = RuleEngine::for($query, ['status']);

    // Attempting syntax breakout inside system variable name
    $maliciousRule = 'status = @request.body.name) OR 1=1 --';

    expect(fn () => $engine->run($maliciousRule))->toThrow(InvalidRuleExpressionException::class);
});
