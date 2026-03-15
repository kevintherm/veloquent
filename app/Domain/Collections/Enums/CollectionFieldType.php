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
            self::Text => ['length' => 255],
            self::Email => ['length' => 255],
            self::Url => ['length' => null],
            self::LongText => [],
            self::Relation => ['collection' => null],
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
                "{$prefix}.length" => ['nullable', 'integer', 'min:1'],
            ],
            self::Relation => [
                "{$prefix}.collection" => ['nullable', 'string', 'regex:/^[a-zA-Z_]+$/'],
            ],
            self::LongText, self::Number, self::Boolean, self::Datetime, self::Json => [],
        };
    }

    public function recordValidationRule(): string
    {
        return match ($this) {
            self::Text, self::LongText, self::Email, self::Relation => 'string',
            self::Number => 'numeric',
            self::Boolean => 'boolean',
            self::Datetime => 'date',
            self::Url => 'url',
            self::Json => 'json',
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

    public static function commonPropertyNames(): array
    {
        return ['name', 'type', 'order', 'nullable', 'unique', 'default'];
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
