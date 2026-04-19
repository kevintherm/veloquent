<?php

use App\Domain\Collections\Models\Collection;
use App\Domain\RuleEngine\RuleEngine;

it('implicitly converts = null to IS NULL in SQL', function () {
    $query = Collection::query();
    $engine = RuleEngine::for($query, ['id']);

    $engine->run('id = null');

    $sql = strtolower($query->toSql());
    expect($sql)->toContain('where "id" is null');
});

it('implicitly converts != null to IS NOT NULL in SQL', function () {
    $query = Collection::query();
    $engine = RuleEngine::for($query, ['id']);

    $engine->run('id != null');

    $sql = strtolower($query->toSql());
    expect($sql)->toContain('where "id" is not null');
});

it('implicitly converts sysvar = null to IS NULL in SQL', function () {
    $query = Collection::query();
    $engine = RuleEngine::for($query, ['id']);

    // @auth.id = null
    $engine->run('@auth.id = null', ['auth' => ['id' => 1]]);

    $sql = strtolower($query->toSql());
    // UnifiedEvaluator::applyNullComparison for sysvars uses whereRaw('? IS NULL', [$value])
    expect($sql)->toContain('where ? is null');
});

it('works correctly in memory for = null and != null', function () {
    $engine = RuleEngine::make(['id']);

    // = null
    expect($engine->evaluate('id = null', ['id' => null]))->toBeTrue()
        ->and($engine->evaluate('id = null', ['id' => 1]))->toBeFalse();

    // != null
    expect($engine->evaluate('id != null', ['id' => 1]))->toBeTrue()
        ->and($engine->evaluate('id != null', ['id' => null]))->toBeFalse();
});
