<?php

namespace App\Domain\RuleEngine\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Adapter for extending field resolution during SQL query compilation.
 */
interface QueryFieldAdapter
{
    /**
     * Whether this adapter can handle the given field path.
     */
    public function supports(string $fieldPath): bool;

    /**
     * Resolve the field path to a column reference (string or raw Expression)
     * and optionally register JOINs or other query modifications on $query.
     */
    public function resolveForQuery(string $fieldPath, Builder $query): string|Expression;
}
