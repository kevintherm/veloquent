<?php

use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use App\Domain\RuleEngine\RuleEngine;

it('accepts valid @-prefixed system variables in lint', function () {
    $engine = RuleEngine::make(['user']);
    expect(fn () => $engine->lint('user = @request.auth.id'))->not->toThrow(InvalidRuleExpressionException::class);
    expect(fn () => $engine->lint('user = @request.body.user'))->not->toThrow(InvalidRuleExpressionException::class);
    expect(fn () => $engine->lint('user = @request.param.id'))->not->toThrow(InvalidRuleExpressionException::class);
    expect(fn () => $engine->lint('user = @request.query.user'))->not->toThrow(InvalidRuleExpressionException::class);
});

it('rejects malformed @-prefixed variables in lint (typo)', function () {
    $engine = RuleEngine::make(['user']);
    expect(fn () => $engine->lint('user = @rquest.auth.id'))
        ->toThrow(InvalidRuleExpressionException::class);
});

it('rejects invalid @-prefixed namespace in lint', function () {
    $engine = RuleEngine::make(['user']);
    expect(fn () => $engine->lint('user = @env.secret'))
        ->toThrow(InvalidRuleExpressionException::class);
});

it('rejects unknown field in lint', function () {
    $engine = RuleEngine::make(['id', 'title']);
    expect(fn () => $engine->lint('unknown_field = "value"'))
        ->toThrow(InvalidRuleExpressionException::class);
});

it('accepts @-variable on the LHS in lint (RuleEngine allows symmetric)', function () {
    $engine = RuleEngine::make(['user']);
    expect(fn () => $engine->lint('@request.auth.id = user'))->not->toThrow(InvalidRuleExpressionException::class);
});

it('rejects the old is null operator', function () {
    $engine = RuleEngine::make(['id']);
    expect(fn () => $engine->lint('id is null'))
        ->toThrow(InvalidRuleExpressionException::class);
});

it('accepts = null as the null check', function () {
    $engine = RuleEngine::make(['id']);
    expect(fn () => $engine->lint('id = null'))->not->toThrow(InvalidRuleExpressionException::class);
    expect(fn () => $engine->lint('id != null'))->not->toThrow(InvalidRuleExpressionException::class);
});
