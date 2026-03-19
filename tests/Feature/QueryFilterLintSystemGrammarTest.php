<?php

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use App\Domain\QueryCompiler\Services\QueryFilter;

it('allows @request system grammar during lint', function () {
    $query = Collection::query();

    expect(fn () => QueryFilter::for($query, ['id', 'title'])->lint('id = @request.auth.id && title = "hello"'))
        ->not->toThrow(InvalidRuleExpressionException::class);
});

it('rejects @-prefixed system grammar in field position during lint', function () {
    $query = Collection::query();

    expect(fn () => QueryFilter::for($query, ['id'])->lint('@request.auth.id = 1'))
        ->toThrow(InvalidRuleExpressionException::class);
});
