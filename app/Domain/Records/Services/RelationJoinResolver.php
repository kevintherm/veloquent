<?php

namespace App\Domain\Records\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Field;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class RelationJoinResolver
{
    /** @var array<string, string> Record of registered join aliases to prevent duplicates */
    private array $joinedAliases = [];

    public function __construct(
        private readonly Collection $sourceCollection,
        private readonly EloquentBuilder|QueryBuilder $query,
        private readonly string $baseAlias = '',
    ) {}

    /**
     * Resolve a dot-notation field (e.g., author.name or author.publisher.title)
     * into an aliased column string (e.g., ___sourceTable_author__publisher.title).
     * Also registers the necessary LEFT JOINs on the query.
     */
    public function resolveField(string $dotPath): string
    {
        $parts = explode('.', $dotPath);

        // If no dots, it's a field in the source table
        if (count($parts) === 1) {
            $alias = $this->baseAlias ?: $this->sourceCollection->getPhysicalTableName();

            return "{$alias}.{$dotPath}";
        }

        $relationName = array_shift($parts);
        $remainingPath = implode('.', $parts);

        $field = collect($this->sourceCollection->fields ?? [])
            ->first(fn (Field|array $f): bool => ($f['name'] ?? '') === $relationName);

        // If not a relation field, we can't join further, return as is (wrapped in base alias)
        if (! $field || ($field['type'] ?? '') !== CollectionFieldType::Relation->value) {
            $alias = $this->baseAlias ?: $this->sourceCollection->getPhysicalTableName();

            return "{$alias}.{$dotPath}";
        }

        $targetCollectionId = $field['target_collection_id'] ?? null;
        if (! $targetCollectionId) {
            $alias = $this->baseAlias ?: $this->sourceCollection->getPhysicalTableName();

            return "{$alias}.{$dotPath}";
        }

        $targetCollection = Collection::query()->find($targetCollectionId);
        if (! $targetCollection) {
            $alias = $this->baseAlias ?: $this->sourceCollection->getPhysicalTableName();

            return "{$alias}.{$dotPath}";
        }

        $sourceTable = $this->sourceCollection->getPhysicalTableName();
        $sourceAlias = $this->baseAlias ?: $sourceTable;

        // Deterministic alias for the join
        $joinAlias = "{$sourceAlias}__{$relationName}";
        if ($this->baseAlias === '') {
            // Root join gets a prefix to ensure no collisions with table names
            $joinAlias = '___'.$joinAlias;
        }

        $targetTable = $targetCollection->getPhysicalTableName();

        $this->registerJoin($sourceAlias, $relationName, $targetTable, $joinAlias);

        // Recursively resolve the rest of the path
        $nestedResolver = new self($targetCollection, $this->query, $joinAlias);
        $nestedResolver->joinedAliases = &$this->joinedAliases;

        return $nestedResolver->resolveField($remainingPath);
    }

    private function registerJoin(string $sourceAlias, string $relationName, string $targetTable, string $joinAlias): void
    {
        if (isset($this->joinedAliases[$joinAlias])) {
            return;
        }

        $this->query->leftJoin(
            "{$targetTable} as {$joinAlias}",
            "{$sourceAlias}.{$relationName}",
            '=',
            "{$joinAlias}.id"
        );

        $this->joinedAliases[$joinAlias] = $targetTable;
    }
}
