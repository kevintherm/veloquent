<?php

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use App\Domain\QueryCompiler\Services\QueryFilter;

it('allows @request system grammar during lint', function () {
    $query = Collection::query();

    expect(fn () => QueryFilter::for($query, ['id', 'title'])->lint('id = @request.auth.id && title = "hello"'))
        ->not->toThrow(InvalidRuleExpressionException::class);
});

it('allows symmetric scalar grammar during SQL lint for reversible operators', function () {
    $query = Collection::query();

    expect(fn () => QueryFilter::for($query, ['id', 'title'])->lint('@request.auth.id = id'))
        ->not->toThrow(InvalidRuleExpressionException::class)
        ->and(fn () => QueryFilter::for($query, ['id', 'title'])->lint('5 > id'))
        ->not->toThrow(InvalidRuleExpressionException::class)
        ->and(fn () => QueryFilter::for($query, ['id', 'title'])->lint('id = title'))
        ->not->toThrow(InvalidRuleExpressionException::class)
        ->and(fn () => QueryFilter::for($query, ['id', 'title'])->lint('@request.body.user = @request.auth.id'))
        ->not->toThrow(InvalidRuleExpressionException::class);
});

it('rejects flipped grammar for non-reversible operators during SQL lint', function () {
    $query = Collection::query();

    expect(fn () => QueryFilter::for($query, ['title'])->lint('"admin" like title'))
        ->toThrow(InvalidRuleExpressionException::class);
});

it('allows @-prefixed system grammar in field position during in-memory lint', function () {
    $query = Collection::query();

    expect(fn () => QueryFilter::for($query, ['id'])->lint('@request.auth.id = 1', true))
        ->not->toThrow(InvalidRuleExpressionException::class);
});
