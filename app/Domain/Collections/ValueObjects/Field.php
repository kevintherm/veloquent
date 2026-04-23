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
        public bool $multiple = false,
        public ?int $max_size_kb = null,
        public array $allowed_mime_types = [],
        public bool $protected = false,
        public ?string $target_collection_id = null,
        public bool $cascade_on_delete = false,
        public ?bool $allow_decimals = null,
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
            multiple: (bool) ($shape['multiple'] ?? false),
            max_size_kb: isset($shape['max_size_kb']) ? (is_null($shape['max_size_kb']) ? null : (int) $shape['max_size_kb']) : null,
            allowed_mime_types: collect($shape['allowed_mime_types'] ?? [])
                ->filter(fn (mixed $mime): bool => is_string($mime) && trim($mime) !== '')
                ->values()
                ->all(),
            protected: (bool) ($shape['protected'] ?? false),
            target_collection_id: isset($shape['target_collection_id']) ? (is_null($shape['target_collection_id']) ? null : (string) $shape['target_collection_id']) : null,
            cascade_on_delete: (bool) ($shape['cascade_on_delete'] ?? false),
            allow_decimals: isset($shape['allow_decimals']) ? (is_null($shape['allow_decimals']) ? null : (bool) $shape['allow_decimals']) : null,
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
            'min' => $this->min,
            'max' => $this->max,
            'multiple' => $this->multiple,
            'max_size_kb' => $this->max_size_kb,
            'allowed_mime_types' => $this->allowed_mime_types,
            'protected' => $this->protected,
            'target_collection_id' => $this->target_collection_id,
            'cascade_on_delete' => $this->cascade_on_delete,
            'allow_decimals' => $this->allow_decimals,
        ];

        if ($type !== CollectionFieldType::File) {
            $data['default'] = $this->default;
        }

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

        if ($key === 'multiple') {
            $this->multiple = (bool) $value;

            return;
        }

        if ($key === 'max_size_kb') {
            $this->max_size_kb = is_null($value) ? null : (int) $value;

            return;
        }

        if ($key === 'allowed_mime_types') {
            $this->allowed_mime_types = collect((array) $value)
                ->filter(fn (mixed $mime): bool => is_string($mime) && trim($mime) !== '')
                ->values()
                ->all();

            return;
        }

        if ($key === 'protected') {
            $this->protected = (bool) $value;

            return;
        }

        if ($key === 'target_collection_id') {
            $this->target_collection_id = is_null($value) ? null : (string) $value;

            return;
        }

        if ($key === 'cascade_on_delete') {
            $this->cascade_on_delete = (bool) $value;

            return;
        }

        if ($key === 'allow_decimals') {
            $this->allow_decimals = is_null($value) ? null : (bool) $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->offsetSet($offset, null);
    }
}
