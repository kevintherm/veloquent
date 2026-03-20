<?php

namespace App\Domain\Collections\QueryBuilder;

use App\Domain\QueryCompiler\Services\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

class CollectionBuilder extends Builder
{
    public function applySorting(?string $sortParam): static
    {
        $systemSorts = ['created_at', 'updated_at', 'id'];
        $allowed = array_merge($this->getModel()->getFillable(), $systemSorts);

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

    public function applyFilter(?string $filter): static
    {
        if (!$filter) {
            return $this;
        }

        $allowedFields = $this->getModel()->getFillable();

        return $this->where(fn($q) => QueryFilter::for($q, $allowedFields)->run($filter));
    }
}
