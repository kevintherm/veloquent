<?php

namespace App\Domain\Records\QueryBuilder;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Records\Services\RelationJoinResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use RuntimeException;

class RecordBuilder extends Builder
{
    public function applySorting(?string $sortParam): static
    {
        if (! $collection = $this->getModel()?->collection) {
            throw new RuntimeException("Model's collection is not set");
        }

        $systemSorts = ['created_at', 'updated_at', 'id'];
        $allowed = array_merge(Arr::pluck($collection->fields, 'name'), $systemSorts);

        if (empty($sortParam)) {
            return $this->orderByDesc('created_at');
        }

        foreach (explode(',', $sortParam) as $sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');

            if (in_array($column, $allowed)) {
                $this->orderBy($column, $direction);
            }
        }

        return $this;
    }

    public function applyRule(string $action): static
    {
        if (! $collection = $this->getModel()?->collection) {
            throw new RuntimeException("Model's collection is not set");
        }

        $rule = $collection->api_rules[$action] ?? null;

        if ($rule === null) {
            $this->whereRaw('1 = 0');

            return $this;
        }

        $allowedFields = $this->getAllowedFilterFields($collection);
        $resolver = new RelationJoinResolver($collection, $this);

        // Ensure we select only the base table columns to avoid collisions with joined tables
        $this->select($collection->getPhysicalTableName().'.*');

        return $this->where(fn ($q) => QueryFilter::for($q, $allowedFields)
            ->withRelationJoinResolver($resolver)
            ->run($rule)
        );
    }

    public function applyFilter(?string $filter): static
    {
        if (! $filter) {
            return $this;
        }

        if (! $collection = $this->getModel()?->collection) {
            throw new RuntimeException("Model's collection is not set");
        }

        $allowedFields = $this->getAllowedFilterFields($collection);
        $resolver = new RelationJoinResolver($collection, $this);

        // Ensure we select only the base table columns to avoid collisions with joined tables
        $this->select($collection->getPhysicalTableName().'.*');

        return $this->where(fn ($q) => QueryFilter::for($q, $allowedFields)
            ->withRelationJoinResolver($resolver)
            ->run($filter)
        );
    }

    private function getAllowedFilterFields(Collection $collection, string $prefix = '', int $depth = 0): array
    {
        // System fields are always allowed at the root
        $fields = $depth === 0 ? ['id', 'token', 'created_at', 'updated_at'] : [];

        if ($depth > 2) { // Limit recursion depth for safety
            return $fields;
        }

        foreach ($collection->fields ?? [] as $field) {
            $name = (string) $field['name'];
            $fullPath = $prefix ? "{$prefix}.{$name}" : $name;
            $fields[] = $fullPath;

            if (($field['type'] ?? null) === CollectionFieldType::Relation->value) {
                // Add standard sub-fields for relations (id, created_at, etc.)
                $fields[] = "{$fullPath}.id";
                $fields[] = "{$fullPath}.created_at";
                $fields[] = "{$fullPath}.updated_at";

                $targetCollectionId = $field['target_collection_id'] ?? null;
                if ($targetCollectionId) {
                    $targetCollection = Collection::query()->find($targetCollectionId);
                    if ($targetCollection) {
                        $fields = array_merge($fields, $this->getAllowedFilterFields($targetCollection, $fullPath, $depth + 1));
                    }
                }
            }
        }

        return array_unique($fields);
    }
}
