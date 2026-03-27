<?php

namespace App\Domain\Records\QueryBuilder;

use App\Domain\QueryCompiler\Services\AllowedFieldsResolver;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Records\Services\RelationJoinResolver;
use App\Domain\Records\Services\RuleContextBuilder;
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

        $allowedFields = app(AllowedFieldsResolver::class)->resolveFromCollection($collection);
        $resolver = new RelationJoinResolver($collection, $this);

        // Ensure we select only the base table columns to avoid collisions with joined tables
        $this->select($collection->getPhysicalTableName().'.*');

        $context = app(RuleContextBuilder::class)->build();

        return $this->where(fn ($q) => QueryFilter::for($q, $allowedFields)
            ->withRelationJoinResolver($resolver)
            ->run($rule, $context)
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

        $allowedFields = app(AllowedFieldsResolver::class)->resolveFromCollection($collection);
        $resolver = new RelationJoinResolver($collection, $this);

        // Ensure we select only the base table columns to avoid collisions with joined tables
        $this->select($collection->getPhysicalTableName().'.*');

        $context = app(RuleContextBuilder::class)->build();

        return $this->where(fn ($q) => QueryFilter::for($q, $allowedFields)
            ->withRelationJoinResolver($resolver)
            ->run($filter, $context)
        );
    }
}
