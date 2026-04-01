<?php

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\RuleEngine\Adapters\JsonFieldAdapter;

it('in-memory: ?= matches value inside array field', function () {
    $engine = new QueryFilter;
    $result = $engine->evaluate(
        'tags ?= "laravel"',
        ['tags' => ['php', 'laravel', 'vue']]
    );
    expect($result)->toBeTrue();
});

it('in-memory: ?= returns false when value not in array', function () {
    $engine = new QueryFilter;
    $result = $engine->evaluate(
        'tags ?= "rust"',
        ['tags' => ['php', 'laravel']]
    );
    expect($result)->toBeFalse();
});

it('in-memory: ?& matches existing key in associative array', function () {
    $engine = new QueryFilter;
    $result = $engine->evaluate(
        'meta ?& "theme"',
        ['meta' => ['theme' => 'dark', 'lang' => 'en']]
    );
    expect($result)->toBeTrue();
});

it('in-memory: ?& returns false for missing key', function () {
    $engine = new QueryFilter;
    $result = $engine->evaluate(
        'meta ?& "missing"',
        ['meta' => ['theme' => 'dark']]
    );
    expect($result)->toBeFalse();
});

it('sql: whereJsonContains is applied for ?= on json-path field', function () {
    $query = Collection::query();
    $adapter = new JsonFieldAdapter;

    // JSON field must use -> notation so the adapter's supports() returns true
    QueryFilter::for($query, ['tags->items'])
        ->withQueryFieldAdapter($adapter)
        ->run('tags->items ?= "laravel"');

    $sql = strtolower($query->toSql());
    // MySQL: json_contains; SQLite: json_each — both contain 'json'
    expect($sql)->toContain('json');
});

it('sql: regular fields with -> in name are resolved by json adapter', function () {
    $query = Collection::query();
    $adapter = new JsonFieldAdapter;

    QueryFilter::for($query, ['meta->theme'])
        ->withQueryFieldAdapter($adapter)
        ->run('meta->theme = "dark"');

    $sql = strtolower($query->toSql());
    // Eloquent compiles 'meta->theme' to a json extract expression
    expect($sql)->toContain('meta');
});
