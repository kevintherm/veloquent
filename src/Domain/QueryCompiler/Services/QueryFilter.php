<?php

namespace Veloquent\Core\Domain\QueryCompiler\Services;

use Veloquent\Core\Domain\RuleEngine\RuleEngine;

/**
 * Compatible QueryFilter facade for the unified RuleEngine.
 */
class QueryFilter extends RuleEngine
{
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
