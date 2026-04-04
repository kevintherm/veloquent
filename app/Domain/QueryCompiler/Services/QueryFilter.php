<?php

namespace App\Domain\QueryCompiler\Services;

use App\Domain\RuleEngine\RuleEngine;
use Illuminate\Database\Eloquent\Builder;

/**
 * Compatible QueryFilter facade for the unified RuleEngine.
 */
class QueryFilter extends RuleEngine
{
    public static function for(Builder $query, array $allowedFields = []): static
    {
        $instance = new static;
        $instance->query = $query;
        $instance->allowedFields = $allowedFields;

        return $instance;
    }

    public function withRelationJoinResolver($resolver): static
    {
        $this->joinResolver = $resolver;

        return $this;
    }

    public function withQueryFieldAdapter(mixed $adapter): static
    {
        return $this;
    }
}
