<?php

namespace App\Domain\Collections\Enums;

enum CollectionFieldType: string
{
    case Text = 'text';
    case LongText = 'longtext';
    case Number = 'number';
    case Boolean = 'boolean';
    case Datetime = 'timestamp';
    case Email = 'email';
    case Url = 'url';
    case Json = 'json';
    case Relation = 'relation';

    public function typeProperties(): array
    {
        return match ($this) {
            self::Text => ['min' => null, 'max' => null],
            self::Email => ['min' => null, 'max' => null],
            self::Url => ['min' => null, 'max' => null],
            self::LongText => [],
            self::Relation => ['target_collection_id' => null, 'cascade_on_delete' => false],
            self::Number, self::Boolean, self::Datetime, self::Json => [],
        };
    }

    public function allowedProperties(): array
    {
        return [...self::commonPropertyNames(), ...array_keys($this->typeProperties())];
    }

    public function defaultShape(): array
    {
        return array_merge(self::commonDefaults(), $this->typeProperties());
    }

    public function typeValidationRules(string $prefix): array
    {
        return match ($this) {
            self::Text, self::Email, self::Url => [
                "{$prefix}.min" => ['nullable', 'integer', 'min:0'],
                "{$prefix}.max" => ['nullable', 'integer', 'min:1'],
            ],
            self::Number => [
                "{$prefix}.min" => ['nullable', 'numeric'],
                "{$prefix}.max" => ['nullable', 'numeric'],
            ],
            self::Relation => [
                "{$prefix}.target_collection_id" => ['required', 'string', 'exists:collections,id'],
                "{$prefix}.cascade_on_delete" => ['sometimes', 'boolean'],
            ],
            self::LongText, self::Boolean, self::Datetime, self::Json => [],
        };
    }

    public function recordValidationRule(): string
    {
        return match ($this) {
            self::Text, self::LongText, self::Email => 'string',
            self::Number => 'numeric',
            self::Boolean => 'boolean',
            self::Datetime => 'date',
            self::Url => 'url',
            self::Json => 'json',
            self::Relation => 'string',
        };
    }

    public function eloquentCast(): ?string
    {
        return match ($this) {
            self::Number => 'float',
            self::Boolean => 'boolean',
            self::Datetime => 'datetime',
            self::Json => 'json',
            default => null,
        };
    }

    public function isIndexable(): bool
    {
        return ! in_array($this, [self::Json, self::LongText, self::Url], true);
    }

    public static function commonPropertyNames(): array
    {
        return ['id', 'name', 'type', 'order', 'nullable', 'unique', 'default'];
    }

    public static function commonDefaults(): array
    {
        return [
            'order' => 0,
            'nullable' => false,
            'unique' => false,
            'default' => null,
        ];
    }
}
