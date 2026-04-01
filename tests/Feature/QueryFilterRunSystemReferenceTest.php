<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\RuleContextBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

function makeSqlRuleContext(array $body = [], int $authId = 21): array
{
    $authCollection = new Collection;
    $authCollection->name = 'users';
    $authCollection->type = CollectionType::Base;
    $authCollection->is_system = false;
    $authCollection->fields = [];
    $authCollection->api_rules = [];

    $user = Record::of($authCollection);
    $user->setAttribute('id', $authId);

    Auth::setUser($user);

    $request = Request::create('/api/collections/posts/records', 'POST', $body);
    app()->instance('request', $request);

    return (new RuleContextBuilder)->build($request, $user);
}

it('resolves @request.auth reference in run mode to query bindings', function () {
    $context = makeSqlRuleContext();
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('id = @request.auth.id', $context);

    expect($query->getBindings())->toBe([21]);
});

it('normalizes @request.auth on the left to a field comparison in run mode', function () {
    $context = makeSqlRuleContext();
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('@request.auth.id = id', $context);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('id')
        ->and($sql)->toContain('= ?')
        ->and($query->getBindings())->toBe([21]);
});

it('normalizes literal-on-left ordered comparisons by inverting the operator', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('5 > id');

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('id')
        ->and($sql)->toContain('< ?')
        ->and($query->getBindings())->toBe([5]);
});

it('uses column comparison when both operands are fields', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['id', 'updated_at'])->run('id = updated_at');

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('id')
        ->and($sql)->toContain('updated_at')
        ->and($sql)->not->toContain('?')
        ->and($query->getBindings())->toBe([]);
});

it('compiles two @request operands into a bound literal SQL comparison', function () {
    $context = makeSqlRuleContext(['user' => 21]);
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('@request.body.user = @request.auth.id', $context);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('? = ?')
        ->and($query->getBindings())->toBe([21, 21]);
});
