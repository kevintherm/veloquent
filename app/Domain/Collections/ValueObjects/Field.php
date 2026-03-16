<?php

namespace App\Domain\Collections\ValueObjects;

use App\Domain\Collections\Enums\CollectionFieldType;

class Field implements \ArrayAccess, \JsonSerializable
{
    public function __construct(
        public string $name,
        public string $type,
        public int $order,
        public bool $nullable,
        public bool $unique,
        public mixed $default,
        public ?string $id = null,
        public ?int $min = null,
        public ?int $max = null,
        public ?string $collection = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $typeValue = $data['type'] instanceof CollectionFieldType
            ? $data['type']->value
            : $data['type'];

        $type = CollectionFieldType::from($typeValue);
        $shape = [
            ...CollectionFieldType::commonDefaults(),
            ...$type->typeProperties(),
            ...$data,
            'type' => $type->value,
        ];

        return new self(
            name: (string) ($shape['name'] ?? ''),
            type: (string) $shape['type'],
            order: (int) $shape['order'],
            nullable: (bool) $shape['nullable'],
            unique: (bool) $shape['unique'],
            default: $shape['default'] ?? null,
            id: isset($shape['id']) ? (string) $shape['id'] : null,
            min: isset($shape['min']) ? (is_null($shape['min']) ? null : (int) $shape['min']) : null,
            max: isset($shape['max']) ? (is_null($shape['max']) ? null : (int) $shape['max']) : null,
            collection: isset($shape['collection']) ? (is_null($shape['collection']) ? null : (string) $shape['collection']) : null,
        );
    }

    public function toArray(): array
    {
        $type = CollectionFieldType::from($this->type);
        $allowed = $type->allowedProperties();

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'order' => $this->order,
            'nullable' => $this->nullable,
            'unique' => $this->unique,
            'default' => $this->default,
            'min' => $this->min,
            'max' => $this->max,
            'collection' => $this->collection,
        ];

        return collect($data)
            ->only($allowed)
            ->all();
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        $key = (string) $offset;
        $array = $this->toArray();

        return $array[$key] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $key = (string) $offset;

        if ($key === 'id') {
            $this->id = is_null($value) ? null : (string) $value;

            return;
        }

        if ($key === 'name') {
            $this->name = (string) $value;

            return;
        }

        if ($key === 'type') {
            $this->type = (string) $value;

            return;
        }

        if ($key === 'order') {
            $this->order = (int) $value;

            return;
        }

        if ($key === 'nullable') {
            $this->nullable = (bool) $value;

            return;
        }

        if ($key === 'unique') {
            $this->unique = (bool) $value;

            return;
        }

        if ($key === 'default') {
            $this->default = $value;

            return;
        }

        if ($key === 'min') {
            $this->min = is_null($value) ? null : (int) $value;

            return;
        }

        if ($key === 'max') {
            $this->max = is_null($value) ? null : (int) $value;

            return;
        }

        if ($key === 'collection') {
            $this->collection = is_null($value) ? null : (string) $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->offsetSet($offset, null);
    }
}
