<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCollectionsAction
{
    public function execute(string $filters = '', string $sorts = '', int $perPage = 15): LengthAwarePaginator
    {
        $collections = Collection::query()
            ->applySorting($sorts)
            ->applyFilter($filters)
            ->paginate($perPage);

        return $collections;
    }
}
