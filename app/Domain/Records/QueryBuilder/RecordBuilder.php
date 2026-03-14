<?php

namespace App\Domain\Records\QueryBuilder;

use App\Domain\QueryCompiler\Services\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use RuntimeException;

class RecordBuilder extends Builder
{
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

        $allowedFields = Arr::pluck($collection->fields, 'name');
        $this->where(fn ($q) => QueryFilter::for($q, $allowedFields)->run($rule));

        return $this;
    }

    public function applyFilter(?string $filter): static
    {
        if (! $filter) {
            return $this;
        }

        if (! $collection = $this->getModel()?->collection) {
            throw new RuntimeException("Model's collection is not set");
        }

        $allowedFields = Arr::pluck($collection->fields ?? [], 'name');

        return $this->where(fn ($q) => QueryFilter::for($q, $allowedFields)->run($filter));
    }
}
