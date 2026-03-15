<?php

namespace App\Domain\Collections\Casts;

use App\Domain\Collections\ValueObjects\Field;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class FieldCollectionCast implements CastsAttributes
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

        return array_map(function (mixed $field): Field {
            if ($field instanceof Field) {
                return $field;
            }

            return Field::fromArray((array) $field);
        }, $decoded);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $fields = is_null($value) ? [] : $value;

        if (! is_array($fields)) {
            $fields = [];
        }

        $serialized = array_map(function (mixed $field): array {
            if ($field instanceof Field) {
                return $field->toArray();
            }

            return Field::fromArray((array) $field)->toArray();
        }, $fields);

        return json_encode(array_values($serialized), JSON_THROW_ON_ERROR);
    }
}
