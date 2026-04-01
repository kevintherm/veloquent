<?php

namespace App\Domain\RuleEngine\Contracts;

/**
 * Adapter for extending field resolution during in-memory evaluation.
 */
interface FieldResolverAdapter
{
    /**
     * Whether this adapter can handle the given field path.
     */
    public function supports(string $fieldPath): bool;

    /**
     * Resolve the field value from the evaluation context.
     */
    public function resolveForEvaluation(string $fieldPath, array $context): mixed;
}
