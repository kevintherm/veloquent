<?php

namespace App\Domain\Collections\QueryBuilder;

use App\Domain\QueryCompiler\Services\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

class CollectionBuilder extends Builder
{
    public function applyFilter(?string $filter): static
    {
        if (! $filter) {
            return $this;
        }

        $allowedFields = $this->getModel()->getFillable();

        return $this->where(fn ($q) => QueryFilter::for($q, $allowedFields)->run($filter));
    }
}
