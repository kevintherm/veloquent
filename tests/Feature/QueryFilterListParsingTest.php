<?php

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;

it('parses numeric list values for in operator', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('id in (3,2)');

    expect(strtolower($query->toSql()))->toContain(' in (?, ?)')
        ->and($query->getBindings())->toBe([3, 2]);
});

it('parses numeric list values for not in operator', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('id not in (3, 2)');

    expect(strtolower($query->toSql()))->toContain(' not in (?, ?)')
        ->and($query->getBindings())->toBe([3, 2]);
});
