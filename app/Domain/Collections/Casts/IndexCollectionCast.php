<?php

namespace App\Domain\Collections\Casts;

use App\Domain\Collections\ValueObjects\Index;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class IndexCollectionCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_null($value)) {
            return [];
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded)) {
            return [];
        }

        return array_map(function (mixed $index): Index {
            if ($index instanceof Index) {
                return $index;
            }

            return Index::fromArray((array) $index);
        }, $decoded);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $indexes = is_null($value) ? [] : $value;

        if (! is_array($indexes)) {
            $indexes = [];
        }

        $serialized = array_map(function (mixed $index): array {
            if ($index instanceof Index) {
                return $index->toArray();
            }

            return Index::fromArray((array) $index)->toArray();
        }, $indexes);

        return json_encode(array_values($serialized), JSON_THROW_ON_ERROR);
    }
}
