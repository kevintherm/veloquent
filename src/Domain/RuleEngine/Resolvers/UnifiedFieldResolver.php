<?php

declare(strict_types=1);

namespace Veloquent\Core\Domain\RuleEngine\Resolvers;

use Illuminate\Database\Eloquent\Builder;
use Kevintherm\Exprc\Resolvers\FieldResolverInterface;
use Veloquent\Core\Domain\Records\Services\RelationJoinResolver;

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
        if (str_starts_with($field, '@') || str_starts_with($field, '__numeric__')) {
            return $field;
        }

        if ($this->joinResolver) {
            return $this->joinResolver->resolveField($field);
        }

        return $field;
    }
}
