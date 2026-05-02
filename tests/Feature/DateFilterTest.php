<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Records\Models\Record;
use App\Domain\RuleEngine\RuleEngine;

it('applies date functions in query filter', function () {
    $collection = new Collection;
    $collection->name = 'posts';
    $collection->type = CollectionType::Base;
    $collection->is_system = false;
    $collection->fields = [
        ['name' => 'created_at', 'type' => 'timestamp', 'nullable' => false],
    ];

    $allowedFields = ['created_at'];

    // Test date()
    $query = Record::of($collection)->newQuery();
    QueryFilter::for($query, $allowedFields)->run('date(created_at) = "2024-05-02"');
    expect($query->toSql())->toContain('strftime(\'%Y-%m-%d\', "created_at")');

    // Test year()
    $query = Record::of($collection)->newQuery();
    QueryFilter::for($query, $allowedFields)->run('year(created_at) = 2024');
    expect($query->toSql())->toContain('strftime(\'%Y\', "created_at")');

    // Test month()
    $query = Record::of($collection)->newQuery();
    QueryFilter::for($query, $allowedFields)->run('month(created_at) = 5');
    expect($query->toSql())->toContain('strftime(\'%m\', "created_at")');

    // Test day()
    $query = Record::of($collection)->newQuery();
    QueryFilter::for($query, $allowedFields)->run('day(created_at) = 2');
    expect($query->toSql())->toContain('strftime(\'%d\', "created_at")');

    // Test time()
    $query = Record::of($collection)->newQuery();
    QueryFilter::for($query, $allowedFields)->run('time(created_at) = "12:34:56"');
    expect($query->toSql())->toContain('strftime(\'%H:%M:%S\', "created_at")');
});

it('evaluates date functions in memory', function () {
    $engine = RuleEngine::make(['created_at']);
    $context = ['created_at' => '2024-05-02 12:34:56'];

    expect($engine->evaluate('date(created_at) = "2024-05-02"', $context))->toBeTrue();
    expect($engine->evaluate('year(created_at) = 2024', $context))->toBeTrue();
    expect($engine->evaluate('month(created_at) = 5', $context))->toBeTrue();
    expect($engine->evaluate('day(created_at) = 2', $context))->toBeTrue();
    expect($engine->evaluate('time(created_at) = "12:34:56"', $context))->toBeTrue();

    expect($engine->evaluate('date(created_at) = "2024-05-03"', $context))->toBeFalse();
});
