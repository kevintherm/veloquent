<?php

namespace App\Domain\RuleEngine\Adapters;

use App\Domain\RuleEngine\Contracts\QueryFieldAdapter;
use App\Domain\Records\Services\RelationJoinResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * QueryFieldAdapter that wraps the existing RelationJoinResolver.
 *
 * Handles dot-notation relation fields like "author.verified" by resolving
 * them to aliased columns and registering LEFT JOINs on the query.
 */
class RelationJoinAdapter implements QueryFieldAdapter
{
    public function __construct(
        private readonly RelationJoinResolver $resolver,
    ) {}

    public function supports(string $fieldPath): bool
    {
        return str_contains($fieldPath, '.');
    }

    public function resolveForQuery(string $fieldPath, Builder $query): string|Expression
    {
        return $this->resolver->resolveField($fieldPath);
    }
}
