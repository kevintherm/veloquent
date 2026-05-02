<?php

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\QueryCompiler\Services\QueryFilter;

it('applies where null when comparing equals null', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('id = null');

    expect(strtolower($query->toSql()))->toContain('"id" is null')
        ->and($query->getBindings())->toBe([]);
});

it('applies where not null when comparing not equals null', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('id != null');

    expect(strtolower($query->toSql()))->toContain('"id" is not null')
        ->and($query->getBindings())->toBe([]);
});
