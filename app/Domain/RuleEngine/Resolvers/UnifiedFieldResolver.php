<?php

declare(strict_types=1);

namespace App\Domain\RuleEngine\Resolvers;

use App\Domain\Records\Services\RelationJoinResolver;
use Illuminate\Database\Eloquent\Builder;
use Kevintherm\Exprc\Resolvers\FieldResolverInterface;

final class UnifiedFieldResolver implements FieldResolverInterface
{
    private ?RelationJoinResolver $joinResolver = null;

    private ?Builder $query = null;

    public function withJoinResolver(?RelationJoinResolver $resolver): self
    {
        $this->joinResolver = $resolver;

        return $this;
    }

    public function setQuery(Builder $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function resolve(string $field): string
    {
        if (str_starts_with($field, '__sysvar__') || str_starts_with($field, '__numeric__')) {
            return $field;
        }

        if ($this->joinResolver && str_contains($field, '.')) {
            return $this->joinResolver->resolveField($field);
        }

        return $field;
    }
}
