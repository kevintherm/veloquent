<?php

namespace App\Infrastructure\Traits;

use App\Domain\QueryCompiler\Services\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    public function scopeFilter(Builder $query, string $filter): Builder
    {
        return (new QueryFilter($query))->run($filter);
    }
}
