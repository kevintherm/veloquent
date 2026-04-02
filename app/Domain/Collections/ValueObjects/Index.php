<?php

namespace App\Domain\Collections\ValueObjects;

use App\Domain\Collections\Enums\IndexType;

class Index implements \ArrayAccess, \JsonSerializable
{
    public function __construct(
        public array $columns,
        public string $type,
    ) {}

    public static function fromArray(array $data): self
    {
        $typeValue = $data['type'] instanceof IndexType
            ? $data['type']->value
            : (string) ($data['type'] ?? IndexType::Index->value);

        $type = IndexType::from($typeValue);
        $columns = collect($data['columns'] ?? [])
            ->filter(fn (mixed $column): bool => is_string($column) && $column !== '')
            ->values()
            ->all();

        return new self(
            columns: $columns,
            type: $type->value,
        );
    }

    /**
     * @return array<int, self>
     */
    public static function collection(mixed $indexes): array
    {
        if (is_array($indexes)) {
            return collect($indexes)
                ->map(function (mixed $index): self {
                    if ($index instanceof self) {
                        return $index;
                    }

                    return self::fromArray((array) $index);
                })
                ->values()
                ->all();
        }

        if (is_string($indexes) && $indexes !== '') {
            $decoded = json_decode($indexes, true);

            if (! is_array($decoded)) {
                return [];
            }

            return collect($decoded)
                ->map(fn (mixed $index): self => self::fromArray((array) $index))
                ->values()
                ->all();
        }

        return [];
    }

    public function toArray(): array
    {
        return [
            'columns' => array_values($this->columns),
            'type' => $this->type,
        ];
    }

    public function generateName(string $table): string
    {
        return self::generateIndexName($table, $this->columns, $this->type);
    }

    public function identityKey(): string
    {
        $columns = $this->columns;
        sort($columns);

        return implode('|', $columns).'|'.$this->type;
    }

    public static function generateIndexName(string $table, array $columns, string $type): string
    {
        $baseName = implode('_', [
            $table,
            ...array_values($columns),
            $type,
        ]);

        if (strlen($baseName) <= 64) {
            return $baseName;
        }

        $hash = substr(md5($baseName), 0, 8);
        $maxBaseLength = 64 - strlen($hash) - 1;

        return substr($baseName, 0, $maxBaseLength).'_'.$hash;
    }

    public function referencesColumn(string $column): bool
    {
        return in_array($column, $this->columns, true);
    }

    public function withRenamedColumn(string $from, string $to): self
    {
        $columns = array_map(
            fn (string $column): string => $column === $from ? $to : $column,
            $this->columns
        );

        return new self(columns: $columns, type: $this->type);
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

        if ($key === 'columns') {
            $this->columns = collect((array) $value)
                ->filter(fn (mixed $column): bool => is_string($column) && $column !== '')
                ->values()
                ->all();

            return;
        }

        if ($key === 'type') {
            $this->type = (string) $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->offsetSet($offset, null);
    }
}
